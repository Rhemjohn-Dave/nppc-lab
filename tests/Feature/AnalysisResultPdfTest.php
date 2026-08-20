<?php

namespace Tests\Feature;

use App\Enums\ControlledFormCategory;
use App\Enums\JobOrderAnalysisStatus;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class AnalysisResultPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyst_can_download_pdf_for_own_completed_analysis(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Result PDF Customer',
            'customer_email' => 'result-pdf@example.com',
            'customer_contact' => '09170000001',
            'samples' => [
                ['description' => 'Tap water', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 250, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '7.2',
                'result_unit' => 'pH',
                'result_remarks' => 'Within range',
            ])
            ->assertRedirect();

        $line->refresh();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $line->status);

        $this->actingAs($analyst)
            ->get("/analyst/tasks/{$line->id}/pdf")
            ->assertForbidden();

        $this->actingAs($analyst)
            ->get("/analyst/tasks/{$line->id}/pdf?inline=1")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_other_analyst_cannot_download_result_pdf(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Other Analyst Customer',
            'customer_email' => 'other-analyst@example.com',
            'customer_contact' => '09170000002',
            'samples' => [
                ['description' => 'Sample B', 'matrix' => 'Solid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 250, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '1.0',
            ])
            ->assertRedirect();

        $other = User::factory()->create([
            'name' => 'Other Analyst',
            'email' => 'other-lab-analyst@nppc.local',
        ]);
        $other->assignRole('analyst');

        $this->actingAs($other)
            ->get("/analyst/tasks/{$line->id}/pdf")
            ->assertForbidden();
    }

    public function test_admin_can_download_result_pdf(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Admin PDF Customer',
            'customer_email' => 'admin-pdf@example.com',
            'customer_contact' => '09170000003',
            'samples' => [
                ['description' => 'Sample C', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 250, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '12.5',
                'result_unit' => '%',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get("/analyst/tasks/{$line->id}/pdf?inline=1")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_individual_pdf_can_be_streamed_inline(): void
    {
        [$analyst, $line] = $this->createSingleCompletedAnalysis();

        $this->actingAs($analyst)
            ->get("/analyst/tasks/{$line->id}/pdf?inline=1")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_report_manifest_falls_back_to_individual_when_no_form_matches(): void
    {
        [$analyst, $line] = $this->createSingleCompletedAnalysis();

        $this->actingAs($analyst)
            ->getJson("/analyst/tasks/{$line->id}/report")
            ->assertOk()
            ->assertJsonPath('kind', 'individual')
            ->assertJsonPath('can_preview', true)
            ->assertJsonPath('can_print', false)
            ->assertJsonPath('pdf_url', "/analyst/tasks/{$line->id}/pdf?inline=1");
    }

    public function test_exact_combination_uses_combined_overlay_form_after_all_results_complete(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        [$first, $second] = $this->twoTypes();

        $this->createActiveOverlayResultForm($admin, [$first->id, $second->id]);

        $job = $this->createReceivedJob([$first->id, $second->id]);
        $firstLine = $job->analyses()->where('analysis_type_id', $first->id)->firstOrFail();
        $secondLine = $job->analyses()->where('analysis_type_id', $second->id)->firstOrFail();

        $this->actingAs($firstLine->fresh()->assignee ?? $analyst)
            ->post("/analyst/tasks/{$firstLine->id}/complete", [
                'result_value' => '7',
                'result_unit' => 'unit',
            ])
            ->assertRedirect();
        $this->actingAs($secondLine->fresh()->assignee ?? $analyst)
            ->post("/analyst/tasks/{$secondLine->id}/complete", [
                'result_value' => '8',
                'result_unit' => 'unit',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->getJson("/analyst/tasks/{$firstLine->id}/report")
            ->assertOk()
            ->assertJsonPath('kind', 'combined')
            ->assertJsonPath('can_preview', true)
            ->assertJsonPath('can_print', false)
            ->assertJsonPath('values.test_1_result', '7')
            ->assertJsonPath('values.test_2_result', '8')
            ->assertJsonPath('pdf_url', "/analyst/tasks/{$firstLine->id}/combined-pdf")
            ->assertJsonPath('template_url', '');

        $response = $this->actingAs($admin)
            ->get("/analyst/tasks/{$firstLine->id}/combined-pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->actingAs($analyst)
            ->get("/analyst/tasks/{$firstLine->id}/pdf")
            ->assertNotFound();
    }

    public function test_combined_report_waits_until_every_matched_result_is_complete(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        [$first, $second] = $this->twoTypes();

        $this->createActiveOverlayResultForm($admin, [$first->id, $second->id]);

        $job = $this->createReceivedJob([$first->id, $second->id]);
        $firstLine = $job->analyses()->where('analysis_type_id', $first->id)->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$firstLine->id}/complete", [
                'result_value' => '6.8',
            ])
            ->assertRedirect();

        $this->actingAs($analyst)
            ->getJson("/analyst/tasks/{$firstLine->id}/report")
            ->assertOk()
            ->assertJsonPath('kind', 'waiting')
            ->assertJsonPath('can_preview', false);
    }

    public function test_combined_report_is_unavailable_when_analyst_is_not_assigned_to_every_test(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $other = User::factory()->create([
            'name' => 'Second Analyst',
            'email' => 'analyst-b@nppc.local',
        ]);
        $other->assignRole('analyst');

        [$first, $second] = $this->twoTypes();
        $analyst->analysisTypes()->sync([$first->id]);
        $other->analysisTypes()->sync([$second->id]);

        $this->createActiveOverlayResultForm($admin, [$first->id, $second->id]);

        $job = $this->createReceivedJob([$first->id, $second->id]);
        $firstLine = $job->analyses()->where('analysis_type_id', $first->id)->firstOrFail();
        $secondLine = $job->analyses()->where('analysis_type_id', $second->id)->firstOrFail();
        $firstLine->update(['assigned_to' => $analyst->id]);
        $secondLine->update(['assigned_to' => $other->id]);

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$firstLine->id}/complete", ['result_value' => '1'])
            ->assertRedirect();
        $this->actingAs($other)
            ->post("/analyst/tasks/{$secondLine->id}/complete", ['result_value' => '2'])
            ->assertRedirect();

        $this->actingAs($analyst)
            ->getJson("/analyst/tasks/{$firstLine->id}/report")
            ->assertOk()
            ->assertJsonPath('kind', 'unavailable');

        $this->actingAs($admin)
            ->getJson("/analyst/tasks/{$firstLine->id}/report")
            ->assertOk()
            ->assertJsonPath('kind', 'combined');
    }

    public function test_package_signatory_can_preview_combined_form_before_sending_to_head(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $signatory = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->firstOrFail();

        $this->assertSame($signatory->id, $package->signatory_user_id);

        $this->createActiveOverlayResultForm($admin, [$total->id, $thermo->id]);

        $this->post('/intake/job-orders', [
            'customer_name' => 'Signatory Preview Customer',
            'customer_email' => 'signatory-preview@example.com',
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

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $job->refresh();
        foreach ($job->analyses()->with('assignee')->get() as $line) {
            $this->actingAs($line->assignee ?? $signatory)
                ->post("/analyst/tasks/{$line->id}/complete", [
                    'result_value' => 'Passed',
                ])
                ->assertRedirect();
        }

        $job->load('analyses.assignee');
        $previewLine = $job->analyses->firstOrFail();
        $other = $job->analyses
            ->first(fn ($line) => (int) $line->assigned_to !== (int) $signatory->id);

        $this->actingAs($signatory)
            ->getJson("/analyst/tasks/{$previewLine->id}/report")
            ->assertOk()
            ->assertJsonPath('kind', 'combined')
            ->assertJsonPath('can_preview', true)
            ->assertJsonPath('can_print', false);

        $this->actingAs($signatory)
            ->get("/analyst/tasks/{$previewLine->id}/combined-pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($signatory)
            ->get('/analyst')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->where('consolidations.0.can_preview', true)
                ->where('consolidations.0.can_submit', true)
                ->where('consolidations.0.preview_url', "/analyst/tasks/{$previewLine->id}/report"));

        if ($other) {
            $this->actingAs($other->assignee)
                ->getJson("/analyst/tasks/{$other->id}/report")
                ->assertOk()
                ->assertJsonPath('kind', 'unavailable');
        }
    }

    public function test_controlled_revision_file_is_protected_without_authorization(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $other = User::factory()->create([
            'name' => 'Other Analyst',
            'email' => 'analyst-c@nppc.local',
        ]);
        $other->assignRole('analyst');
        [$first, $second] = $this->twoTypes();

        $revision = $this->createActiveOverlayResultForm($admin, [$first->id, $second->id]);
        $job = $this->createReceivedJob([$first->id, $second->id]);
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($other)
            ->get("/analyst/controlled-revisions/{$revision->id}?analysis={$line->id}")
            ->assertForbidden();

        $this->actingAs($other)
            ->get("/analyst/controlled-revisions/{$revision->id}")
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: JobOrderAnalysis}
     */
    private function createSingleCompletedAnalysis(): array
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Single Result Customer',
            'customer_email' => 'single-result@example.com',
            'customer_contact' => '09170000999',
            'samples' => [
                ['description' => 'Tap water', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 250, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '7.2',
                'result_unit' => 'pH',
            ])
            ->assertRedirect();

        $line->refresh();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $line->status);

        return [$analyst, $line];
    }

    /**
     * @param  list<int>  $typeIds
     */
    private function createReceivedJob(array $typeIds): JobOrder
    {
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Combined Result Customer',
            'customer_email' => 'combined-result@example.com',
            'customer_contact' => '09170000888',
            'samples' => [
                ['description' => 'River water', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => $typeIds,
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $lines = $job->analyses()->get();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $lines->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 250,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        return $job->fresh(['analyses']) ?? $job;
    }

    /**
     * @param  list<int>  $typeIds
     */
    private function createActiveOverlayResultForm(User $admin, array $typeIds): ControlledFormRevision
    {
        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'NPPC-LAB-RES-001',
                'name' => 'Two-test result form',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'fill_mode' => 'overlay',
                'analysis_type_ids' => $typeIds,
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'NPPC-LAB-RES-001')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/fields", [
                'fields' => [
                    [
                        'name' => 'test_1_result',
                        'label' => 'Test 1 result',
                        'field_type' => 'text',
                        'page_number' => 1,
                        'x' => 20,
                        'y' => 40,
                        'width' => 40,
                        'height' => 6,
                        'font_size' => 10,
                        'data_source_key' => 'test_1_result',
                    ],
                    [
                        'name' => 'test_2_result',
                        'label' => 'Test 2 result',
                        'field_type' => 'text',
                        'page_number' => 1,
                        'x' => 20,
                        'y' => 50,
                        'width' => 40,
                        'height' => 6,
                        'font_size' => 10,
                        'data_source_key' => 'test_2_result',
                    ],
                ],
            ])
            ->assertRedirect();

        return $revision->fresh() ?? $revision;
    }

    /**
     * @return array{0: AnalysisType, 1: AnalysisType}
     */
    private function twoTypes(): array
    {
        $types = AnalysisType::query()
            ->where('result_mode', AnalysisType::RESULT_MODE_VALUE)
            ->orderBy('id')
            ->take(2)
            ->get();
        $this->assertCount(2, $types);

        return [$types[0], $types[1]];
    }

    private function makeBlankResultPdf(): UploadedFile
    {
        $pdf = new Fpdi('P', 'mm', [210, 297], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Text(20, 20, 'Blank official result sheet');
        $binary = $pdf->Output('', 'S');

        $path = tempnam(sys_get_temp_dir(), 'blk');
        file_put_contents($path, $binary);

        return new UploadedFile($path, 'blank-result.pdf', 'application/pdf', null, true);
    }
}
