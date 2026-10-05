<?php

namespace Tests\Feature;

use App\Models\AccessApplication;
use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Models\Grant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end checks of the access-control rules:
 * field permissions, version pinning, link invalidation,
 * and grants surviving dataset updates unchanged.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $approver;
    private User $researcher;
    private Dataset $dataset;

    protected function setUp(): void
    {
        parent::setUp();

        // keep test CSVs off the real local disk (it holds the seeded demo data)
        Storage::fake('local');

        $this->approver = User::factory()->create(['role' => 'approver']);
        $this->researcher = User::factory()->create(['role' => 'researcher']);

        $this->dataset = Dataset::create([
            'title' => '测试数据集',
            'description' => 'desc',
            'department' => '测试部',
            'created_by' => $this->approver->id,
        ]);

        $fields = [
            ['name' => 'age', 'restricted' => false],
            ['name' => 'grade', 'restricted' => false],
            ['name' => 'student_id', 'restricted' => true],
            ['name' => 'health', 'restricted' => true],
        ];
        foreach (array_values($fields) as $i => $f) {
            $this->dataset->fields()->create([
                'name' => $f['name'], 'label' => $f['name'],
                'is_restricted' => $f['restricted'], 'position' => $i,
            ]);
        }

        $this->addVersion(1, "age,grade,student_id,health\n20,1,S1,none\n21,2,S2,asthma\n");
    }

    private function addVersion(int $n, string $csv): DatasetVersion
    {
        $path = "datasets/{$this->dataset->id}/v{$n}.csv";
        Storage::disk('local')->put($path, $csv);

        return $this->dataset->versions()->create([
            'version' => $n, 'file_path' => $path, 'changelog' => "v{$n}",
        ]);
    }

    private function applyAndApprove(array $fields, ?int $versionId = null, string $end = '2030-01-01'): Grant
    {
        $versionId ??= $this->dataset->versions()->first()->id;

        $response = $this->actingAs($this->researcher)->postJson('/api/applications', [
            'dataset_id' => $this->dataset->id,
            'dataset_version_id' => $versionId,
            'purpose' => '用于统计分析方法研究',
            'fields' => $fields,
            'access_start' => '2026-01-01',
            'access_end' => $end,
        ]);
        $response->assertCreated();

        $application = AccessApplication::latest('id')->first();
        $this->actingAs($this->approver)
            ->postJson("/api/admin/applications/{$application->id}/approve")
            ->assertOk();

        return Grant::latest('id')->first();
    }

    private function tokenFor(Grant $grant): string
    {
        $response = $this->actingAs($this->researcher)->postJson("/api/grants/{$grant->id}/token");
        $response->assertCreated();

        return $response->json('token');
    }

    public function test_catalog_preview_hides_restricted_fields(): void
    {
        $response = $this->getJson("/api/catalog/{$this->dataset->id}");
        $response->assertOk();

        $body = $response->getContent();
        $this->assertStringContainsString('age', $body);
        // restricted field names and their values must not leak
        $this->assertStringNotContainsString('student_id', $body);
        $this->assertStringNotContainsString('health', $body);
        $this->assertStringNotContainsString('S1', $body);
        $this->assertStringNotContainsString('asthma', $body);

        $preview = $response->json('preview');
        $this->assertNotEmpty($preview);
        foreach ($preview as $row) {
            $this->assertArrayNotHasKey('student_id', $row);
            $this->assertArrayNotHasKey('health', $row);
        }
    }

    public function test_download_serves_only_approved_fields(): void
    {
        $grant = $this->applyAndApprove(['age', 'grade']);
        $token = $this->tokenFor($grant);

        $response = $this->get("/api/download/{$token}");
        $response->assertOk();
        $this->assertEquals("age,grade", trim(explode("\n", $response->getContent())[0]));
        $this->assertStringNotContainsString('S1', $response->getContent());
    }

    public function test_download_rejects_field_param_tampering(): void
    {
        $grant = $this->applyAndApprove(['age', 'grade']);
        $token = $this->tokenFor($grant);

        // asking for an unapproved field via the query string is refused
        $this->get("/api/download/{$token}?fields=age,student_id")->assertForbidden();
        $this->get("/api/download/{$token}?fields=health")->assertForbidden();

        // narrowing to a subset of approved fields is fine
        $ok = $this->get("/api/download/{$token}?fields=grade");
        $ok->assertOk();
        $this->assertEquals('grade', trim(explode("\n", $ok->getContent())[0]));
    }

    public function test_pending_application_grants_no_access(): void
    {
        $this->actingAs($this->researcher)->postJson('/api/applications', [
            'dataset_id' => $this->dataset->id,
            'dataset_version_id' => $this->dataset->versions()->first()->id,
            'purpose' => '用于统计分析方法研究',
            'fields' => ['age'],
            'access_start' => '2026-01-01',
            'access_end' => '2030-01-01',
        ])->assertCreated();

        $this->assertEquals(0, Grant::count());
    }

    public function test_rejected_application_cannot_be_repurposed(): void
    {
        $this->actingAs($this->researcher)->postJson('/api/applications', [
            'dataset_id' => $this->dataset->id,
            'dataset_version_id' => $this->dataset->versions()->first()->id,
            'purpose' => '原始用途描述文本内容',
            'fields' => ['age'],
            'access_start' => '2026-01-01',
            'access_end' => '2030-01-01',
        ])->assertCreated();

        $application = AccessApplication::latest('id')->first();
        $this->actingAs($this->approver)
            ->postJson("/api/admin/applications/{$application->id}/reject", ['note' => '用途不清'])
            ->assertOk();

        // no endpoint exists to edit/resubmit it; a decided application is final
        $put = $this->putJson("/api/applications/{$application->id}", ['purpose' => '换个用途']);
        $this->assertContains($put->getStatusCode(), [404, 405]);
        $patch = $this->patchJson("/api/applications/{$application->id}", ['purpose' => '换个用途']);
        $this->assertContains($patch->getStatusCode(), [404, 405]);

        // and it cannot be approved afterwards either
        $this->actingAs($this->approver)
            ->postJson("/api/admin/applications/{$application->id}/approve")
            ->assertStatus(422);

        // the changed purpose must go through a brand new application
        $this->actingAs($this->researcher)->postJson('/api/applications', [
            'dataset_id' => $this->dataset->id,
            'dataset_version_id' => $this->dataset->versions()->first()->id,
            'purpose' => '修改后的全新用途说明',
            'fields' => ['age'],
            'access_start' => '2026-01-01',
            'access_end' => '2030-01-01',
        ])->assertCreated();

        $this->assertEquals(2, AccessApplication::count());
        $this->assertEquals('原始用途描述文本内容', $application->fresh()->purpose);
    }

    public function test_revoked_grant_kills_download_links_immediately(): void
    {
        $grant = $this->applyAndApprove(['age']);
        $token = $this->tokenFor($grant);

        $this->get("/api/download/{$token}")->assertOk();

        $this->actingAs($this->approver)->postJson("/api/admin/grants/{$grant->id}/revoke")->assertOk();

        $this->get("/api/download/{$token}")->assertStatus(410);
        // new tokens can no longer be issued either
        $this->actingAs($this->researcher)->postJson("/api/grants/{$grant->id}/token")->assertStatus(410);
    }

    public function test_expired_grant_kills_download_links(): void
    {
        $this->travelTo('2026-03-01 12:00:00');
        $grant = $this->applyAndApprove(['age'], null, '2026-06-30');
        $token = $this->tokenFor($grant);
        $this->get("/api/download/{$token}")->assertOk();

        $this->travelTo('2026-07-01 00:00:01');
        $this->get("/api/download/{$token}")->assertStatus(410);
    }

    public function test_dataset_update_does_not_expand_existing_grants(): void
    {
        // grant approved on v1 for public fields only
        $grant = $this->applyAndApprove(['age', 'grade']);
        $token = $this->tokenFor($grant);

        // approver publishes v2 with different data
        $this->addVersion(2, "age,grade,student_id,health\n99,9,S999,critical\n");

        // the old grant still serves v1 data, not v2
        $response = $this->get("/api/download/{$token}");
        $response->assertOk();
        $this->assertStringContainsString('S1', $this->dataset->versions()->find($grant->dataset_version_id)->readCsv()['rows'][0]['student_id']);
        $this->assertStringNotContainsString('99,9', $response->getContent());
        $this->assertStringContainsString('20,1', $response->getContent());

        // and still only the originally approved fields
        $this->get("/api/download/{$token}?fields=age,health")->assertForbidden();

        // the grant's pinned version is unchanged
        $this->assertEquals(1, $grant->fresh()->version->version);
    }

    public function test_grant_pins_requested_version(): void
    {
        $this->addVersion(2, "age,grade,student_id,health\n30,3,S3,none\n");

        $v1 = $this->dataset->versions()->where('version', 1)->first();
        $v2 = $this->dataset->versions()->where('version', 2)->first();

        $grantV2 = $this->applyAndApprove(['age'], $v2->id);
        $tokenV2 = $this->tokenFor($grantV2);
        $response = $this->get("/api/download/{$tokenV2}");
        $this->assertStringContainsString('30', $response->getContent());
        $this->assertStringNotContainsString('20', $response->getContent());

        // a grant on v1 keeps serving v1 even though v2 exists
        $grantV1 = $this->applyAndApprove(['age'], $v1->id);
        $tokenV1 = $this->tokenFor($grantV1);
        $response = $this->get("/api/download/{$tokenV1}");
        $this->assertStringContainsString('20', $response->getContent());
        $this->assertStringNotContainsString('30', $response->getContent());
    }

    public function test_citations_survive_revocation_and_link_to_used_version(): void
    {
        $grant = $this->applyAndApprove(['age']);

        $this->actingAs($this->researcher)->postJson('/api/citations', [
            'grant_id' => $grant->id,
            'citation_number' => 'DOI-10.1234/TEST.1',
            'title' => '基于测试数据的研究',
            'venue' => '测试学报',
            'published_at' => '2026-05-01',
        ])->assertCreated();

        $this->actingAs($this->approver)->postJson("/api/admin/grants/{$grant->id}/revoke")->assertOk();

        $registry = $this->getJson('/api/citations')->assertOk()->json('citations');
        $this->assertCount(1, $registry);
        $this->assertEquals('DOI-10.1234/TEST.1', $registry[0]['citation_number']);
        $this->assertEquals($grant->dataset_version_id, $registry[0]['dataset_version_id']);
        $this->assertEquals(1, $registry[0]['version']['version']);
    }

    public function test_citation_requires_own_grant(): void
    {
        $grant = $this->applyAndApprove(['age']);
        $other = User::factory()->create(['role' => 'researcher']);

        $this->actingAs($other)->postJson('/api/citations', [
            'grant_id' => $grant->id,
            'citation_number' => 'DOI-10.1234/STOLEN',
            'title' => '冒名登记',
        ])->assertForbidden();
    }

    public function test_application_validation(): void
    {
        $v1 = $this->dataset->versions()->first();

        // unknown field
        $this->actingAs($this->researcher)->postJson('/api/applications', [
            'dataset_id' => $this->dataset->id,
            'dataset_version_id' => $v1->id,
            'purpose' => '用于统计分析方法研究',
            'fields' => ['age', 'nonexistent'],
            'access_start' => '2026-01-01',
            'access_end' => '2030-01-01',
        ])->assertStatus(422);

        // end before start
        $this->actingAs($this->researcher)->postJson('/api/applications', [
            'dataset_id' => $this->dataset->id,
            'dataset_version_id' => $v1->id,
            'purpose' => '用于统计分析方法研究',
            'fields' => ['age'],
            'access_start' => '2030-01-01',
            'access_end' => '2026-01-01',
        ])->assertStatus(422);
    }

    public function test_approver_routes_require_approver_role(): void
    {
        $this->actingAs($this->researcher)->getJson('/api/admin/applications')->assertForbidden();
        $this->actingAs($this->researcher)->postJson('/api/admin/datasets', [])->assertForbidden();
    }
}
