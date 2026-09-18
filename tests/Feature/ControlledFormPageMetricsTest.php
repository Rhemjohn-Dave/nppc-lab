<?php

namespace Tests\Feature;

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\User;
use App\Services\ControlledFormService;
use App\Services\ControlledFormStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class ControlledFormPageMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_revision_page_metrics_corrects_stale_dimensions(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::query()->where('email', 'admin@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->create([
            'form_code' => 'TEST-PAGE-METRICS',
            'name' => 'Page metrics test',
            'category' => ControlledFormCategory::Other,
            'department' => 'Laboratory',
        ]);

        $revision = ControlledFormRevision::query()->create([
            'controlled_form_id' => $form->id,
            'revision' => '1',
            'status' => ControlledFormRevisionStatus::Draft,
            'fill_mode' => ControlledFormRevision::FILL_MODE_OVERLAY,
            'created_by' => $admin->id,
            'page_width_mm' => 100.0,
            'page_height_mm' => 100.0,
            'page_count' => 9,
        ]);

        $upload = $this->makeBlankPdf(210.0, 297.0, 'A4 metrics');
        app(ControlledFormService::class)->attachFile($form, $revision, $upload);
        $revision = $revision->fresh();

        $this->assertNotNull($revision);
        $this->assertTrue($revision->hasCanonicalPdf());

        // Intentionally drift again after attach.
        $revision->page_width_mm = 100.0;
        $revision->page_height_mm = 100.0;
        $revision->page_count = 9;
        $revision->save();

        $synced = app(ControlledFormStorage::class)->syncRevisionPageMetrics($revision->fresh());
        $revision = $revision->fresh();

        $this->assertNotNull($synced);
        $this->assertEqualsWithDelta(210.0, $synced['width_mm'], 0.6);
        $this->assertEqualsWithDelta(297.0, $synced['height_mm'], 0.6);
        $this->assertSame(1, $synced['page_count']);
        $this->assertEqualsWithDelta(210.0, (float) $revision->page_width_mm, 0.6);
        $this->assertEqualsWithDelta(297.0, (float) $revision->page_height_mm, 0.6);
        $this->assertSame(1, (int) $revision->page_count);
    }

    public function test_designer_exposes_canonical_page_from_fpdi_metrics(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::query()->where('email', 'admin@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->create([
            'form_code' => 'TEST-DESIGNER-PAGE',
            'name' => 'Designer page metrics',
            'category' => ControlledFormCategory::Other,
            'department' => 'Laboratory',
        ]);

        $revision = ControlledFormRevision::query()->create([
            'controlled_form_id' => $form->id,
            'revision' => '1',
            'status' => ControlledFormRevisionStatus::Draft,
            'fill_mode' => ControlledFormRevision::FILL_MODE_OVERLAY,
            'created_by' => $admin->id,
            'page_width_mm' => 50.0,
            'page_height_mm' => 50.0,
            'page_count' => 3,
        ]);

        app(ControlledFormService::class)->attachFile(
            $form,
            $revision,
            $this->makeBlankPdf(215.9, 279.4, 'Letter metrics'),
        );
        $revision = $revision->fresh();

        // Stale DB values — designer must sync before rendering props.
        $revision->page_width_mm = 50.0;
        $revision->page_height_mm = 50.0;
        $revision->page_count = 3;
        $revision->save();

        $response = $this->actingAs($admin)
            ->get("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/designer");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/form-designer')
            ->has('canonical_page')
            ->where('canonical_page.width_mm', fn ($v) => abs((float) $v - 215.9) < 0.6)
            ->where('canonical_page.height_mm', fn ($v) => abs((float) $v - 279.4) < 0.6)
            ->where('canonical_page.page_count', 1)
        );

        $revision->refresh();
        $this->assertEqualsWithDelta(215.9, (float) $revision->page_width_mm, 0.6);
        $this->assertEqualsWithDelta(279.4, (float) $revision->page_height_mm, 0.6);
        $this->assertSame(1, (int) $revision->page_count);
    }

    public function test_page_metrics_matches_official_milk_pdf(): void
    {
        $path = resource_path('forms/official/Milk Sample Test Result Form.pdf');
        if (! is_file($path)) {
            $this->markTestSkipped('Official Milk PDF is not present.');
        }

        $metrics = app(ControlledFormStorage::class)->pageMetrics($path);

        $this->assertEqualsWithDelta(215.9, $metrics['width_mm'], 0.6);
        $this->assertEqualsWithDelta(279.4, $metrics['height_mm'], 0.6);
        $this->assertSame(2, $metrics['page_count']);

        $blueprint = config('result_milk_form_fields.page');
        $this->assertEqualsWithDelta(
            (float) $blueprint['width'],
            $metrics['width_mm'],
            0.6,
            'Milk blueprint page width must match FPDI metrics of the official PDF.',
        );
        $this->assertEqualsWithDelta(
            (float) $blueprint['height'],
            $metrics['height_mm'],
            0.6,
            'Milk blueprint page height must match FPDI metrics of the official PDF.',
        );
    }

    private function makeBlankPdf(float $widthMm, float $heightMm, string $label): UploadedFile
    {
        $pdf = new Fpdi('P', 'mm', [$widthMm, $heightMm], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Text(20, 20, $label);
        $binary = $pdf->Output('', 'S');

        $path = tempnam(sys_get_temp_dir(), 'cfm');
        file_put_contents($path, $binary);

        return new UploadedFile($path, 'metrics-test.pdf', 'application/pdf', null, true);
    }
}
