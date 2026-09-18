<?php

namespace Tests\Feature;

use App\Enums\CatalogScope;
use App\Models\AnalysisPackage;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogScopeIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_list_aqua_codes_are_aqua_scoped(): void
    {
        $this->seed();

        $this->assertDatabaseHas('analysis_types', [
            'code' => 'AQ-W-01',
            'catalog_scope' => CatalogScope::Aqua->value,
            'default_price' => 300,
        ]);
        $this->assertDatabaseHas('analysis_types', [
            'code' => 'WW-02',
            'catalog_scope' => CatalogScope::NonAqua->value,
            'default_price' => 1500,
        ]);
        $this->assertDatabaseHas('analysis_types', [
            'code' => 'FD-CAP',
            'catalog_scope' => CatalogScope::Both->value,
            'default_price' => 5000,
        ]);
    }

    public function test_aqua_packages_are_hidden_from_non_aqua_and_vice_versa(): void
    {
        $this->seed();

        $aquaWater = AnalysisPackage::query()->where('code', 'PKG-AQUA-WATER')->firstOrFail();
        $dw = AnalysisPackage::query()->where('code', 'PKG-DW-PHYSICO')->firstOrFail();
        $cap = AnalysisPackage::query()->where('code', 'PKG-CAP')->firstOrFail();

        $this->assertTrue($aquaWater->visibleForAqua(true));
        $this->assertFalse($aquaWater->visibleForAqua(false));

        $this->assertFalse($dw->visibleForAqua(true));
        $this->assertTrue($dw->visibleForAqua(false));

        $this->assertTrue($cap->visibleForAqua(true));
        $this->assertTrue($cap->visibleForAqua(false));
    }

    public function test_chemicals_are_not_in_catalog_definitions(): void
    {
        $names = collect(OfficialAnalysisCatalog::definitions())
            ->flatten(1)
            ->pluck(1)
            ->all();

        $this->assertNotContains('0.01 N EDTA', $names);
        $this->assertContains('Ammonia-Nitrogen', $names);
    }
}
