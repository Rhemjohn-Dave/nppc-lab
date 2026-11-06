<?php

namespace Tests\Feature;

use App\Enums\ControlledFormFieldType;
use App\Models\AnalysisPackage;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Models\JobOrder;
use App\Services\FieldValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MixedSampleClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_job_order_keeps_potability_and_wastewater_on_their_samples(): void
    {
        $this->seed();

        $potability = AnalysisPackage::query()->where('code', 'PKG-DW-BACT')->firstOrFail();
        $wastewater = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Mixed Classification Customer',
            'customer_email' => 'mixed@example.com',
            'samples' => [
                [
                    'classification' => 'Potability',
                    'sample_code' => 'TAP-1',
                    'description' => 'Kitchen tap',
                    'matrix' => 'Liquid',
                    'package_ids' => [$potability->id],
                    'analysis_type_ids' => $potability->orderedTypeIds(),
                ],
                [
                    'classification' => 'Wastewater',
                    'sample_code' => 'OUT-1',
                    'description' => 'River water',
                    'matrix' => 'Liquid',
                    'package_ids' => [$wastewater->id],
                    'analysis_type_ids' => $wastewater->orderedTypeIds(),
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(1, JobOrder::query()->count());

        $job = JobOrder::query()->with('samples')->firstOrFail();
        $this->assertSame('Potability, Wastewater', $job->classification);
        $this->assertSame('Potability', $job->samples[0]->classification);
        $this->assertSame('Wastewater', $job->samples[1]->classification);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->firstOrFail();
        $revision = $form->revisions()->firstOrFail();
        $revision->setRelation('fields', collect([
            new ControlledFormField([
                'name' => 'results.classification',
                'field_type' => ControlledFormFieldType::Text,
            ]),
            new ControlledFormField([
                'name' => 'results.sample_description',
                'field_type' => ControlledFormFieldType::Text,
            ]),
        ]));
        $resolver = app(FieldValueResolver::class);

        $jobBag = $resolver->jobOrderBag($job);
        $this->assertTrue($jobBag['class_potability']);
        $this->assertTrue($jobBag['class_wastewater']);
        $this->assertFalse($jobBag['class_aqua']);

        $potabilityBag = $resolver->forResult($revision, $job, null, $form, $job->samples[0]);
        $wastewaterBag = $resolver->forResult($revision, $job, null, $form, $job->samples[1]);
        $this->assertSame('Potability', $potabilityBag['results.classification']);
        $this->assertSame('Wastewater', $wastewaterBag['results.classification']);

        $fo4 = ControlledForm::query()->where('form_code', 'LSP-7.8-FO4')->firstOrFail();
        $fo4Revision = $fo4->revisions()->firstOrFail();
        $fo4Revision->setRelation('fields', $revision->fields);
        $fo4Bag = $resolver->forResult($fo4Revision, $job, null, $fo4, $job->samples[1]);
        $this->assertSame('Wastewater', $fo4Bag['results.sample_description']);
        $this->assertSame('Wastewater', $fo4Bag['results.classification']);
    }

    public function test_aqua_cannot_share_a_job_order_with_potability(): void
    {
        $this->seed();

        $potability = AnalysisPackage::query()->where('code', 'PKG-DW-BACT')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Mixed Form Customer',
            'customer_email' => 'mixed-form@example.com',
            'samples' => [
                [
                    'classification' => 'Aqua',
                    'description' => 'Pond',
                    'matrix' => 'Liquid',
                    'package_ids' => [$potability->id],
                    'analysis_type_ids' => $potability->orderedTypeIds(),
                ],
                [
                    'classification' => 'Potability',
                    'description' => 'Tap',
                    'matrix' => 'Liquid',
                    'package_ids' => [$potability->id],
                    'analysis_type_ids' => $potability->orderedTypeIds(),
                ],
            ],
        ])->assertSessionHasErrors('samples');

        $this->assertSame(0, JobOrder::query()->count());
    }
}
