<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\AccessRequest;
use App\Models\Citation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CitationTest extends TestCase
{
    use RefreshDatabase;

    private AccessGrant $grant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUsers();
        $version = $this->createDatasetVersion(
            fields: [['name' => 'building'], ['name' => 'meter_id', 'restricted' => true]],
            rows: [['理科楼', 'M-1001']],
        );

        $request = AccessRequest::create([
            'user_id' => $this->researcher->id,
            'dataset_version_id' => $version->id,
            'purpose' => '引用测试用途说明文字',
            'requested_fields' => ['building'],
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'status' => 'approved',
        ]);

        $this->grant = AccessGrant::create([
            'access_request_id' => $request->id,
            'user_id' => $this->researcher->id,
            'dataset_version_id' => $version->id,
            'granted_fields' => ['building'],
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
        ]);
    }

    public function test_citation_is_linked_to_the_actually_used_version(): void
    {
        $response = $this->actingAsUser($this->researcher)->postJson('/api/citations', [
            'access_grant_id' => $this->grant->id,
            'title' => '基于校园能耗数据的节能策略研究',
            'venue' => '某学术期刊',
            'published_at' => '2026-06-01',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('version.version', '1.0');
        $this->assertMatchesRegularExpression('/^CIT-\d{4}-\d{4}$/', $response->json('citation_number'));

        $citation = Citation::sole();
        $this->assertSame($this->grant->dataset_version_id, $citation->dataset_version_id);
    }

    public function test_citation_survives_revocation_and_expiry(): void
    {
        $this->actingAsUser($this->researcher)->postJson('/api/citations', [
            'access_grant_id' => $this->grant->id,
            'title' => '已发表成果',
            'published_at' => '2026-06-01',
        ])->assertCreated();

        // Revoke the grant — the citation must remain.
        $this->actingAsUser($this->admin)->postJson("/api/admin/grants/{$this->grant->id}/revoke",
            ['reason' => '授权期满清理'])->assertOk();

        $this->assertSame(1, Citation::count());

        $list = $this->actingAsUser($this->researcher)->getJson('/api/citations');
        $list->assertOk();
        $this->assertCount(1, $list->json());
        $this->assertSame('revoked', $list->json('0.grant_status'));

        // Registration is still possible after revocation (data was used while valid).
        $this->actingAsUser($this->researcher)->postJson('/api/citations', [
            'access_grant_id' => $this->grant->id,
            'title' => '撤回后补登的成果',
            'published_at' => '2026-07-01',
        ])->assertCreated();

        $this->assertSame(2, Citation::count());
    }

    public function test_cannot_register_citation_against_someone_elses_grant(): void
    {
        $other = \App\Models\User::factory()->create(['role' => 'researcher']);

        $this->actingAsUser($other)->postJson('/api/citations', [
            'access_grant_id' => $this->grant->id,
            'title' => '冒名登记',
            'published_at' => '2026-06-01',
        ])->assertForbidden();

        $this->assertSame(0, Citation::count());
    }
}
