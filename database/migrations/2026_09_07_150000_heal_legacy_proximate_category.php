<?php

use App\Enums\AnalysisCategory as AnalysisCategoryEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy slugs that predate the price-list enum rename
     * (e.g. proximate → proximate_analysis).
     *
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

            if ($legacy === null) {
                continue;
            }

            if ($canonical === null) {
                DB::table('analysis_categories')
                    ->where('id', $legacy->id)
                    ->update([
                        'slug' => $canonicalSlug,
                        'name' => AnalysisCategoryEnum::tryFrom($canonicalSlug)?->label() ?? $legacy->name,
                        'is_active' => true,
                        'updated_at' => $now,
                    ]);

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

        // Remove any other non-enum category rows (retired duplicates).
        $enumSlugs = array_map(
            fn (AnalysisCategoryEnum $case) => $case->value,
            AnalysisCategoryEnum::cases(),
        );

        $otherFallback = DB::table('analysis_categories')->where('slug', 'other')->value('id');

        $retired = DB::table('analysis_categories')
            ->whereNotIn('slug', $enumSlugs)
            ->get(['id']);

        foreach ($retired as $row) {
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
        // Intentionally empty: deleted duplicate categories are not restored.
    }
};
