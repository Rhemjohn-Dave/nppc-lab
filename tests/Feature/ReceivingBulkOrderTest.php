<?php

namespace Tests\Feature;

use App\Enums\JobOrderStatus;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\FieldValueResolver;
use App\Support\SampleControlNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceivingBulkOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_can_create_bulk_order_with_shared_tests_and_attachment(): void
    {
        Storage::fake('local');
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('is_active', true)->where('show_on_kiosk', true)->firstOrFail();

        $response = $this->actingAs($receiving)
            ->post('/receiving/bulk-orders', [
                'customer_name' => 'Bayawan Water District',
                'customer_email' => 'bulk@example.com',
                'classification' => 'Potability',
                'see_attached_file' => true,
                'analysis_type_ids' => [$type->id],
                'samples' => [
                    [
                        'sample_code' => 'A',
                        'description' => 'KALAMTUKAN SOURCE',
                        'location' => 'BRGY. KALAMTUKAN',
                        'sample_point' => 'SPRING',
                        'collected_time' => '12:04 PM',
                        'matrix' => 'Water',
                    ],
                    [
                        'sample_code' => 'B',
                        'description' => '',
                        'location' => 'BRGY. KALAMTUKAN',
                        'sample_point' => 'TAP, CHLORINATED',
                        'collected_time' => '12:13 PM',
                        'matrix' => 'Water',
                    ],
                    [
                        'sample_code' => 'C',
                        'description' => 'VILLASOL SOURCE',
                        'location' => 'BRGY. VILLASOL',
                        'sample_point' => 'SPRING',
                        'matrix' => 'Water',
                    ],
                ],
                'attachment' => UploadedFile::fake()->create('sample-list.pdf', 120, 'application/pdf'),
            ]);

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('receiving.show', $job));

        $job->load(['samples', 'analyses']);
        $this->assertTrue($job->is_receiving_bulk);
        $this->assertFalse((bool) $job->is_bulk_draft);
        $this->assertCount(3, $job->samples);
        $this->assertCount(3, $job->analyses);
        $this->assertTrue($job->see_attached_file);
        $this->assertSame('KALAMTUKAN SOURCE', $job->samples[0]->description);
        $this->assertTrue(blank($job->samples[1]->description));
        $this->assertSame('VILLASOL SOURCE', $job->samples[2]->description);
        $this->assertStringContainsString('Location: BRGY. KALAMTUKAN', (string) $job->samples[0]->remarks);
        $this->assertNotNull($job->attachment_path);
        $this->assertSame('sample-list.pdf', $job->attachment_original_name);
        Storage::disk('local')->assertExists($job->attachment_path);

        $bag = app(FieldValueResolver::class)->jobOrderBag($job->fresh('samples'), false);
        $this->assertSame('see attached file', $bag['sample_code_1']);
        $this->assertNull($bag['sample_code_2']);
        $this->assertSame($job->reference_no, $bag['control_number_1']);
        $this->assertNull($bag['control_number_2']);
        $this->assertCount(1, $bag['samples[]']);
        $this->assertSame('see attached file', $bag['samples[]'][0]['description']);
        $this->assertSame($job->reference_no, $bag['samples[]'][0]['control_number']);

        // Result sheets still use letter suffixes per sample.
        $this->assertSame(
            $job->reference_no.'A',
            SampleControlNumber::forIndex($job->reference_no, 0, 3),
        );
        $this->assertSame(
            $job->reference_no.'C',
            SampleControlNumber::forIndex($job->reference_no, 2, 3),
        );

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->firstOrFail();
        $revision = $form->activeRevision() ?? $form->revisions()->firstOrFail();
        $revision->setRelation('fields', collect());
        $resolver = app(FieldValueResolver::class);
        $jobForResult = $job->fresh(['analyses', 'packages', 'samples']);

        $bagA = $resolver->forResult($revision, $jobForResult, null, $form, $jobForResult->samples[0]);
        $this->assertSame('KALAMTUKAN SOURCE', $bagA['results.customer']);

        $bagBlank = $resolver->forResult($revision, $jobForResult, null, $form, $jobForResult->samples[1]);
        $this->assertSame('Bayawan Water District', $bagBlank['results.customer']);

        $bagC = $resolver->forResult($revision, $jobForResult, null, $form, $jobForResult->samples[2]);
        $this->assertSame('VILLASOL SOURCE', $bagC['results.customer']);

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/attachment")
            ->assertOk();
    }

    public function test_bulk_orders_index_keeps_customer_and_company_names(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('is_active', true)->where('show_on_kiosk', true)->firstOrFail();

        $this->actingAs($receiving)
            ->post('/receiving/bulk-orders', [
                'customer_name' => 'Juan Requestor',
                'company_name' => 'Bayawan Water District',
                'classification' => 'Potability',
                'analysis_type_ids' => [$type->id],
                'samples' => [
                    ['description' => 'SOURCE A', 'matrix' => 'Water'],
                    ['description' => 'SOURCE B', 'matrix' => 'Water'],
                ],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();

        $this->actingAs($receiving)
            ->get('/receiving/bulk-orders?tab=submitted')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('receiving/bulk-orders')
                ->where('orders.data.0.id', $job->id)
                ->where('orders.data.0.customer_name', 'Juan Requestor')
                ->where('orders.data.0.company_name', 'Bayawan Water District'));
    }

    public function test_bulk_order_requires_at_least_two_samples(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($receiving)
            ->post('/receiving/bulk-orders', [
                'customer_name' => 'One Sample',
                'classification' => 'Potability',
                'analysis_type_ids' => [$type->id],
                'samples' => [
                    [
                        'description' => 'Only one',
                        'matrix' => 'Water',
                    ],
                ],
            ])
            ->assertSessionHasErrors('samples');
    }

    public function test_receiving_can_save_and_resume_bulk_draft(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('is_active', true)->where('show_on_kiosk', true)->firstOrFail();

        $save = $this->actingAs($receiving)
            ->post('/receiving/bulk-orders/draft', [
                'customer_name' => 'Bayawan Water District',
                'classification' => 'Potability',
                'samples' => [
                    [
                        'description' => 'SOURCE A',
                        'location' => 'BRGY. ONE',
                        'matrix' => 'Water',
                    ],
                ],
            ]);

        $draft = JobOrder::query()->latest('id')->firstOrFail();
        $save->assertRedirect(route('receiving.bulk-orders.edit', $draft));
        $this->assertTrue($draft->is_bulk_draft);
        $this->assertTrue($draft->is_receiving_bulk);
        $this->assertCount(1, $draft->samples);
        $this->assertCount(0, $draft->analyses);

        $this->actingAs($receiving)
            ->get(route('receiving.bulk-orders.edit', $draft))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('receiving/bulk-create')
                ->where('draft.id', $draft->id)
                ->where('draft.customer_name', 'Bayawan Water District'));

        $this->actingAs($receiving)
            ->get('/receiving/bulk-orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('receiving/bulk-orders')
                ->where('filters.tab', 'drafts')
                ->where('orders.data.0.id', $draft->id)
                ->where('orders.data.0.is_bulk_draft', true)
                ->where('orders.data.0.continue_url', "/receiving/bulk-orders/{$draft->id}/edit"));

        $this->actingAs($receiving)
            ->get('/receiving?q='.urlencode((string) $draft->reference_no))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('orders.data', 0));

        $finish = $this->actingAs($receiving)
            ->post('/receiving/bulk-orders', [
                'draft_id' => $draft->id,
                'customer_name' => 'Bayawan Water District',
                'classification' => 'Potability',
                'analysis_type_ids' => [$type->id],
                'samples' => [
                    [
                        'description' => 'SOURCE A',
                        'location' => 'BRGY. ONE',
                        'matrix' => 'Water',
                    ],
                    [
                        'description' => 'SOURCE B',
                        'location' => 'BRGY. TWO',
                        'matrix' => 'Water',
                    ],
                ],
            ]);

        $job = $draft->fresh(['samples', 'analyses']);
        $finish->assertRedirect(route('receiving.show', $job));
        $this->assertFalse((bool) $job->is_bulk_draft);
        $this->assertTrue($job->is_receiving_bulk);
        $this->assertCount(2, $job->samples);
        $this->assertCount(2, $job->analyses);
    }

    public function test_intake_multi_sample_is_not_receiving_bulk(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('is_active', true)->where('show_on_kiosk', true)->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Intake Person',
            'company_name' => 'Intake Company LLC',
            'customer_email' => 'intake-multi@example.com',
            'classification' => 'Potability',
            'samples' => [
                [
                    'description' => 'Sample One',
                    'matrix' => 'Water',
                    'analysis_type_ids' => [$type->id],
                ],
                [
                    'description' => 'Sample Two',
                    'matrix' => 'Water',
                    'analysis_type_ids' => [$type->id],
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail()->load('samples');
        $this->assertFalse((bool) $job->is_receiving_bulk);
        $this->assertFalse((bool) $job->is_bulk_draft);
        $this->assertCount(2, $job->samples);

        $this->actingAs($receiving)
            ->get('/receiving/bulk-orders?tab=submitted')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('receiving/bulk-orders')
                ->where('counts.submitted', 0)
                ->has('orders.data', 0));

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/samples")
            ->assertNotFound();

        $this->actingAs($receiving)
            ->get("/receiving?q=".urlencode((string) $job->reference_no))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('orders.data.0.id', $job->id)
                ->where('orders.data.0.is_bulk', false)
                ->where('orders.data.0.can_encode_samples', false));

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->firstOrFail();
        $revision = $form->activeRevision() ?? $form->revisions()->firstOrFail();
        $revision->setRelation('fields', collect());
        $resolver = app(FieldValueResolver::class);
        $jobForResult = $job->fresh(['analyses', 'packages', 'samples']);

        $bagA = $resolver->forResult($revision, $jobForResult, null, $form, $jobForResult->samples[0]);
        $this->assertSame('Intake Company LLC', $bagA['results.customer']);

        $bagB = $resolver->forResult($revision, $jobForResult, null, $form, $jobForResult->samples[1]);
        $this->assertSame('Intake Company LLC', $bagB['results.customer']);
    }

    public function test_bulk_can_price_with_blank_names_then_encode_before_send(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('is_active', true)->where('show_on_kiosk', true)->firstOrFail();

        $this->actingAs($receiving)
            ->post('/receiving/bulk-orders', [
                'customer_name' => 'Price First Customer',
                'classification' => 'Potability',
                'see_attached_file' => true,
                'analysis_type_ids' => [$type->id],
                'samples' => [
                    ['description' => '', 'matrix' => 'Water'],
                    ['description' => '', 'matrix' => 'Water'],
                    ['description' => '', 'matrix' => 'Water'],
                ],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $job->load(['samples', 'analyses']);
        $this->assertCount(3, $job->samples);
        $this->assertTrue($job->samples->every(fn ($sample) => blank($sample->description)));

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/samples")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('receiving/encode-samples')
                ->where('jobOrder.samples_named_count', 0)
                ->where('jobOrder.samples_count', 3));

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $job->analyses->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 100,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();

        $job->refresh();
        $this->assertSame(JobOrderStatus::PendingJoApproval, $job->status);

        $this->approveJobOrder($job);

        $job->refresh();
        $this->assertSame(JobOrderStatus::JoApproved, $job->status);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertSessionHasErrors('samples');

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/samples", [
                'samples' => $job->samples->values()->map(fn ($sample, int $index) => [
                    'id' => $sample->id,
                    'description' => 'SOURCE '.chr(65 + $index),
                    'location' => 'Site '.$index,
                    'sample_point' => 'Tap',
                    'collected_time' => '10:0'.$index,
                    'matrix' => 'Water',
                ])->all(),
            ])
            ->assertRedirect(route('receiving.samples.edit', $job));

        $job->load('samples');
        $this->assertSame('SOURCE A', $job->samples[0]->description);
        $this->assertSame('SOURCE C', $job->samples[2]->description);
        $this->assertStringContainsString('Location: Site 0', (string) $job->samples[0]->remarks);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect(route('receiving.index'));

        $job->refresh();
        $this->assertSame(JobOrderStatus::InAnalysis, $job->status);

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/samples")
            ->assertNotFound();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/samples", [
                'samples' => $job->samples->values()->map(fn ($sample) => [
                    'id' => $sample->id,
                    'description' => 'Blocked',
                    'matrix' => 'Water',
                ])->all(),
            ])
            ->assertNotFound();
    }
}
