<?php

use App\Enums\AnalysisCategory as AnalysisCategoryEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $existing = DB::table('analysis_categories')->pluck('slug')->all();
        $sort = (int) DB::table('analysis_categories')->max('sort_order');

        foreach (AnalysisCategoryEnum::cases() as $case) {
            if (in_array($case->value, $existing, true)) {
                continue;
            }

            DB::table('analysis_categories')->insert([
                'slug' => $case->value,
                'name' => $case->label(),
                'sort_order' => ++$sort,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('analysis_categories')
            ->whereIn('slug', [
                'pcr',
                'test_kits',
                'proximate',
                'phytochemical',
                'food_special',
            ])
            ->delete();
    }
};
