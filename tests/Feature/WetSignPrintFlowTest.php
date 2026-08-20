<?php

namespace Tests\Feature;

use App\Enums\ControlledFormCategory;
use App\Enums\JobOrderStatus;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class WetSignPrintFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_result_print_waits_for_head_release_and_rfa_reprint_waits_for_review(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $head = User::where('email', 'head@nppc.local')->firstOrFail();
        $signatory = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'LSP-7.8-FO4-PRINT',
                'name' => 'Micro non-drinking water result',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '10',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'fill_mode' => 'overlay',
                'analysis_type_ids' => [$total->id, $thermo->id],
            ])
            ->assertRedirect();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Print Flow Customer',
            'customer_email' => 'print-flow@example.com',
            'samples' => [
                ['description' => 'Wastewater', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/print")
            ->assertForbidden();

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
            ->get("/receiving/{$job->id}/print")
            ->assertForbidden();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/print")
            ->assertForbidden();

        $this->actingAs($head)
            ->get("/head/{$job->id}/print")
            ->assertForbidden();

        foreach ($job->fresh()->analyses()->with('assignee')->get() as $line) {
            $this->actingAs($line->assignee ?? $signatory)
                ->post("/analyst/tasks/{$line->id}/complete", [
                    'result_value' => 'Passed',
                ])
                ->assertRedirect();
        }

        $previewLine = $job->analyses()->firstOrFail();

        $this->actingAs($signatory)
            ->getJson("/analyst/tasks/{$previewLine->id}/report")
            ->assertOk()
            ->assertJsonPath('can_preview', true)
            ->assertJsonPath('can_print', false);

        $this->actingAs($signatory)
            ->get("/analyst/tasks/{$previewLine->id}/combined-pdf")
            ->assertOk();

        $this->actingAs($signatory)
            ->get("/analyst/tasks/{$previewLine->id}/combined-pdf?print=1")
            ->assertForbidden();

        $this->actingAs($signatory)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review")
            ->assertRedirect();

        $this->actingAs($head)
            ->post("/head/{$job->id}/sign")
            ->assertRedirect('/head');

        $job->refresh();
        $this->assertSame(JobOrderStatus::ReadyForPickup, $job->status);
        $this->assertNotNull($job->reviewed_at);

        $this->actingAs($signatory)
            ->getJson("/analyst/tasks/{$previewLine->id}/report")
            ->assertOk()
            ->assertJsonPath('can_print', true)
            ->assertJsonPath('can_preview', true);

        $this->actingAs($signatory)
            ->get("/analyst/tasks/{$previewLine->id}/combined-pdf?print=1")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($signatory)
            ->get('/analyst')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->where('releasedPrints.0.can_print', true)
                ->where('releasedPrints.0.reference_no', $job->reference_no));

        $this->actingAs($receiving)
            ->get('/receiving?status=reviewed')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('receiving/index')
                ->where('counts.reviewed', 1)
                ->where('orders.data.0.reviewed', true));

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/print?copies=3")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('rfa/print')
                ->where('copies', 3));

        $this->actingAs($head)
            ->get("/head/{$job->id}/print")
            ->assertForbidden();
    }

    public function test_ready_for_pickup_follows_result_release_not_rfa_print(): void
    {
        Mail::fake();
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $head = User::where('email', 'head@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Pickup Status Customer',
            'customer_email' => 'pickup-status@example.com',
            'customer_contact' => '09170000000',
            'samples' => [
                ['description' => 'Sample', 'matrix' => 'Solid'],
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
            ])
            ->assertRedirect();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/print")
            ->assertForbidden();

        $this->assertNotSame(JobOrderStatus::ReadyForPickup, $job->fresh()->status);

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '1.0',
            ])
            ->assertRedirect();

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review")
            ->assertRedirect();

        $this->actingAs($head)
            ->post("/head/{$job->id}/sign")
            ->assertRedirect('/head');

        $this->assertSame(JobOrderStatus::ReadyForPickup, $job->fresh()->status);
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
