<?php

namespace Tests\Feature;

use App\Enums\JobOrderStatus;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\JobOrderPackage;
use App\Models\User;
use App\Services\FieldValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingAddAnalysesTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_can_add_package_and_individual_test_before_jo_approval(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $starter = AnalysisType::query()->where('code', 'DW-03')->firstOrFail();
        $extraType = AnalysisType::query()->where('code', 'DW-02')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-DW-BACT')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Forgot Test Customer',
            'customer_email' => 'forgot@example.com',
            'classification' => 'Potability',
            'samples' => [
                [
                    'classification' => 'Potability',
                    'description' => 'Kitchen tap',
                    'matrix' => 'Liquid',
                    'analysis_type_ids' => [$starter->id],
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->with(['samples', 'analyses'])->firstOrFail();
        $sample = $job->samples->firstOrFail();
        $this->assertSame(1, $job->analyses->count());

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/analyses/packages", [
                'analysis_package_id' => $package->id,
                'sample_id' => $sample->id,
            ])
            ->assertRedirect();

        $job->refresh()->load(['analyses', 'packageLinks']);
        $this->assertTrue(
            $job->packageLinks->contains(
                fn (JobOrderPackage $link) => (int) $link->analysis_package_id === (int) $package->id,
            ),
        );
        foreach ($package->orderedTypeIds() as $typeId) {
            $this->assertTrue(
                $job->analyses->contains(
                    fn ($line) => (int) $line->analysis_type_id === (int) $typeId
                        && (int) $line->sample_id === (int) $sample->id,
                ),
                "Expected package member type {$typeId} on the sample.",
            );
        }

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/analyses/types", [
                'analysis_type_id' => $extraType->id,
                'sample_id' => $sample->id,
            ])
            ->assertRedirect();

        $job->refresh()->load('analyses');
        $this->assertTrue(
            $job->analyses->contains(
                fn ($line) => (int) $line->analysis_type_id === (int) $extraType->id,
            ),
        );

        $bag = app(FieldValueResolver::class)->jobOrderBag($job->fresh(['analyses.analysisType', 'packages']));
        $params = [];
        for ($i = 1; $i <= 20; $i++) {
            $value = trim((string) ($bag["bill_param_{$i}"] ?? ''));
            if ($value !== '') {
                $params[] = $value;
            }
        }
        $this->assertContains($package->name, $params);
        $this->assertContains($extraType->name, $params);
    }

    public function test_receiving_cannot_add_analyses_after_jo_approval(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'DW-01')->firstOrFail();
        $extra = AnalysisType::query()->where('code', 'DW-02')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Locked After Approval',
            'customer_email' => 'locked@example.com',
            'classification' => 'Potability',
            'samples' => [
                [
                    'classification' => 'Potability',
                    'description' => 'Kitchen tap',
                    'matrix' => 'Liquid',
                    'analysis_type_ids' => [$type->id],
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 100, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->approveJobOrder($job);
        $job->refresh();
        $this->assertSame(JobOrderStatus::JoApproved, $job->status);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/analyses/types", [
                'analysis_type_id' => $extra->id,
                'sample_id' => $job->samples()->firstOrFail()->id,
            ])
            ->assertNotFound();
    }

    public function test_duplicate_test_on_same_sample_is_rejected(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'DW-01')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Duplicate Guard',
            'customer_email' => 'dup@example.com',
            'classification' => 'Potability',
            'samples' => [
                [
                    'classification' => 'Potability',
                    'description' => 'Kitchen tap',
                    'matrix' => 'Liquid',
                    'analysis_type_ids' => [$type->id],
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->with('samples')->firstOrFail();

        $this->actingAs($receiving)
            ->from("/receiving/{$job->id}")
            ->post("/receiving/{$job->id}/analyses/types", [
                'analysis_type_id' => $type->id,
                'sample_id' => $job->samples->first()->id,
            ])
            ->assertRedirect("/receiving/{$job->id}")
            ->assertSessionHasErrors('analysis_type_id');
    }

    public function test_bulk_add_applies_to_every_sample(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'DW-01')->firstOrFail();
        $extra = AnalysisType::query()->where('code', 'DW-02')->firstOrFail();

        $this->actingAs($receiving)
            ->post('/receiving/bulk-orders', [
                'customer_name' => 'Bulk Agency',
                'company_name' => 'Bulk Co',
                'customer_email' => 'bulk-add@example.com',
                'classification' => 'Potability',
                'analysis_type_ids' => [$type->id],
                'samples' => [
                    [
                        'sample_code' => 'A',
                        'description' => 'Point A',
                        'matrix' => 'Water',
                    ],
                    [
                        'sample_code' => 'B',
                        'description' => 'Point B',
                        'matrix' => 'Water',
                    ],
                ],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->with('samples')->latest('id')->firstOrFail();
        $this->assertTrue((bool) $job->is_receiving_bulk);
        $this->assertSame(2, $job->samples->count());

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/analyses/types", [
                'analysis_type_id' => $extra->id,
            ])
            ->assertRedirect();

        $job->refresh()->load('analyses');
        foreach ($job->samples as $sample) {
            $this->assertTrue(
                $job->analyses->contains(
                    fn ($line) => (int) $line->sample_id === (int) $sample->id
                        && (int) $line->analysis_type_id === (int) $extra->id,
                ),
                "Expected extra test on sample {$sample->id}.",
            );
        }
    }
}
