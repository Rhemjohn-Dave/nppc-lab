<?php

namespace Tests\Feature;

use App\Enums\JobOrderStatus;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use App\Services\FieldValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoryServicesIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_laboratory_services_creates_flagged_jo_without_samples(): void
    {
        $this->seed();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Lab Services Customer',
            'customer_email' => 'lab-services@example.com',
            'company_name' => 'City LGU',
            'is_laboratory_services' => true,
            'samples' => [],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->is_laboratory_services);
        $this->assertCount(0, $job->samples);
        $this->assertCount(0, $job->analyses);
        $this->assertNull($job->classification);
        $this->assertSame(JobOrderStatus::DraftSubmitted, $job->status);
    }

    public function test_receiving_can_add_price_and_print_billing_for_lab_services(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Fee Customer',
            'company_name' => 'Agency Co',
            'is_laboratory_services' => true,
            'samples' => [],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/lab-services", [
                'services' => [
                    [
                        'name' => 'Sterilization fee',
                        'quantity' => 2,
                        'unit_price' => 150,
                    ],
                    [
                        'name' => 'Consultation',
                        'quantity' => 1,
                        'unit_price' => 500,
                    ],
                ],
                'save_pricing' => true,
                'discount_percent' => 0,
            ])
            ->assertRedirect();

        $job->refresh()->load('analyses');
        $this->assertTrue($job->is_laboratory_services);
        $this->assertCount(2, $job->analyses);
        $this->assertSame(JobOrderStatus::PendingJoApproval, $job->status);
        $this->assertEqualsWithDelta(800.0, (float) $job->total_cost, 0.01);
        $this->assertTrue(
            $job->analyses->every(
                fn (JobOrderAnalysis $line) => $line->isLaboratoryServiceLine()
                    && $line->sample_id === null
                    && $line->analysis_type_id === null,
            ),
        );

        $bag = app(FieldValueResolver::class)->jobOrderBag($job->fresh('analyses'), false);
        $this->assertSame('Laboratory Services', $bag['bill_param_1']);
        $this->assertSame('800.00', $bag['bill_price_1']);
        $this->assertSame('800.00', $bag['bill_total_1']);
        $this->assertNull($bag['bill_param_2']);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertSessionHasErrors('job_order');

        $this->approveJobOrder($job);
        $job->refresh();
        $this->assertSame(JobOrderStatus::JoApproved, $job->status);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/lab-services/ready")
            ->assertRedirect(route('receiving.show', $job));

        $job->refresh()->load('analyses');
        $this->assertSame(JobOrderStatus::ReadyForPickup, $job->status);
        $this->assertTrue(
            $job->analyses->every(fn (JobOrderAnalysis $line) => $line->status->value === 'completed'),
        );
    }

    public function test_receiving_index_flags_laboratory_services(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Badge Customer',
            'is_laboratory_services' => true,
            'samples' => [],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();

        $this->actingAs($receiving)
            ->get('/receiving?q='.urlencode((string) $job->reference_no))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('orders.data.0.id', $job->id)
                ->where('orders.data.0.is_laboratory_services', true)
                ->where('orders.data.0.is_bulk', false));
    }
}
