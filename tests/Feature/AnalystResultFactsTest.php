<?php

namespace Tests\Feature;

use App\Models\AnalysisPackage;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\FieldValueResolver;
use App\Support\BulkSampleRemarks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalystResultFactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fo5_analyst_can_edit_the_printed_water_supply_until_release(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-DW-BACT')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $other = User::where('email', 'analyst2@nppc.local')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Water Supply Customer',
            'customer_email' => 'supply@example.com',
            'classification' => 'Potability',
            'samples' => [
                ['description' => 'Kitchen faucet', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $job->update(['wastewater_source' => 'Faucet']);
        $sample = $job->samples()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $job->analyses->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 100,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $job->analyses()->update(['assigned_to' => $analyst->id]);
        $other->analysisTypes()->detach(
            $job->analyses()->pluck('analysis_type_id')->all(),
        );

        $this->actingAs($analyst)
            ->get('/analyst?q=Water%20Supply%20Customer')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tasks', function ($tasks) {
                $facts = collect($tasks[0]['result_facts'] ?? []);
                $this->assertTrue($facts->contains(
                    fn (array $fact): bool => $fact['key'] === 'wastewater_source'
                        && $fact['value'] === 'Faucet',
                ));
                $this->assertFalse($facts->contains(
                    fn (array $fact): bool => $fact['key'] === 'specimen',
                ));
                $this->assertTrue($tasks[0]['can_edit_result_facts'] === true);

                return true;
            }));

        $this->actingAs($analyst)
            ->withHeader('referer', 'http://127.0.0.1:8000/analyst?job='.$job->id)
            ->patch("/analyst/job-orders/{$job->id}/result-facts", [
                'sample_id' => $sample->id,
                'wastewater_source' => 'Deep well',
                'specimen' => 'Should stay hidden',
            ])
            ->assertRedirect('/analyst?job='.$job->id)
            ->assertSessionDoesntHaveErrors();

        $job->refresh();
        $this->assertSame('Deep well', $job->wastewater_source);
        $this->assertNull($job->specimen);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->firstOrFail();
        $revision = $form->revisions()->latest('id')->firstOrFail();
        $bag = app(FieldValueResolver::class)->forResult($revision, $job->fresh(['samples', 'analyses']), null, $form);

        $this->assertSame('Deep well', $bag['results.water_supply']);
        $this->assertSame('Deep well', $bag['results.sampling_point']);

        $this->actingAs($other)
            ->patch("/analyst/job-orders/{$job->id}/result-facts", [
                'sample_id' => $sample->id,
                'wastewater_source' => 'Should not save',
            ])
            ->assertForbidden();

        $this->assertSame('Deep well', $job->fresh()->wastewater_source);

        $sample->update(['released_at' => now()]);

        $this->actingAs($analyst)
            ->patch("/analyst/job-orders/{$job->id}/result-facts", [
                'sample_id' => $sample->id,
                'wastewater_source' => 'After release',
            ])
            ->assertForbidden();

        $this->assertSame('Deep well', $job->fresh()->wastewater_source);
    }

    public function test_bulk_sample_fact_edit_does_not_change_the_other_samples(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-DW-BACT')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->actingAs($receiving)
            ->post('/receiving/bulk-orders', [
                'customer_name' => 'Bulk Water District',
                'customer_email' => 'bulk-facts@example.com',
                'classification' => 'Potability',
                'sampling_date' => '2026-11-05',
                'sampling_time' => '07:00',
                'wastewater_source' => 'Shared source',
                'package_ids' => [$package->id],
                'samples' => [
                    [
                        'description' => 'Source A',
                        'location' => 'Site A',
                        'sample_point' => 'Spring',
                        'collected_time' => '08:15',
                        'matrix' => 'Water',
                    ],
                    [
                        'description' => 'Source B',
                        'location' => 'Site B',
                        'sample_point' => 'Tap',
                        'collected_time' => '09:45',
                        'matrix' => 'Water',
                    ],
                ],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->is_receiving_bulk);
        $job->analyses()->update(['assigned_to' => $analyst->id]);
        $sampleA = $job->samples[0];
        $sampleB = $job->samples[1];
        $remarksB = $sampleB->remarks;

        $this->actingAs($analyst)
            ->withHeader('referer', 'http://127.0.0.1:8000/analyst?job='.$job->id)
            ->patch("/analyst/job-orders/{$job->id}/result-facts", [
                'sample_id' => $sampleA->id,
                'location' => 'Site A gate',
                'collected_time' => '08:30',
            ])
            ->assertRedirect('/analyst?job='.$job->id)
            ->assertSessionDoesntHaveErrors();

        $sampleA->refresh();
        $sampleB->refresh();
        $job->refresh();

        $parsedA = BulkSampleRemarks::parse($sampleA->remarks);
        $this->assertSame('Site A gate', $parsedA['location']);
        $this->assertSame('Spring', $parsedA['sample_point']);
        $this->assertSame('08:30', $parsedA['collected_time']);
        $this->assertSame($remarksB, $sampleB->remarks);
        $this->assertSame('Source A', $sampleA->description);
        $this->assertSame('Source B', $sampleB->description);
        $this->assertSame('Shared source', $job->wastewater_source);
        $this->assertSame('07:00', $job->sampling_time);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->firstOrFail();
        $revision = $form->revisions()->latest('id')->firstOrFail();
        $resolver = app(FieldValueResolver::class);
        $fresh = $job->fresh(['samples', 'analyses']);
        $bagA = $resolver->forResult($revision, $fresh, null, $form, $fresh->samples[0]);
        $bagB = $resolver->forResult($revision, $fresh, null, $form, $fresh->samples[1]);

        $this->assertSame('Site A gate', $bagA['results.water_supply']);
        $this->assertSame('Spring', $bagA['results.sampling_point']);
        $this->assertSame('November 5, 2026 (8:30AM)', $bagA['results.collection_datetime']);

        $this->assertSame('Site B', $bagB['results.water_supply']);
        $this->assertSame('Tap', $bagB['results.sampling_point']);
        $this->assertSame('November 5, 2026 (9:45AM)', $bagB['results.collection_datetime']);
    }
}
