<?php

namespace Database\Seeders;

use App\Models\Dataset;
use App\Models\DatasetField;
use App\Models\DatasetVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: safe to run on every `start.sh` invocation.
        User::updateOrCreate(
            ['email' => 'admin@school.edu'],
            ['name' => '数据管理员', 'password' => 'admin123', 'role' => User::ROLE_ADMIN],
        );

        User::updateOrCreate(
            ['email' => 'researcher@school.edu'],
            ['name' => '王研究员', 'password' => 'research123', 'role' => User::ROLE_RESEARCHER],
        );

        User::updateOrCreate(
            ['email' => 'li@school.edu'],
            ['name' => '李研究员', 'password' => 'research123', 'role' => User::ROLE_RESEARCHER],
        );

        $this->seedDataset(
            name: '校园能耗监测数据集',
            code: 'campus-energy',
            description: '覆盖全校教学楼的分时能耗读数，可用于建筑节能与用电行为研究。受限字段涉及具体表计与房间，需申请审批后使用。',
            version: '1.0',
            changelog: '首次发布',
            fields: [
                ['name' => 'record_date', 'label' => '记录日期', 'restricted' => false, 'description' => '读数日期 YYYY-MM-DD'],
                ['name' => 'building', 'label' => '楼栋', 'restricted' => false, 'description' => '教学楼名称'],
                ['name' => 'energy_kwh', 'label' => '能耗(千瓦时)', 'restricted' => false, 'description' => '当日能耗读数'],
                ['name' => 'meter_id', 'label' => '电表编号', 'restricted' => true, 'description' => '具体表计资产编号（受限）'],
                ['name' => 'room_number', 'label' => '房间号', 'restricted' => true, 'description' => '表计所在房间（受限）'],
            ],
            rows: [
                ['2026-01-05', '理科楼', '812.5', 'M-1001', 'A-301'],
                ['2026-01-05', '文科楼', '640.2', 'M-1002', 'B-112'],
                ['2026-01-06', '理科楼', '798.1', 'M-1001', 'A-301'],
                ['2026-01-06', '实验楼', '1024.7', 'M-1003', 'C-208'],
                ['2026-01-07', '文科楼', '655.9', 'M-1002', 'B-112'],
                ['2026-01-07', '图书馆', '430.0', 'M-1004', 'D-101'],
                ['2026-01-08', '实验楼', '1011.3', 'M-1003', 'C-208'],
                ['2026-01-08', '图书馆', '428.4', 'M-1004', 'D-101'],
            ],
        );

        $this->seedDataset(
            name: '图书馆借阅统计数据集',
            code: 'library-loans',
            description: '按月份与图书分类汇总的借阅量。受限字段包含读者标识信息，仅供审批后的学术研究使用。',
            version: '2.1',
            changelog: '补充 2025 全年数据',
            fields: [
                ['name' => 'month', 'label' => '月份', 'restricted' => false, 'description' => '统计月份 YYYY-MM'],
                ['name' => 'category', 'label' => '图书分类', 'restricted' => false, 'description' => '中图法一级分类'],
                ['name' => 'loan_count', 'label' => '借阅量', 'restricted' => false, 'description' => '当月借阅册次'],
                ['name' => 'reader_id', 'label' => '读者证号', 'restricted' => true, 'description' => '读者证件编号（受限）'],
                ['name' => 'id_card', 'label' => '身份证号', 'restricted' => true, 'description' => '读者身份证号（受限）'],
            ],
            rows: [
                ['2025-09', 'TP 计算机', '1320', 'R-88001', '1101**********21'],
                ['2025-09', 'I 文学', '2210', 'R-88002', '1101**********45'],
                ['2025-10', 'TP 计算机', '1480', 'R-88001', '1101**********21'],
                ['2025-10', 'O 数理科学', '960', 'R-88003', '3101**********77'],
                ['2025-11', 'I 文学', '1980', 'R-88002', '1101**********45'],
                ['2025-11', 'F 经济', '870', 'R-88004', '4401**********09'],
            ],
        );
    }

    private function seedDataset(
        string $name,
        string $code,
        string $description,
        string $version,
        string $changelog,
        array $fields,
        array $rows,
    ): void {
        $dataset = Dataset::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'description' => $description],
        );

        // Never touch an existing version: grants may already point at it.
        if (DatasetVersion::where('dataset_id', $dataset->id)->where('version', $version)->exists()) {
            return;
        }

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
            'changelog' => $changelog,
        ]);

        foreach ($fields as $i => $field) {
            DatasetField::create([
                'dataset_version_id' => $versionModel->id,
                'name' => $field['name'],
                'label' => $field['label'],
                'data_type' => 'string',
                'is_restricted' => $field['restricted'],
                'description' => $field['description'],
                'sort_order' => $i,
            ]);
        }
    }
}
