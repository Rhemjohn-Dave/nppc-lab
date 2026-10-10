<?php

namespace Tests\Feature;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Services\FieldValueResolver;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobOrderBillingTotalPlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_total_stays_left_when_ten_or_fewer_lines(): void
    {
        $this->seed();

        $job = $this->jobWithLines(10, 11650.0);
        $bag = app(FieldValueResolver::class)->jobOrderBag($job->fresh('analyses'));

        $this->assertSame('11,650.00', $bag['billing_total']);
        $this->assertNull($bag['billing_total_right']);
        $this->assertNotNull($bag['bill_param_10']);
        $this->assertNull($bag['bill_param_11']);
    }

    public function test_billing_total_moves_right_when_lines_spill_past_ten(): void
    {
        $this->seed();

        $job = $this->jobWithLines(17, 17000.0);
        $bag = app(FieldValueResolver::class)->jobOrderBag($job->fresh('analyses'));

        $this->assertSame(' ', $bag['billing_total']);
        $this->assertSame('17,000.00', $bag['billing_total_right']);
        $this->assertNotNull($bag['bill_param_11']);
        $this->assertNotNull($bag['bill_param_17']);
        $this->assertNull($bag['bill_param_18']);
        $this->assertNull($bag['bill_param_20']);
    }

    public function test_billing_slots_fill_through_twenty(): void
    {
        $this->seed();

        $job = $this->jobWithLines(20, 20000.0);
        $bag = app(FieldValueResolver::class)->jobOrderBag($job->fresh('analyses'));

        $this->assertSame(' ', $bag['billing_total']);
        $this->assertSame('20,000.00', $bag['billing_total_right']);
        $this->assertNotNull($bag['bill_param_20']);
        $this->assertNotNull($bag['bill_price_20']);
        $this->assertNotNull($bag['bill_total_20']);
    }

    public function test_same_package_across_samples_collapses_to_one_package_row(): void
    {
        $this->seed();

        $package = $this->package();
        $memberIds = $package->orderedTypeIds();
        $this->assertNotEmpty($memberIds);

        $packagePrice = (float) $package->default_price;
        $this->assertGreaterThan(0, $packagePrice);
        $sampleCount = 2;

        $this->post('/intake/job-orders', [
            'customer_name' => 'Dedupe Billing Customer',
            'customer_email' => 'dedupe@example.com',
            'classification' => 'Wastewater',
            'samples' => [
                [
                    'description' => 'Sample A',
                    'matrix' => 'Liquid',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => $memberIds,
                ],
                [
                    'description' => 'Sample B',
                    'matrix' => 'Liquid',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => $memberIds,
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail()->load('analyses');
        $this->assertCount(count($memberIds) * $sampleCount, $job->analyses);
        $this->assertEqualsWithDelta($packagePrice * $sampleCount, (float) $job->total_cost, 0.01);

        $bag = app(FieldValueResolver::class)->jobOrderBag($job);

        $this->assertSame($package->name, $bag['bill_param_1']);
        $this->assertSame(number_format($packagePrice, 2), $bag['bill_price_1']);
        $this->assertSame(number_format($packagePrice * $sampleCount, 2), $bag['bill_total_1']);
        $this->assertNull($bag['bill_param_2']);
        $this->assertSame(number_format($packagePrice * $sampleCount, 2), $bag['billing_total']);
        $this->assertNull($bag['billing_total_right']);
    }

    public function test_full_package_bills_package_price_and_extra_test_separately(): void
    {
        $this->seed();

        $package = $this->package();
        $memberIds = $package->orderedTypeIds();
        $packagePrice = (float) $package->default_price;

        $extra = AnalysisType::query()
            ->where('is_active', true)
            ->whereNotIn('id', $memberIds)
            ->where('default_price', '>', 0)
            ->orderBy('id')
            ->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Package Billing Customer',
            'customer_email' => 'package-billing@example.com',
            'classification' => 'Wastewater',
            'samples' => [
                [
                    'description' => 'Sample A',
                    'matrix' => 'Liquid',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => [...$memberIds, $extra->id],
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail()->load('analyses');
        $memberTotal = (float) $job->analyses->whereIn('analysis_type_id', $memberIds)->sum('total_cost');
        $this->assertEqualsWithDelta($packagePrice, $memberTotal, 0.001);
        $this->assertEqualsWithDelta(
            $packagePrice + (float) $extra->default_price,
            (float) $job->total_cost,
            0.001,
        );

        $bag = app(FieldValueResolver::class)->jobOrderBag($job);

        $this->assertSame($package->name, $bag['bill_param_1']);
        $this->assertSame(number_format($packagePrice, 2), $bag['bill_price_1']);
        $this->assertSame($extra->name, $bag['bill_param_2']);
        $this->assertSame(number_format((float) $extra->default_price, 2), $bag['bill_price_2']);
        $this->assertNull($bag['bill_param_3']);
    }

    public function test_package_with_unchecked_test_bills_sum_of_selected_tests(): void
    {
        $this->seed();

        $package = $this->package();
        $memberIds = $package->orderedTypeIds();
        $this->assertGreaterThan(1, count($memberIds));
        $selected = array_slice($memberIds, 1);
        $selectedSum = (float) AnalysisType::query()->whereIn('id', $selected)->sum('default_price');

        $this->post('/intake/job-orders', [
            'customer_name' => 'Waived Billing Customer',
            'customer_email' => 'waived-billing@example.com',
            'classification' => 'Wastewater',
            'samples' => [
                [
                    'description' => 'Sample A',
                    'matrix' => 'Liquid',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => $selected,
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail()->load('analyses');
        $this->assertCount(count($selected), $job->analyses);
        $this->assertEqualsWithDelta($selectedSum, (float) $job->total_cost, 0.001);

        $bag = app(FieldValueResolver::class)->jobOrderBag($job);

        $this->assertSame($package->name, $bag['bill_param_1']);
        $this->assertSame(number_format($selectedSum, 2), $bag['bill_price_1']);
        $this->assertNull($bag['bill_param_2']);
    }

    private function package(): \App\Models\AnalysisPackage
    {
        return \App\Models\AnalysisPackage::query()
            ->where('code', 'PKG-MIC-NDW')
            ->with('analysisTypes')
            ->firstOrFail();
    }

    public function test_general_jo_active_revision_has_right_total_field(): void
    {
        $this->seed();

        $form = ControlledForm::query()
            ->where('form_code', ControlledForm::RFA_FORM_CODE)
            ->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $right = $revision->fields()->where('name', 'billing_total_right')->first();
        $this->assertInstanceOf(ControlledFormField::class, $right);
        $this->assertSame('billing_total_right', $right->data_source_key);
        $this->assertFalse((bool) ($right->options['cover'] ?? false));

        $left = $revision->fields()->where('name', 'billing_total')->first();
        $this->assertNotNull($left);
        $this->assertFalse((bool) ($left->options['cover'] ?? false));

        $this->assertTrue($revision->fields()->where('name', 'bill_param_20')->exists());
        $this->assertTrue($revision->fields()->where('name', 'bill_total_20')->exists());
    }

    private function jobWithLines(int $count, float $totalCost): JobOrder
    {
        $codes = OfficialAnalysisCatalog::wastewaterPhysicoIssue18TypeCodes();
        $orderedTypes = AnalysisType::query()
            ->with('category')
            ->whereIn('code', $codes)
            ->get()
            ->sortBy(fn (AnalysisType $type) => array_search($type->code, $codes, true))
            ->values();

        if ($orderedTypes->count() < $count) {
            $extra = AnalysisType::query()
                ->with('category')
                ->where('is_active', true)
                ->whereNotIn('id', $orderedTypes->pluck('id'))
                ->orderBy('id')
                ->limit($count - $orderedTypes->count())
                ->get();
            $orderedTypes = $orderedTypes->concat($extra)->values();
        }

        $this->assertGreaterThanOrEqual($count, $orderedTypes->count());

        $job = JobOrder::query()->create([
            'reference_no' => '26-BILL'.$count,
            'customer_name' => 'Billing Total Customer',
            'status' => JobOrderStatus::DraftSubmitted,
            'total_cost' => $totalCost,
        ]);

        foreach ($orderedTypes->take($count) as $type) {
            JobOrderAnalysis::query()->create([
                'job_order_id' => $job->id,
                'analysis_type_id' => $type->id,
                'name' => $type->name,
                'category' => $type->category?->name,
                'unit_price' => 100,
                'quantity' => 1,
                'total_cost' => 100,
                'status' => JobOrderAnalysisStatus::Pending,
            ]);
        }

        return $job;
    }
}
