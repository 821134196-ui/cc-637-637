<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_never_exposes_restricted_fields(): void
    {
        $this->createDatasetVersion(
            fields: [
                ['name' => 'building'],
                ['name' => 'energy_kwh'],
                ['name' => 'meter_id', 'restricted' => true],
                ['name' => 'room_number', 'restricted' => true],
            ],
            rows: [['理科楼', '812.5', 'M-1001', 'A-301']],
        );

        $response = $this->getJson('/api/datasets');
        $response->assertOk();

        $raw = $response->getContent();
        $this->assertStringNotContainsString('meter_id', $raw);
        $this->assertStringNotContainsString('room_number', $raw);
        $this->assertStringNotContainsString('M-1001', $raw);
        $this->assertStringNotContainsString('A-301', $raw);

        $dataset = $response->json('0');
        $this->assertSame(2, $dataset['restricted_field_count']);
        $this->assertSame(['building', 'energy_kwh'], array_column($dataset['public_fields'], 'name'));
    }

    public function test_dataset_detail_hides_restricted_fields_for_every_version(): void
    {
        $version = $this->createDatasetVersion(
            fields: [
                ['name' => 'building'],
                ['name' => 'meter_id', 'restricted' => true],
            ],
        );

        $response = $this->getJson("/api/datasets/{$version->dataset_id}");
        $response->assertOk();

        $raw = $response->getContent();
        $this->assertStringNotContainsString('meter_id', $raw);
        $this->assertSame(1, $response->json('versions.0.restricted_field_count'));
    }

    public function test_preview_strips_restricted_columns_and_values(): void
    {
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

        $response = $this->getJson("/api/datasets/{$version->dataset_id}/preview");
        $response->assertOk();

        $this->assertSame(['building', 'energy_kwh'], $response->json('columns'));

        $raw = $response->getContent();
        $this->assertStringNotContainsString('meter_id', $raw);
        $this->assertStringNotContainsString('M-1001', $raw);
        $this->assertStringNotContainsString('M-1002', $raw);
        $this->assertStringContainsString('812.5', $raw);
    }
}
