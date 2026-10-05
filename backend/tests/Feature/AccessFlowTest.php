<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\AccessRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeVersion()
    {
        return $this->createDatasetVersion(fields: [
            ['name' => 'building'],
            ['name' => 'energy_kwh'],
            ['name' => 'meter_id', 'restricted' => true],
        ]);
    }

    private function submitRequest(int $versionId, array $fields, string $purpose = '建筑节能研究用途说明'): array
    {
        return [
            'dataset_version_id' => $versionId,
            'purpose' => $purpose,
            'requested_fields' => $fields,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
        ];
    }

    public function test_full_apply_approve_flow_creates_grant_snapshot(): void
    {
        $this->createUsers();
        $version = $this->makeVersion();

        $created = $this->actingAsUser($this->researcher)->postJson('/api/requests',
            $this->submitRequest($version->id, ['building', 'meter_id']));
        $created->assertCreated()->assertJsonPath('status', 'pending');

        $requestId = $created->json('id');

        $approved = $this->actingAsUser($this->admin)->postJson("/api/admin/requests/{$requestId}/approve");
        $approved->assertOk()->assertJsonPath('status', 'approved');

        $grant = AccessGrant::sole();
        $this->assertSame(['building', 'meter_id'], $grant->granted_fields);
        $this->assertSame($version->id, $grant->dataset_version_id);
        $this->assertSame('2026-01-01', $grant->period_start->toDateString());
        $this->assertSame('2026-12-31', $grant->period_end->toDateString());
    }

    public function test_request_with_unknown_fields_is_rejected(): void
    {
        $this->createUsers();
        $version = $this->makeVersion();

        $this->actingAsUser($this->researcher)->postJson('/api/requests',
            $this->submitRequest($version->id, ['building', 'no_such_field']))
            ->assertUnprocessable();

        $this->assertSame(0, AccessRequest::count());
    }

    public function test_admin_may_narrow_but_never_widen_requested_fields(): void
    {
        $this->createUsers();
        $version = $this->makeVersion();

        $created = $this->actingAsUser($this->researcher)->postJson('/api/requests',
            $this->submitRequest($version->id, ['building', 'meter_id']));
        $requestId = $created->json('id');

        // Narrowing is allowed.
        $this->actingAsUser($this->admin)->postJson("/api/admin/requests/{$requestId}/approve",
            ['granted_fields' => ['building']])->assertOk();
        $this->assertSame(['building'], AccessGrant::sole()->granted_fields);

        // Widening beyond the application is not.
        $second = $this->actingAsUser($this->researcher)->postJson('/api/requests',
            $this->submitRequest($version->id, ['building']));
        $this->actingAsUser($this->admin)->postJson("/api/admin/requests/{$second->json('id')}/approve",
            ['granted_fields' => ['building', 'meter_id']])->assertUnprocessable();
    }

    public function test_researcher_cannot_approve_requests(): void
    {
        $this->createUsers();
        $version = $this->makeVersion();

        $created = $this->actingAsUser($this->researcher)->postJson('/api/requests',
            $this->submitRequest($version->id, ['building']));

        $this->actingAsUser($this->researcher)
            ->postJson("/api/admin/requests/{$created->json('id')}/approve")
            ->assertForbidden();
    }

    public function test_rejected_request_is_final_and_new_purpose_needs_new_request(): void
    {
        $this->createUsers();
        $version = $this->makeVersion();

        $created = $this->actingAsUser($this->researcher)->postJson('/api/requests',
            $this->submitRequest($version->id, ['meter_id'], '原始用途说明文字内容'));
        $requestId = $created->json('id');

        $this->actingAsUser($this->admin)->postJson("/api/admin/requests/{$requestId}/reject",
            ['reason' => '用途说明不充分'])->assertOk();

        // A rejected request cannot be flipped to approved afterwards.
        $this->actingAsUser($this->admin)->postJson("/api/admin/requests/{$requestId}/approve")
            ->assertUnprocessable();
        $this->actingAsUser($this->admin)->postJson("/api/admin/requests/{$requestId}/reject",
            ['reason' => '重复操作'])->assertUnprocessable();

        $this->assertSame('rejected', AccessRequest::find($requestId)->status);
        $this->assertSame(0, AccessGrant::count());

        // Changing the purpose means filing a brand-new application.
        $renewed = $this->actingAsUser($this->researcher)->postJson('/api/requests',
            $this->submitRequest($version->id, ['meter_id'], '修改后的新用途说明内容'));
        $renewed->assertCreated()->assertJsonPath('status', 'pending');
        $this->assertSame(2, AccessRequest::count());
    }

    public function test_pending_request_cannot_be_downloaded_before_approval(): void
    {
        $this->createUsers();
        $version = $this->makeVersion();

        $this->actingAsUser($this->researcher)->postJson('/api/requests',
            $this->submitRequest($version->id, ['building']));

        $grants = $this->actingAsUser($this->researcher)->getJson('/api/grants');
        $grants->assertOk();
        $this->assertSame([], $grants->json());
    }
}
