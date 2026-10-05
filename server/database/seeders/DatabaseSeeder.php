<?php

namespace Database\Seeders;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $approver = User::create([
            'name' => '王审批',
            'email' => 'approver@example.edu',
            'password' => 'password',
            'role' => User::ROLE_APPROVER,
        ]);

        User::create([
            'name' => '李研究',
            'email' => 'researcher@example.edu',
            'password' => 'password',
            'role' => User::ROLE_RESEARCHER,
        ]);

        User::create([
            'name' => '张学术',
            'email' => 'researcher2@example.edu',
            'password' => 'password',
            'role' => User::ROLE_RESEARCHER,
        ]);

        // --- Dataset 1: 学生体质健康调查 (has restricted identity/health fields) ---
        $health = Dataset::create([
            'title' => '学生体质健康调查',
            'description' => '2024-2025 学年全校学生体质健康抽样调查，含体能测试与问卷结果。',
            'department' => '体育部',
            'created_by' => $approver->id,
        ]);

        $healthFields = [
            ['name' => 'age_group', 'label' => '年龄段', 'restricted' => false],
            ['name' => 'grade', 'label' => '年级', 'restricted' => false],
            ['name' => 'bmi', 'label' => 'BMI', 'restricted' => false],
            ['name' => 'sport_hours', 'label' => '周运动时长', 'restricted' => false],
            ['name' => 'student_id', 'label' => '学号', 'restricted' => true],
            ['name' => 'health_condition', 'label' => '既往病史', 'restricted' => true],
        ];
        foreach (array_values($healthFields) as $i => $f) {
            $health->fields()->create([
                'name' => $f['name'],
                'label' => $f['label'],
                'description' => null,
                'is_restricted' => $f['restricted'],
                'position' => $i,
            ]);
        }

        $this->putCsv($health, 1, <<<'CSV'
        age_group,grade,bmi,sport_hours,student_id,health_condition
        18-20,大一,21.3,4,S2024001,无
        18-20,大一,22.8,2,S2024002,哮喘
        21-23,大三,20.1,6,S2022055,无
        21-23,大三,24.5,1,S2022078,高血压
        24+,研一,23.0,3,S2023010,无
        CSV);
        $this->putCsv($health, 2, <<<'CSV'
        age_group,grade,bmi,sport_hours,student_id,health_condition
        18-20,大一,21.5,5,S2024001,无
        18-20,大一,22.4,3,S2024002,哮喘
        21-23,大三,20.4,7,S2022055,无
        21-23,大三,24.1,2,S2022078,高血压
        24+,研一,22.7,4,S2023010,无
        24+,研二,25.2,1,S2022021,糖尿病
        CSV, '补充研二样本，修正个别 BMI 录入错误');

        // --- Dataset 2: 校园能耗监测 ---
        $energy = Dataset::create([
            'title' => '校园能耗月度监测',
            'description' => '各楼宇逐月电耗与水耗监测数据。',
            'department' => '后勤管理处',
            'created_by' => $approver->id,
        ]);

        $energyFields = [
            ['name' => 'building', 'label' => '楼宇', 'restricted' => false],
            ['name' => 'month', 'label' => '月份', 'restricted' => false],
            ['name' => 'electricity_kwh', 'label' => '电耗(kWh)', 'restricted' => false],
            ['name' => 'water_ton', 'label' => '水耗(吨)', 'restricted' => false],
            ['name' => 'meter_id', 'label' => '表计编号', 'restricted' => true],
            ['name' => 'room_number', 'label' => '房间号', 'restricted' => true],
        ];
        foreach (array_values($energyFields) as $i => $f) {
            $energy->fields()->create([
                'name' => $f['name'],
                'label' => $f['label'],
                'description' => null,
                'is_restricted' => $f['restricted'],
                'position' => $i,
            ]);
        }

        $this->putCsv($energy, 1, <<<'CSV'
        building,month,electricity_kwh,water_ton,meter_id,room_number
        图书馆,2025-01,18200,640,M-1101,B101
        图书馆,2025-02,16900,590,M-1101,B101
        实验楼,2025-01,32100,1120,M-2201,A305
        实验楼,2025-02,30400,1050,M-2201,A305
        体育馆,2025-01,12400,830,M-3301,C201
        CSV);
    }

    private function putCsv(Dataset $dataset, int $version, string $csv, ?string $changelog = null): void
    {
        // heredoc indentation leaves leading spaces — normalise each line
        $csv = implode("\n", array_map('trim', explode("\n", trim($csv))))."\n";

        $path = "datasets/{$dataset->id}/v{$version}.csv";
        Storage::disk('local')->put($path, $csv);

        $dataset->versions()->create([
            'version' => $version,
            'file_path' => $path,
            'changelog' => $changelog ?? '初始版本',
        ]);
    }
}
