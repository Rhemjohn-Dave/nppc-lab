<?php

use App\Enums\AnalysisCategory as AnalysisCategoryEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up for environments that already ran the prior heal which only
 * deactivated legacy duplicate categories (e.g. proximate). Delete them.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $legacyToCanonical = [
        'proximate' => 'proximate_analysis',
        'food_special' => 'other_food_analysis',
    ];

    public function up(): void
    {
        $now = now();
        $hasPackageCategory = Schema::hasColumn('analysis_packages', 'category_id');

        foreach ($this->legacyToCanonical as $legacySlug => $canonicalSlug) {
            $legacy = DB::table('analysis_categories')->where('slug', $legacySlug)->first();
            $canonical = DB::table('analysis_categories')->where('slug', $canonicalSlug)->first();

            if ($legacy === null || $canonical === null) {
                continue;
            }

            DB::table('analysis_types')
                ->where('category_id', $legacy->id)
                ->update([
                    'category_id' => $canonical->id,
                    'updated_at' => $now,
                ]);

            if ($hasPackageCategory) {
                DB::table('analysis_packages')
                    ->where('category_id', $legacy->id)
                    ->update([
                        'category_id' => $canonical->id,
                        'updated_at' => $now,
                    ]);
            }

            DB::table('analysis_categories')->where('id', $legacy->id)->delete();
        }

        $enumSlugs = array_map(
            fn (AnalysisCategoryEnum $case) => $case->value,
            AnalysisCategoryEnum::cases(),
        );

        $otherFallback = DB::table('analysis_categories')->where('slug', 'other')->value('id');

        foreach (DB::table('analysis_categories')->whereNotIn('slug', $enumSlugs)->get(['id']) as $row) {
            if ($otherFallback) {
                DB::table('analysis_types')
                    ->where('category_id', $row->id)
                    ->update([
                        'category_id' => $otherFallback,
                        'updated_at' => $now,
                    ]);

                if ($hasPackageCategory) {
                    DB::table('analysis_packages')
                        ->where('category_id', $row->id)
                        ->update([
                            'category_id' => $otherFallback,
                            'updated_at' => $now,
                        ]);
                }
            }

            DB::table('analysis_categories')->where('id', $row->id)->delete();
        }
    }

    public function down(): void
    {
        //
    }
};
