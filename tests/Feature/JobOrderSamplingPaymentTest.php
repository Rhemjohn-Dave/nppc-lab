<?php

namespace Tests\Feature;

use App\Enums\PaymentMode;
use App\Enums\PaymentTerms;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Services\FieldValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobOrderSamplingPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_stores_sampling_site_and_billing_payment_with_terms(): void
    {
        $this->seed();

        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Payment Customer',
            'customer_email' => 'pay@example.com',
            'classification' => 'Potability',
            'sampling_site' => 'Barangay hall deep well',
            'payment_mode' => PaymentMode::BillingPartial->value,
            'payment_terms' => PaymentTerms::Days30->value,
            'samples' => [
                ['description' => 'Tap water', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertSame('Barangay hall deep well', $job->sampling_site);
        $this->assertSame(PaymentMode::BillingPartial, $job->payment_mode);
        $this->assertSame(PaymentTerms::Days30, $job->payment_terms);

        $bag = app(FieldValueResolver::class)->jobOrderBag($job);
        $this->assertSame('Barangay hall deep well', $bag['sampling_site']);
        $this->assertTrue($bag['payment_billing_partial']);
        $this->assertFalse($bag['payment_cash']);
        $this->assertFalse($bag['payment_check']);
        $this->assertTrue($bag['payment_terms_30']);
        $this->assertFalse($bag['payment_terms_15']);
    }

    public function test_billing_partial_requires_payment_terms(): void
    {
        $this->seed();

        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->from('/intake/create')
            ->post('/intake/job-orders', [
                'customer_name' => 'Missing Terms',
                'customer_email' => 'terms@example.com',
                'payment_mode' => PaymentMode::BillingPartial->value,
                'samples' => [
                    ['description' => 'Sample', 'matrix' => 'Liquid'],
                ],
                'analysis_type_ids' => [$type->id],
            ])
            ->assertRedirect('/intake/create')
            ->assertSessionHasErrors('payment_terms');
    }

    public function test_cash_payment_clears_terms(): void
    {
        $this->seed();

        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Cash Customer',
            'customer_email' => 'cash@example.com',
            'sampling_site' => 'Plant gate',
            'payment_mode' => PaymentMode::Cash->value,
            'payment_terms' => PaymentTerms::Days15->value,
            'samples' => [
                ['description' => 'Sample', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertSame(PaymentMode::Cash, $job->payment_mode);
        $this->assertNull($job->payment_terms);

        $bag = app(FieldValueResolver::class)->jobOrderBag($job);
        $this->assertTrue($bag['payment_cash']);
        $this->assertFalse($bag['payment_terms_15']);
        $this->assertFalse($bag['payment_terms_30']);
    }

    public function test_check_payment_mode_persists(): void
    {
        $this->seed();

        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Check Customer',
            'customer_email' => 'check@example.com',
            'payment_mode' => PaymentMode::Check->value,
            'samples' => [
                ['description' => 'Sample', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertSame(PaymentMode::Check, $job->payment_mode);
        $this->assertNull($job->payment_terms);

        $bag = app(FieldValueResolver::class)->jobOrderBag($job);
        $this->assertTrue($bag['payment_check']);
        $this->assertFalse($bag['payment_cash']);
        $this->assertFalse($bag['payment_billing_partial']);
    }

    public function test_kiosk_exposes_non_aqua_other_classification_options(): void
    {
        $this->seed();

        $this->get('/intake/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('intake/wizard')
                ->has('options.other_classification_options')
                ->where('options.other_classification_options', function ($options) {
                    $labels = collect($options);

                    return $labels->contains('Proximate Analysis')
                        && $labels->contains('Food Products - Microbiological Test')
                        && ! $labels->contains('Water Analysis - Aquaculture')
                        && ! $labels->contains('Soil Analysis - Aquaculture');
                })
            );
    }

    public function test_others_classification_dropdown_value_persists(): void
    {
        $this->seed();

        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Others Customer',
            'customer_email' => 'others@example.com',
            'classification' => 'Others: Proximate Analysis',
            'payment_mode' => PaymentMode::Cash->value,
            'samples' => [
                ['description' => 'Food sample', 'matrix' => 'Solid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertSame('Others: Proximate Analysis', $job->classification);

        $bag = app(FieldValueResolver::class)->jobOrderBag($job);
        $this->assertTrue((bool) $bag['class_others']);
        $this->assertSame('Others: Proximate Analysis', $bag['class_others_text']);
    }

    public function test_empty_sample_description_is_allowed(): void
    {
        $this->seed();

        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'No Description Customer',
            'customer_email' => 'nodesc@example.com',
            'classification' => 'Potability',
            'samples' => [
                [
                    'sample_code' => 'S-1',
                    'description' => '',
                    'matrix' => 'Liquid',
                    'quantity' => '500',
                    'unit' => 'mL',
                ],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $sample = $job->samples()->firstOrFail();
        $this->assertSame('', $sample->description);
        $this->assertSame('S-1', $sample->sample_code);
    }

    public function test_general_jo_form_has_sampling_and_payment_overlay_fields(): void
    {
        $this->seed();

        $form = ControlledForm::query()
            ->where('form_code', ControlledForm::RFA_FORM_CODE)
            ->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);
        $this->assertTrue($revision->hasCanonicalPdf());

        $names = $revision->fields()->pluck('name')->all();
        foreach ([
            'sampling_site',
            'payment_cash',
            'payment_billing_partial',
            'payment_check',
            'payment_terms_15',
            'payment_terms_30',
        ] as $name) {
            $this->assertContains($name, $names);
        }
    }
}
