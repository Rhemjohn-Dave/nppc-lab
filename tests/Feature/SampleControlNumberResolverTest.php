<?php

namespace Tests\Feature;

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\Sample;
use App\Services\FieldValueResolver;
use App\Support\SampleControlNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleControlNumberResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_applies_suffix_rule_and_samples_table_column(): void
    {
        $this->seed();

        $single = JobOrder::query()->create([
            'reference_no' => '26-SING1',
            'customer_name' => 'Single Sample',
            'status' => JobOrderStatus::DraftSubmitted,
            'total_cost' => 0,
        ]);
        $this->addSample($single, 'S1', 'One');

        $multi = JobOrder::query()->create([
            'reference_no' => '26-MULT1',
            'customer_name' => 'Multi Sample',
            'status' => JobOrderStatus::DraftSubmitted,
            'total_cost' => 0,
        ]);
        $this->addSample($multi, 'A1', 'First');
        $this->addSample($multi, 'A2', 'Second');

        $resolver = app(FieldValueResolver::class);

        $singleBag = $resolver->jobOrderBag($single->fresh('samples'), false);
        $this->assertSame('26-SING1', $singleBag['control_number_1']);
        $this->assertNull($singleBag['control_number_2']);
        $this->assertSame('26-SING1', $singleBag['samples[]'][0]['control_number']);

        $multiBag = $resolver->jobOrderBag($multi->fresh('samples'), false);
        $this->assertSame('26-MULT1A', $multiBag['control_number_1']);
        $this->assertSame('26-MULT1B', $multiBag['control_number_2']);
        $this->assertNull($multiBag['control_number_3']);
        $this->assertSame(
            ['26-MULT1A', '26-MULT1B'],
            array_column($multiBag['samples[]'], 'control_number'),
        );
    }

    public function test_resolver_fills_sixteen_sample_slots_with_letter_suffixes(): void
    {
        $this->seed();

        $job = JobOrder::query()->create([
            'reference_no' => '26-0020',
            'customer_name' => 'Sixteen Samples',
            'status' => JobOrderStatus::DraftSubmitted,
            'total_cost' => 0,
        ]);

        for ($i = 1; $i <= FieldValueResolver::RFA_SAMPLE_TOTAL_SLOTS; $i++) {
            $this->addSample($job, 'S'.$i, 'Sample '.$i);
        }

        $bag = app(FieldValueResolver::class)->jobOrderBag($job->fresh('samples'), false);

        $this->assertSame('26-0020A', $bag['control_number_1']);
        $this->assertSame('26-0020B', $bag['control_number_2']);
        $this->assertSame('26-0020H', $bag['control_number_8']);
        $this->assertSame('26-0020I', $bag['control_number_9']);
        $this->assertSame('26-0020P', $bag['control_number_16']);
        $this->assertArrayHasKey('control_number_16', $bag);
        $this->assertArrayNotHasKey('control_number_17', $bag);
        $this->assertNotNull($bag['sample_code_8']);
        $this->assertNotNull($bag['sample_code_16']);
        $this->assertNull($bag['sample_code_17'] ?? null);
    }

    public function test_designer_catalog_lists_rfa_sample_line_sources(): void
    {
        $samplesGroup = collect(config('controlled_form_sources.groups'))
            ->firstWhere('label', 'Samples');

        $this->assertNotNull($samplesGroup);
        $keys = collect($samplesGroup['sources'])->pluck('key');

        $this->assertTrue($keys->contains('sample_code_1'));
        $this->assertTrue($keys->contains('control_number_9'));
        $this->assertTrue($keys->contains('sample_code_16'));
        $this->assertTrue($keys->contains('control_number_16'));
        $this->assertTrue($keys->contains('samples[]'));

        $analysesGroup = collect(config('controlled_form_sources.groups'))
            ->firstWhere('label', 'Analyses / tests');
        $this->assertNotNull($analysesGroup);
        $analysisKeys = collect($analysesGroup['sources'])->pluck('key');
        $this->assertTrue($analysisKeys->contains('bill_param_1'));
        $this->assertTrue($analysisKeys->contains('bill_price_7'));
        $this->assertTrue($analysisKeys->contains('bill_total_14'));
        $this->assertTrue($analysisKeys->contains('bill_param_20'));
        $this->assertTrue($analysisKeys->contains('bill_total_20'));
        $this->assertTrue($analysisKeys->contains('billing_total'));
        $this->assertTrue($analysisKeys->contains('billing_total_right'));
    }

    public function test_helper_matches_documented_rule(): void
    {
        $this->assertSame('REF', SampleControlNumber::forIndex('REF', 0, 1));
        $this->assertSame('REFA', SampleControlNumber::forIndex('REF', 0, 3));
        $this->assertSame('REFB', SampleControlNumber::forIndex('REF', 1, 3));
        $this->assertSame('REFP', SampleControlNumber::forIndex('REF', 15, 16));
    }

    public function test_general_jo_active_revision_has_sixteen_sample_slots(): void
    {
        $this->seed();

        $form = \App\Models\ControlledForm::query()
            ->where('form_code', \App\Models\ControlledForm::RFA_FORM_CODE)
            ->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $this->assertTrue($revision->fields()->where('name', 'sample_code_8')->exists());
        $this->assertTrue($revision->fields()->where('name', 'control_number_8')->exists());
        $this->assertTrue($revision->fields()->where('name', 'sample_code_16')->exists());
        $this->assertTrue($revision->fields()->where('name', 'control_number_16')->exists());

        $leftEight = $revision->fields()->where('name', 'sample_code_8')->first();
        $this->assertNotNull($leftEight);
        $this->assertLessThan(100.0, (float) $leftEight->x, 'slot 8 should be on the left column');

        $rightNine = $revision->fields()->where('name', 'sample_code_9')->first();
        $this->assertNotNull($rightNine);
        $this->assertGreaterThan(100.0, (float) $rightNine->x, 'slot 9 should be on the right column');
    }

    private function addSample(JobOrder $job, string $code, string $description): void
    {
        Sample::query()->create([
            'job_order_id' => $job->id,
            'sample_code' => $code,
            'description' => $description,
            'sort_order' => Sample::query()->where('job_order_id', $job->id)->count(),
        ]);
    }
}
