<?php

namespace Tests\Feature;

use App\Enums\AnalysisPackageReportLayout;
use App\Enums\ControlledFormCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\User;
use App\Support\Performance\PerformanceAuditor;
use App\Support\Performance\PerformanceMeasurement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

/**
 * Speed and redundancy guardrails for hot paths.
 *
 * Run: php artisan test --group=performance
 * Or:  php artisan nppc:performance-audit --seed
 */
#[Group('performance')]
class SystemPerformanceAuditTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceAuditor $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed();
        $this->auditor = app(PerformanceAuditor::class);
        $this->ensureControlledFormFixtures();
        $this->ensureDynamicMatrixFixture();
    }

    public function test_critical_paths_stay_within_performance_budgets(): void
    {
        $measurements = $this->auditor->run();
        $budgets = $this->auditor->budgets();
        $failures = [];

        foreach ($measurements as $measurement) {
            if ($measurement->skipped) {
                $failures[] = sprintf('%s was skipped: %s', $measurement->label, implode('; ', $measurement->warnings));

                continue;
            }

            $budget = $budgets[$measurement->key] ?? [];
            $violations = $measurement->violations($budget);

            if ($violations !== []) {
                $failures[] = sprintf(
                    "%s — %s",
                    $measurement->label,
                    implode('; ', $violations),
                );
            }
        }

        if ($failures !== []) {
            $this->fail("Performance budget violations:\n- ".implode("\n- ", $failures));
        }

        $this->assertNotEmpty($measurements);
    }

    public function test_dashboard_payloads_are_bounded(): void
    {
        $roles = [
            'admin@nppc.local',
            'receiving@nppc.local',
            'analyst@nppc.local',
            'head@nppc.local',
        ];

        foreach ($roles as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $payload = app(\App\Support\Dashboard\DashboardPayloadBuilder::class)->build($user);
            $bytes = strlen(json_encode($payload, JSON_THROW_ON_ERROR));

            $this->assertLessThan(
                200 * 1024,
                $bytes,
                "Dashboard payload for {$email} is larger than 200KB — trim Inertia props or paginate preview lists.",
            );
        }
    }

    public function test_no_excessive_duplicate_queries_on_dashboard(): void
    {
        /** @var PerformanceMeasurement|null $measurement */
        $measurement = $this->auditor->run()
            ->first(fn (PerformanceMeasurement $item) => $item->key === 'dashboard.admin');

        $this->assertNotNull($measurement);
        $this->assertFalse($measurement->skipped);

        $threshold = $this->auditor->duplicateQueryThreshold();
        foreach ($measurement->warnings as $warning) {
            if (str_contains($warning, 'Same SQL ran')) {
                $this->fail(
                    "Possible N+1 or redundant query on admin dashboard (threshold {$threshold}): {$warning}",
                );
            }
        }

        $this->assertTrue(true);
    }

    public function test_dynamic_matrix_preview_benchmark_is_available(): void
    {
        $this->assertNotNull(
            $this->auditor->matrixRevisionForAudit(),
            'Matrix fixture missing — ensureDynamicMatrixFixture should create one.',
        );

        $measurement = $this->auditor->run()
            ->first(fn (PerformanceMeasurement $item) => $item->key === 'service.dynamic_matrix.preview');

        $this->assertNotNull($measurement);
        $this->assertFalse($measurement->skipped, implode('; ', $measurement?->warnings ?? []));
    }

    private function ensureControlledFormFixtures(): void
    {
        $admin = User::query()->where('email', 'admin@nppc.local')->firstOrFail();

        if (ControlledForm::jobOrderForm()?->activeRevision()?->hasCanonicalPdf()) {
            return;
        }

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'PERF-AUDIT-RFA',
                'name' => 'Performance audit RFA',
                'category' => ControlledFormCategory::JobOrder->value,
                'revision' => '01',
                'file' => $this->makeBlankPdf('RFA'),
                'activate' => 1,
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'PERF-AUDIT-RFA')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/fields", [
                'fields' => [
                    [
                        'name' => 'customer_name',
                        'label' => 'Customer',
                        'field_type' => 'text',
                        'page_number' => 1,
                        'x' => 20,
                        'y' => 40,
                        'width' => 80,
                        'height' => 5,
                        'font_size' => 10,
                        'data_source_key' => 'job_orders.customer_name',
                    ],
                ],
            ])
            ->assertRedirect();
    }

    private function ensureDynamicMatrixFixture(): void
    {
        if ($this->auditor->matrixRevisionForAudit() !== null) {
            return;
        }

        $admin = User::query()->where('email', 'admin@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->firstOrFail();

        $package = AnalysisPackage::query()->create([
            'code' => 'PKG-PERF-MATRIX',
            'name' => 'Performance audit matrix',
            'default_price' => 100,
            'report_layout' => AnalysisPackageReportLayout::DynamicMatrix,
            'is_active' => true,
            'sort_order' => 999,
        ]);
        $package->syncTypes([$type->id]);

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'PERF-MATRIX-01',
                'name' => 'Performance matrix result',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankPdf('Matrix'),
                'activate' => 1,
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'PERF-MATRIX-01')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/fields", [
                'fields' => [
                    [
                        'name' => 'perf_matrix',
                        'label' => 'Results',
                        'field_type' => 'dynamic_test_matrix',
                        'page_number' => 1,
                        'x' => 15,
                        'y' => 50,
                        'width' => 180,
                        'height' => 80,
                        'font_size' => 8,
                        'table_config' => [
                            'columns' => [
                                ['key' => 'test', 'label' => 'TEST', 'width_pct' => 55],
                                ['key' => 'result', 'label' => 'Result', 'width_pct' => 25],
                                ['key' => 'remarks', 'label' => 'Remarks', 'width_pct' => 20],
                            ],
                            'row_height_mm' => 7,
                            'header_row' => true,
                            'border' => true,
                        ],
                    ],
                ],
            ])
            ->assertRedirect();
    }

    private function makeBlankPdf(string $label): UploadedFile
    {
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(40, 10, $label);
        $binary = $pdf->Output('', 'S');
        $path = tempnam(sys_get_temp_dir(), 'perf-audit-');
        file_put_contents($path, $binary);

        return new UploadedFile($path, 'perf-audit.pdf', 'application/pdf', null, true);
    }
}
