<?php

namespace App\Support;

use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * {Result|JO}_{TYPE}_{LSO}_{CONTROL}_{CustomerName}_{mm-dd-yy}[_{FORM}].pdf
 */
class ResultPdfFilename
{
    private const TYPE_BY_CATEGORY = [
        'drinking_water' => 'DW',
        'waste_water' => 'WW',
        'food_products_microbiological' => 'FOOD',
        'proximate_analysis' => 'FOOD',
        'nutrifacts' => 'FOOD',
        'other_food_analysis' => 'FOOD',
        'soil_analysis_aquaculture' => 'AQUA',
        'adult_fry_prawn_aquaculture' => 'AQUA',
        'water_analysis_aquaculture' => 'AQUA',
    ];

    /**
     * @param  Collection<int, JobOrderAnalysis>  $analyses
     */
    public static function for(
        JobOrder $jobOrder,
        Collection $analyses,
        ?string $controlNumber,
        ?string $formCode = null,
        bool $withFormCode = false,
        string $prefix = 'Result',
    ): string {
        $parts = [
            $prefix,
            self::typeCode(self::categorySlugs($analyses), $formCode),
            (string) $jobOrder->reference_no,
            self::controlPart($controlNumber ?? (string) $jobOrder->reference_no),
            self::customerPart($jobOrder->customer_name),
            $jobOrder->created_at?->format('m-d-y') ?? now()->format('m-d-y'),
        ];

        if ($withFormCode && ($suffix = self::formSuffix($formCode)) !== '') {
            $parts[] = $suffix;
        }

        return implode('_', array_filter($parts, fn (string $part): bool => $part !== '')).'.pdf';
    }

    /**
     * Job Order (RFA) form: JO_{TYPE}_{LSO}_{CONTROL}_{CustomerName}_{mm-dd-yy}.pdf
     */
    public static function forJobOrder(JobOrder $jobOrder): string
    {
        $jobOrder->loadMissing('analyses.analysisType.category');

        return self::for($jobOrder, $jobOrder->analyses, null, prefix: 'JO');
    }

    /**
     * @param  list<string>  $categorySlugs
     */
    public static function typeCode(array $categorySlugs, ?string $formCode = null): string
    {
        foreach ($categorySlugs as $slug) {
            if (isset(self::TYPE_BY_CATEGORY[$slug])) {
                return self::TYPE_BY_CATEGORY[$slug];
            }
        }

        if (in_array('microbiological', $categorySlugs, true) && self::formSuffix($formCode) === 'FO5') {
            return 'DW';
        }

        return 'OTH';
    }

    public static function controlPart(string $controlNumber): string
    {
        return (string) preg_replace('/^\d{2}-/', '', trim($controlNumber));
    }

    public static function customerPart(?string $customerName): string
    {
        $clean = (string) preg_replace('/[^A-Za-z0-9]/', '', Str::ascii((string) $customerName));

        return $clean !== '' ? $clean : 'Customer';
    }

    public static function formSuffix(?string $formCode): string
    {
        $code = (string) preg_replace('/^LSP-7\.8-/i', '', trim((string) $formCode));

        return (string) preg_replace('/[^A-Za-z0-9-]/', '', $code);
    }

    /**
     * @param  Collection<int, JobOrderAnalysis>  $analyses
     * @return list<string>
     */
    private static function categorySlugs(Collection $analyses): array
    {
        return $analyses
            ->map(function ($line): ?string {
                if (! $line instanceof JobOrderAnalysis) {
                    return null;
                }
                $line->loadMissing('analysisType.category');

                return $line->analysisType?->category?->slug;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
