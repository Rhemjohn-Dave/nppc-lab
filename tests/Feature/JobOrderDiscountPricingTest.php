<?php

namespace Tests\Feature;

use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobOrderDiscountPricingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array{description?: string, matrix?: string, classification?: string}>  $samples
     * @param  list<int>  $typeIds
     * @param  list<int>  $packageIds
     */
    private function createJob(
        string $name,
        array $samples,
        array $typeIds = [],
        array $packageIds = [],
        ?string $classification = 'Wastewater',
    ): JobOrder {
        $response = $this->post('/intake/job-orders', [
            'customer_name' => $name,
            'customer_email' => strtolower(str_replace(' ', '-', $name)).'@example.com',
            'customer_contact' => '09171234567',
            'classification' => $classification,
            'samples' => $samples,
            'analysis_type_ids' => $typeIds,
            'package_ids' => $packageIds,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        return JobOrder::query()->latest('id')->firstOrFail();
    }

    public function test_order_level_percent_discount_reduces_total_cost(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $job = $this->createJob('Discount Customer', [
            ['description' => 'Wastewater', 'matrix' => 'Liquid', 'classification' => 'Wastewater'],
        ], [$type->id]);

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

        $job = $this->createJob('Sticky Discount', [
            ['description' => 'Wastewater', 'matrix' => 'Liquid', 'classification' => 'Wastewater'],
        ], [$type->id]);

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

        $job = $this->createJob('Over Discount', [
            ['description' => 'Wastewater', 'matrix' => 'Liquid', 'classification' => 'Wastewater'],
        ], [$type->id]);

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

    public function test_line_discount_only_affects_selected_tests(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $typeA = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $typeB = AnalysisType::query()->where('code', 'WW-01')->firstOrFail();

        $job = $this->createJob('Partial Line Discount', [
            ['description' => 'Wastewater', 'matrix' => 'Liquid', 'classification' => 'Wastewater'],
            ['description' => 'Wastewater', 'matrix' => 'Liquid', 'classification' => 'Wastewater'],
        ], [$typeA->id, $typeB->id]);

        $lines = $job->analyses()->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(4, $lines->count());

        $discounted = $lines->firstWhere('analysis_type_id', $typeA->id);
        $this->assertNotNull($discounted);

        $payloadLines = $lines->map(function ($line) use ($discounted) {
            return [
                'id' => $line->id,
                'unit_price' => 100,
                'quantity' => 1,
                'discount_percent' => (int) $line->id === (int) $discounted->id ? 20 : 0,
            ];
        })->all();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $payloadLines,
                'discount_percent' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $job->refresh();
        $discounted->refresh();
        $fullPriceLine = $lines->first(
            fn ($line) => (int) $line->id !== (int) $discounted->id,
        );
        $this->assertNotNull($fullPriceLine);
        $fullPriceLine->refresh();

        $this->assertSame('20.00', number_format((float) $discounted->discount_percent, 2));
        $this->assertSame('80.00', number_format((float) $discounted->total_cost, 2));
        $this->assertSame('0.00', number_format((float) $fullPriceLine->discount_percent, 2));
        $this->assertSame('100.00', number_format((float) $fullPriceLine->total_cost, 2));

        $expectedNet = ($lines->count() * 100) - 20;
        $this->assertSame(number_format($expectedNet, 2), number_format((float) $job->total_cost, 2));
        $this->assertSame('20.00', number_format((float) $job->discount_amount, 2));
    }

    public function test_line_and_jo_discount_stack(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $job = $this->createJob('Stacked Discount', [
            ['description' => 'Wastewater', 'matrix' => 'Liquid', 'classification' => 'Wastewater'],
        ], [$type->id]);

        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    [
                        'id' => $line->id,
                        'unit_price' => 200,
                        'quantity' => 1,
                        'discount_percent' => 10,
                    ],
                ],
                'discount_percent' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $job->refresh();
        $line->refresh();
        $this->assertSame('180.00', number_format((float) $line->total_cost, 2));
        $this->assertSame('38.00', number_format((float) $job->discount_amount, 2));
        $this->assertSame('162.00', number_format((float) $job->total_cost, 2));
    }

    public function test_package_member_discounts_sync_pivot_percent(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()
            ->where('code', 'PKG-DW-BACT')
            ->firstOrFail();

        $job = $this->createJob(
            'Package Discount',
            [
                [
                    'description' => 'Drinking water',
                    'matrix' => 'Liquid',
                    'classification' => 'Potability',
                ],
            ],
            [],
            [$package->id],
            'Potability',
        );

        $members = $job->analyses()->orderBy('id')->get();
        $this->assertNotEmpty($members);

        $payloadLines = $members->map(fn ($line) => [
            'id' => $line->id,
            'unit_price' => (float) $line->unit_price,
            'quantity' => (int) $line->quantity,
            'discount_percent' => 15,
        ])->all();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $payloadLines,
                'discount_percent' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        foreach ($members as $member) {
            $member->refresh();
            $this->assertSame('15.00', number_format((float) $member->discount_percent, 2));
        }

        $link = $job->packageLinks()->firstOrFail();
        $this->assertSame('15.00', number_format((float) $link->discount_percent, 2));
    }
}
