<?php

namespace Tests;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected User $admin;

    protected User $researcher;

    private bool $storageFaked = false;

    /**
     * Create the two standard accounts used across the suite.
     */
    protected function createUsers(): void
    {
        if (isset($this->admin)) {
            return;
        }

        $this->admin = User::factory()->create([
            'email' => 'admin@school.edu',
            'role' => User::ROLE_ADMIN,
        ]);
        $this->researcher = User::factory()->create([
            'email' => 'researcher@school.edu',
            'role' => User::ROLE_RESEARCHER,
        ]);
    }

    /**
     * Create a dataset with one CSV-backed version.
     *
     * @param  array<int, array{name: string, restricted?: bool}>  $fields
     * @param  array<int, array<int, string>>  $rows
     */
    protected function createDatasetVersion(
        array $fields,
        string $version = '1.0',
        array $rows = [],
        ?Dataset $dataset = null,
    ): DatasetVersion {
        if (! $this->storageFaked) {
            Storage::fake('local');
            $this->storageFaked = true;
        }

        $dataset ??= Dataset::create([
            'name' => '测试数据集',
            'code' => 'test-'.uniqid(),
            'description' => '测试用',
        ]);

        $header = array_column($fields, 'name');
        $csv = implode(',', $header)."\n";
        foreach ($rows as $row) {
            $csv .= implode(',', $row)."\n";
        }
        $path = "datasets/{$dataset->id}/v{$version}.csv";
        Storage::put($path, $csv);

        $versionModel = DatasetVersion::create([
            'dataset_id' => $dataset->id,
            'version' => $version,
            'file_path' => $path,
        ]);

        foreach ($fields as $i => $field) {
            DatasetField::create([
                'dataset_version_id' => $versionModel->id,
                'name' => $field['name'],
                'label' => $field['name'],
                'data_type' => 'string',
                'is_restricted' => $field['restricted'] ?? false,
                'sort_order' => $i,
            ]);
        }

        return $versionModel->fresh('fields');
    }

    /**
     * Authenticate subsequent requests as the given user.
     *
     * Uses Sanctum::actingAs rather than raw Bearer tokens because the
     * sanctum guard caches its user across requests within one test, which
     * would leak the first user into later requests.
     */
    protected function actingAsUser(User $user): static
    {
        \Laravel\Sanctum\Sanctum::actingAs($user);

        return $this;
    }
}
