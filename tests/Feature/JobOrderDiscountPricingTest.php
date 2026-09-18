<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobOrderDiscountPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_level_percent_discount_reduces_total_cost(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Discount Customer',
            'customer_email' => 'discount@example.com',
            'samples' => [
                ['description' => 'Sample', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 100, 'quantity' => 1],
                ],
                'discount_percent' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $job->refresh();
        $this->assertSame('10.00', number_format((float) $job->discount_percent, 2));
        $this->assertSame('10.00', number_format((float) $job->discount_amount, 2));
        $this->assertSame('90.00', number_format((float) $job->total_cost, 2));
        $this->assertSame('100.00', number_format((float) $line->fresh()->total_cost, 2));
    }

    public function test_percent_discount_reapplies_when_line_prices_change(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Sticky Discount',
            'customer_email' => 'sticky@example.com',
            'samples' => [
                ['description' => 'Sample', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 200, 'quantity' => 1],
                ],
                'discount_percent' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 500, 'quantity' => 1],
                ],
                'discount_percent' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $job->refresh();
        $this->assertSame('10.00', number_format((float) $job->discount_percent, 2));
        $this->assertSame('50.00', number_format((float) $job->discount_amount, 2));
        $this->assertSame('450.00', number_format((float) $job->total_cost, 2));
    }

    public function test_discount_percent_cannot_exceed_100(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Over Discount',
            'customer_email' => 'over-discount@example.com',
            'samples' => [
                ['description' => 'Sample', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->from("/receiving/{$job->id}")
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 100, 'quantity' => 1],
                ],
                'discount_percent' => 150,
            ])
            ->assertRedirect("/receiving/{$job->id}")
            ->assertSessionHasErrors('discount_percent');
    }
}
