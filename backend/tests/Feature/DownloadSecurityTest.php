<?php

namespace Tests\Feature;

use App\Models\AccessGrant;
use App\Models\AccessRequest;
use App\Models\DownloadToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DownloadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function grantFor(array $fields, string $periodEnd = '2026-12-31'): AccessGrant
    {
        $this->createUsers();
        $version = $this->createDatasetVersion(
            fields: [
                ['name' => 'building'],
                ['name' => 'energy_kwh'],
                ['name' => 'meter_id', 'restricted' => true],
            ],
            rows: [
                ['理科楼', '812.5', 'M-1001'],
                ['文科楼', '640.2', 'M-1002'],
            ],
        );

        $request = AccessRequest::create([
            'user_id' => $this->researcher->id,
            'dataset_version_id' => $version->id,
            'purpose' => '测试用途说明文字内容',
            'requested_fields' => $fields,
            'period_start' => '2026-01-01',
            'period_end' => $periodEnd,
            'status' => 'approved',
        ]);

        return AccessGrant::create([
            'access_request_id' => $request->id,
            'user_id' => $this->researcher->id,
            'dataset_version_id' => $version->id,
            'granted_fields' => $fields,
            'period_start' => '2026-01-01',
            'period_end' => $periodEnd,
        ]);
    }

    private function issueLink(AccessGrant $grant): string
    {
        $response = $this->actingAsUser($this->researcher)
            ->postJson("/api/grants/{$grant->id}/download-links");
        $response->assertCreated();

        return $response->json('url');
    }

    public function test_download_returns_exactly_the_granted_fields(): void
    {
        $grant = $this->grantFor(['building', 'energy_kwh']);
        $url = $this->issueLink($grant);

        $response = $this->get($url);
        $response->assertOk();
        $response->assertHeader('X-Dataset-Version', '1.0');

        $csv = $response->getContent();
        $this->assertStringContainsString("building,energy_kwh\n", $csv);
        $this->assertStringContainsString('812.5', $csv);
        // The restricted column must not leak even though it sits in the same file.
        $this->assertStringNotContainsString('meter_id', $csv);
        $this->assertStringNotContainsString('M-1001', $csv);
    }

    public function test_modified_download_params_cannot_fetch_ungranted_fields(): void
    {
        $grant = $this->grantFor(['building', 'energy_kwh']);
        $url = $this->issueLink($grant);

        // Tampering with the fields parameter to grab a restricted column.
        $this->get($url.'?fields=building,meter_id')->assertForbidden();
        $this->get($url.'?fields=meter_id')->assertForbidden();
        $this->get($url.'?fields[]=meter_id')->assertForbidden();
        $this->get($url.'?fields[0]=building&fields[1]=meter_id')->assertForbidden();

        // Narrowing to a subset of the grant is fine.
        $subset = $this->get($url.'?fields=energy_kwh');
        $subset->assertOk();
        $this->assertStringContainsString("energy_kwh\n", $subset->getContent());
        $this->assertStringNotContainsString('building', $subset->getContent());
    }

    public function test_version_parameters_cannot_switch_the_downloaded_version(): void
    {
        $grant = $this->grantFor(['building']);
        $url = $this->issueLink($grant);

        // Publish version 2.0 of the same dataset with an extra column.
        $v2 = $this->createDatasetVersion(
            fields: [
                ['name' => 'building'],
                ['name' => 'meter_id', 'restricted' => true],
            ],
            version: '2.0',
            rows: [['理科楼', 'M-9999']],
            dataset: $grant->version->dataset,
        );

        foreach (["?version=2.0", "?dataset_version_id={$v2->id}", "?version_id={$v2->id}"] as $suffix) {
            $response = $this->get($url.$suffix);
            $response->assertOk();
            $response->assertHeader('X-Dataset-Version', '1.0');
            $this->assertStringNotContainsString('M-9999', $response->getContent());
        }
    }

    public function test_dataset_update_does_not_expand_existing_grant(): void
    {
        $grant = $this->grantFor(['building']);
        $url = $this->issueLink($grant);

        // Dataset gets updated: new version with a brand-new restricted field.
        $this->createDatasetVersion(
            fields: [
                ['name' => 'building'],
                ['name' => 'room_number', 'restricted' => true],
            ],
            version: '2.0',
            rows: [['理科楼', 'A-301']],
            dataset: $grant->version->dataset,
        );

        // The old grant still points at version 1.0 and its original fields.
        $this->assertSame('1.0', $grant->fresh()->version->version);

        $response = $this->get($url);
        $response->assertOk();
        $response->assertHeader('X-Dataset-Version', '1.0');
        $this->assertStringNotContainsString('room_number', $response->getContent());
        $this->assertStringNotContainsString('A-301', $response->getContent());

        // Asking the old link for a field that only exists in v2.0 fails.
        $this->get($url.'?fields=room_number')->assertForbidden();
    }

    public function test_revoking_grant_immediately_kills_download_links(): void
    {
        $grant = $this->grantFor(['building']);
        $url = $this->issueLink($grant);
        $this->get($url)->assertOk(); // link works before revocation

        $this->actingAsUser($this->admin)->postJson("/api/admin/grants/{$grant->id}/revoke",
            ['reason' => '数据使用违规'])->assertOk();

        // Old link is dead.
        $this->get($url)->assertNotFound();

        // And no new link can be minted from a revoked grant.
        $this->actingAsUser($this->researcher)
            ->postJson("/api/grants/{$grant->id}/download-links")
            ->assertUnprocessable();
        $this->assertSame(0, DownloadToken::count());
    }

    public function test_expired_grant_invalidates_download_links(): void
    {
        // Grant whose usage period already ended.
        $grant = $this->grantFor(['building'], periodEnd: '2020-01-01');

        // No new link for an expired grant.
        $this->actingAsUser($this->researcher)
            ->postJson("/api/grants/{$grant->id}/download-links")
            ->assertUnprocessable();

        // A link issued while valid dies as soon as the period ends.
        $live = $this->grantFor(['building']);
        $url = $this->issueLink($live);
        $this->get($url)->assertOk();

        $live->update(['period_end' => '2020-01-01']);
        $this->get($url)->assertGone();
    }

    public function test_unknown_token_is_rejected(): void
    {
        $this->grantFor(['building']);
        $this->get('/api/download/not-a-real-token')->assertNotFound();
    }

    public function test_other_users_grant_cannot_be_used_to_mint_links(): void
    {
        $grant = $this->grantFor(['building']);
        $other = \App\Models\User::factory()->create(['role' => 'researcher']);

        $this->actingAsUser($other)
            ->postJson("/api/grants/{$grant->id}/download-links")
            ->assertForbidden();
    }
}
