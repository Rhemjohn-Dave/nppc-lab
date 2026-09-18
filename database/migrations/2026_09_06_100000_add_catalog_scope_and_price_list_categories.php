<?php

use App\Enums\AnalysisCategory as AnalysisCategoryEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_types', function (Blueprint $table) {
            $table->string('catalog_scope', 20)->default('non_aqua')->after('result_mode')->index();
        });

        $now = now();
        $existing = DB::table('analysis_categories')->pluck('slug')->all();
        $sort = (int) DB::table('analysis_categories')->max('sort_order');

        foreach (AnalysisCategoryEnum::cases() as $case) {
            if (in_array($case->value, $existing, true)) {
                DB::table('analysis_categories')
                    ->where('slug', $case->value)
                    ->update([
                        'name' => $case->label(),
                        'is_active' => true,
                        'updated_at' => $now,
                    ]);

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

        // Retire categories no longer in the price-list enum.
        DB::table('analysis_categories')
            ->whereNotIn('slug', array_map(
                fn (AnalysisCategoryEnum $case) => $case->value,
                AnalysisCategoryEnum::cases(),
            ))
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        Schema::table('analysis_types', function (Blueprint $table) {
            $table->dropColumn('catalog_scope');
        });
    }
};
