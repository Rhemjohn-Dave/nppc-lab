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
