<?php

namespace Tests\Feature;

use App\Enums\ControlledFormCategory;
use App\Enums\JobOrderStatus;
use App\Enums\JobOrderVariant;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DualJobOrderFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_resolution_prefers_aqua_unless_water_override(): void
    {
        $this->assertSame(
            JobOrderVariant::Aqua,
            OfficialAnalysisCatalog::variantForClassification('Aqua'),
        );
        $this->assertSame(
            JobOrderVariant::General,
            OfficialAnalysisCatalog::variantForClassification('Potability'),
        );
        $this->assertSame(
            JobOrderVariant::General,
            OfficialAnalysisCatalog::variantForClassification('Aqua / Potability'),
        );
        $this->assertSame(
            JobOrderVariant::General,
            OfficialAnalysisCatalog::variantForClassification(null),
        );
    }

    public function test_document_control_matches_official_revisions(): void
    {
        $general = OfficialAnalysisCatalog::documentControl(JobOrderVariant::General);
        $this->assertSame('LSP 7.1 FO1', $general['form']);
        $this->assertSame('10/Issue 11', $general['revision']);

        $aqua = OfficialAnalysisCatalog::documentControl(JobOrderVariant::Aqua);
        $this->assertSame('LSP 7.1 FO4', $aqua['form']);
        $this->assertSame('02/Issue 03', $aqua['revision']);
    }

    public function test_job_order_form_resolver_selects_by_classification(): void
    {
        $general = ControlledForm::query()->create([
            'form_code' => ControlledForm::RFA_FORM_CODE,
            'name' => 'General RFA',
            'category' => ControlledFormCategory::JobOrder,
            'job_order_variant' => JobOrderVariant::General,
        ]);
        $aqua = ControlledForm::query()->create([
            'form_code' => ControlledForm::RFA_AQUA_FORM_CODE,
            'name' => 'Aqua RFA',
            'category' => ControlledFormCategory::JobOrder,
            'job_order_variant' => JobOrderVariant::Aqua,
        ]);

        $aquaJob = JobOrder::query()->create([
            'reference_no' => '26-AQUA1',
            'customer_name' => 'Aqua Client',
            'classification' => 'Aqua',
            'status' => JobOrderStatus::DraftSubmitted,
            'total_cost' => 0,
        ]);
        $waterJob = JobOrder::query()->create([
            'reference_no' => '26-POT01',
            'customer_name' => 'Water Client',
            'classification' => 'Potability',
            'status' => JobOrderStatus::DraftSubmitted,
            'total_cost' => 0,
        ]);

        $this->assertTrue(
            ControlledForm::jobOrderFormFor($aquaJob)?->is($aqua),
        );
        $this->assertTrue(
            ControlledForm::jobOrderFormFor($waterJob)?->is($general),
        );
        $this->assertTrue(
            ControlledForm::jobOrderForm()?->is($general),
        );
    }

    public function test_aqua_does_not_silently_fall_back_to_general(): void
    {
        ControlledForm::query()->create([
            'form_code' => ControlledForm::RFA_FORM_CODE,
            'name' => 'General RFA',
            'category' => ControlledFormCategory::JobOrder,
            'job_order_variant' => JobOrderVariant::General,
        ]);

        $aquaJob = JobOrder::query()->create([
            'reference_no' => '26-AQUA2',
            'customer_name' => 'Aqua Client',
            'classification' => 'Aqua',
            'status' => JobOrderStatus::DraftSubmitted,
            'total_cost' => 0,
        ]);

        $this->assertNull(ControlledForm::jobOrderFormFor($aquaJob));
    }

    public function test_result_form_registry_uses_distinct_f016_codes(): void
    {
        $codes = collect(OfficialAnalysisCatalog::resultFormRegistry())
            ->pluck('form_code')
            ->all();

        $this->assertSame(count($codes), count(array_unique($codes)));
        $this->assertContains('LSP-7.8-F016-PROX', $codes);
        $this->assertContains('LSP-7.8-FO4', $codes);
        $this->assertContains('LSP-7.8-FO5', $codes);
        $this->assertContains('LSP-7.8-FO26', $codes);
        $this->assertContains('LSP-7.8-FO27', $codes);
    }

    public function test_catalog_includes_aqua_and_food_codes(): void
    {
        $flat = collect(OfficialAnalysisCatalog::definitions())->flatten(1)->pluck(0);

        foreach (['AQ-W-01', 'PCR-01', 'PX-01', 'WA-01', 'FD-CAP', 'PHY-01', 'MK-01', 'MT-01', 'FM-01'] as $code) {
            $this->assertTrue($flat->contains($code), "Missing catalog code {$code}");
        }
    }

    public function test_seeder_creates_dual_job_order_shells_and_packages(): void
    {
        $this->seed();

        $this->assertDatabaseHas('controlled_forms', [
            'form_code' => ControlledForm::RFA_FORM_CODE,
            'job_order_variant' => JobOrderVariant::General->value,
        ]);
        $this->assertDatabaseHas('controlled_forms', [
            'form_code' => ControlledForm::RFA_AQUA_FORM_CODE,
            'job_order_variant' => JobOrderVariant::Aqua->value,
        ]);
        $this->assertDatabaseHas('analysis_packages', ['code' => 'PKG-AQUA-MIC']);
        $this->assertDatabaseHas('analysis_packages', ['code' => 'PKG-AQUA-WATER']);
        $this->assertDatabaseHas('analysis_packages', ['code' => 'PKG-DW-PHYSICO']);
        $this->assertDatabaseHas('analysis_packages', [
            'code' => 'PKG-PROXIMATE',
            'report_layout' => 'dynamic_matrix',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('analysis_packages', [
            'code' => 'PKG-MIC-FOOD',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('analysis_packages', [
            'code' => 'PKG-MIC-NDW',
            'is_active' => true,
            'default_price' => 750,
        ]);
        $this->assertDatabaseHas('analysis_packages', [
            'code' => 'PKG-DW-BACT',
            'is_active' => true,
            'default_price' => 300,
        ]);
        $this->assertDatabaseHas('controlled_forms', [
            'form_code' => 'LSP-7.8-FO4',
        ]);
        $this->assertDatabaseHas('controlled_forms', [
            'form_code' => 'LSP-7.8-FO5',
            'analysis_package_id' => null,
        ]);
        $this->assertDatabaseHas('analysis_types', ['code' => 'PCR-01']);
        $this->assertDatabaseHas('analysis_types', [
            'code' => 'AQ-W-01',
            'catalog_scope' => 'aqua',
        ]);
        $this->assertDatabaseHas('analysis_categories', ['slug' => 'water_analysis_aquaculture']);
        $this->assertDatabaseHas('analysis_categories', ['slug' => 'drinking_water']);
    }
}
