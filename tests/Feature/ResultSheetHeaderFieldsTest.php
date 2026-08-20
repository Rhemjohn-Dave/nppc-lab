<?php

namespace Tests\Feature;

use App\Enums\ControlledFormCategory;
use App\Models\AnalysisPackage;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\FieldValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResultSheetHeaderFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_result_catalog_lists_official_header_fields(): void
    {
        $this->seed();

        $keys = collect(FieldValueResolver::catalog(ControlledFormCategory::AnalysisResult->value))
            ->pluck('key');

        $this->assertTrue($keys->contains('results.sample_received_at'));
        $this->assertTrue($keys->contains('results.sampling_datetime'));
        $this->assertTrue($keys->contains('results.analysis_datetime'));
        $this->assertTrue($keys->contains('results.release_date'));
        $this->assertTrue($keys->contains('results.customer'));
        $this->assertTrue($keys->contains('results.collected_by'));
        $this->assertTrue($keys->contains('results.receipt_at'));
        $this->assertTrue($keys->contains('results.ref_no'));
        $this->assertTrue($keys->contains('results.control_no'));
        $this->assertTrue($keys->contains('results.sample_code'));
        $this->assertTrue($keys->contains('results.collection_datetime'));
        $this->assertTrue($keys->contains('results.examination_datetime'));
        $this->assertTrue($keys->contains('results.report_date'));
        $this->assertTrue($keys->contains('results.water_supply'));
        $this->assertTrue($keys->contains('results.sampling_point'));
        $this->assertTrue($keys->contains('results.classification'));
    }

    public function test_sample_received_uses_kiosk_submit_datetime_format(): void
    {
        $this->seed();

        $this->travelTo(Carbon::parse('2026-07-29 15:00:00', 'Asia/Manila'));

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Coastal Farms',
            'customer_email' => 'coastal@example.com',
            'customer_address' => 'Bacolod City',
            'classification' => 'Wastewater',
            'sampling_date' => '2026-07-20',
            'sampling_time' => '09:30',
            'sample_collected_by' => 'Juan Cruz',
            'wastewater_source' => 'Faucet',
            'samples' => [
                ['description' => 'Wastewater', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $bag = app(FieldValueResolver::class)->jobOrderBag($job, true);

        $this->assertSame('Coastal Farms', $bag['results.customer']);
        $this->assertSame('Bacolod City', $bag['results.address']);
        $this->assertSame('July 29, 2026 (3:00PM)', $bag['results.sample_received_at']);
        $this->assertSame('Wastewater', $bag['results.sample_code']);
        $this->assertNull($bag['results.sample_description']);
        $this->assertSame('July 20, 2026 (9:30AM)', $bag['results.sampling_datetime']);
        $this->assertSame('Juan Cruz', $bag['results.collected_by']);
        $this->assertSame($job->reference_no, $bag['results.ref_no']);
        $this->assertSame($job->reference_no, $bag['results.control_no']);
        $this->assertSame($job->reference_no, $bag['control_number_1']);
        $this->assertSame('July 20, 2026 (9:30AM)', $bag['results.collection_datetime']);
        $this->assertSame($bag['results.sampling_datetime'], $bag['results.collection_datetime']);
        $this->assertSame('Faucet', $bag['results.water_supply']);
        $this->assertSame('Faucet', $bag['results.sampling_point']);
        $this->assertNull($bag['results.analysis_datetime']);
        $this->assertNull($bag['results.release_date']);
        $this->assertNull($bag['results.report_date']);
    }

    public function test_others_specified_source_and_classification_print_on_result_header(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Other Source Customer',
            'customer_email' => 'other-source@example.com',
            'classification' => 'Others: Irrigation canal',
            'wastewater_source' => 'Others: Spring box',
            'samples' => [
                ['description' => 'Spring water', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $bag = app(FieldValueResolver::class)->jobOrderBag($job, true);

        $this->assertSame('Irrigation canal', $bag['results.classification']);
        $this->assertSame('Spring box', $bag['results.sampling_point']);
        $this->assertSame('Spring box', $bag['results.water_supply']);
    }

    public function test_analysis_datetime_uses_first_completed_result(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Coastal Farms',
            'customer_email' => 'coastal-analysis@example.com',
            'samples' => [
                ['description' => 'Wastewater', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $lines = $job->analyses()->get();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $lines->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 225,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();

        $this->travelTo(Carbon::parse('2026-07-21 10:00:00', 'Asia/Manila'));

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->travelTo(Carbon::parse('2026-07-29 15:00:00', 'Asia/Manila'));

        $first = $job->analyses()->firstOrFail();
        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$first->id}/complete", [
                'result_value' => 'Passed',
            ])
            ->assertRedirect();

        $bag = app(FieldValueResolver::class)->jobOrderBag($job->fresh(['analyses']), true);

        $this->assertSame('July 21, 2026 (10:00AM)', $bag['results.receipt_at']);
        $this->assertSame('July 29, 2026 (3:00PM)', $bag['results.analysis_datetime']);
        $this->assertSame($bag['results.analysis_datetime'], $bag['results.examination_datetime']);
        $this->assertNull($bag['results.release_date']);
        $this->assertNull($bag['results.report_date']);
    }

    public function test_receipt_and_examination_print_manila_time_not_utc(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-DW')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Timezone Customer',
            'customer_email' => 'timezone@example.com',
            'classification' => 'Potability',
            'sampling_date' => '2026-08-20',
            'sampling_time' => '00:05',
            'samples' => [
                ['description' => 'Kitchen faucet', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $lines = $job->analyses()->get();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $lines->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 225,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();

        $this->travelTo(Carbon::parse('2026-08-19 16:51:00', 'UTC'));

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $first = $job->analyses()->firstOrFail();
        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$first->id}/complete", [
                'result_value' => 'Passed',
            ])
            ->assertRedirect();

        $bag = app(FieldValueResolver::class)->jobOrderBag($job->fresh(['analyses']), true);

        $this->assertSame('August 20, 2026 (12:05AM)', $bag['results.collection_datetime']);
        $this->assertSame('August 20, 2026 (12:51AM)', $bag['results.receipt_at']);
        $this->assertSame('August 20, 2026 (12:51AM)', $bag['results.examination_datetime']);
        $this->assertNull($bag['results.report_date']);
        $this->assertNull($bag['results.release_date']);
    }

    public function test_sterile_bottle_field_data_prints_as_sample_description(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-DW')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Potability Customer',
            'customer_email' => 'potability@example.com',
            'classification' => 'Potability',
            'field_data' => 'Water in sterile bottle',
            'samples' => [
                [
                    'sample_code' => 'DW-12',
                    'description' => 'Kitchen faucet',
                    'matrix' => 'Liquid',
                ],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $bag = app(FieldValueResolver::class)->jobOrderBag($job, true);

        $this->assertTrue($bag['potability_sterile']);
        $this->assertSame('DW-12', $bag['results.sample_code']);
        $this->assertSame('Water in sterile bottle', $bag['results.sample_description']);
        $this->assertSame($job->reference_no, $bag['results.control_no']);
    }
}
