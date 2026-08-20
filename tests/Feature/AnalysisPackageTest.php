<?php

namespace Tests\Feature;

use App\Enums\ControlledFormCategory;
use App\Enums\JobOrderAnalysisStatus;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class AnalysisPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiosk_hides_package_member_tests_and_lists_the_package(): void
    {
        $this->seed();

        $this->get('/intake/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('intake/wizard')
                ->has('packages', 2)
                ->where('packages.0.code', 'PKG-MIC-NDW')
                ->where('packages.0.name', 'Microbiological Examination — Non-Drinking Water')
                ->has('packages.0.analysis_type_ids', 2)
                ->where('packages.1.code', 'PKG-MIC-DW')
                ->where('packages.1.name', 'Microbiological Examination — Drinking Water')
                ->has('packages.1.analysis_type_ids', 3)
                ->where('categories', function ($categories) {
                    $codes = collect($categories)
                        ->flatMap(fn ($group) => $group['items'])
                        ->pluck('code');

                    return ! $codes->contains('MB-02A')
                        && ! $codes->contains('MB-02B')
                        && ! $codes->contains('MB-02')
                        && $codes->contains('MB-01');
                })
            );
    }

    public function test_selecting_micro_non_drinking_package_creates_two_analyses_and_package_row(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Wastewater Customer',
            'customer_email' => 'ww@example.com',
            'classification' => 'Wastewater',
            'samples' => [
                ['description' => 'Wastewater', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();

        $this->assertTrue($job->packages()->where('analysis_packages.id', $package->id)->exists());
        $this->assertEqualsCanonicalizing(
            [$total->id, $thermo->id],
            $job->analyses()->pluck('analysis_type_id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['Total Coliform (MPN/100ml)', 'Thermotolerant Coliform (MPN/100ml)'],
            $job->analyses()->pluck('name')->all(),
        );
        $this->assertEquals(450, (float) $job->total_cost);
    }

    public function test_selecting_drinking_water_package_creates_three_analyses_and_package_row(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-DW')->firstOrFail();
        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->firstOrFail();
        $hpc = AnalysisType::query()->where('code', 'MB-01')->firstOrFail();

        $this->assertTrue($hpc->isPassFail());

        $this->post('/intake/job-orders', [
            'customer_name' => 'Potability Customer',
            'customer_email' => 'dw@example.com',
            'classification' => 'Potability',
            'samples' => [
                ['description' => 'Water in sterile bottle', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();

        $this->assertTrue($job->packages()->where('analysis_packages.id', $package->id)->exists());
        $this->assertSame(
            [$total->id, $thermo->id, $hpc->id],
            $job->analyses()->orderBy('id')->pluck('analysis_type_id')->all(),
        );
        $this->assertEquals(900, (float) $job->total_cost);
    }

    public function test_combined_result_form_matches_package_member_types(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'LSP-7.8-FO4',
                'name' => 'Micro non-drinking water result',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '10',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'fill_mode' => 'overlay',
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO4')->firstOrFail();
        $this->assertSame($package->id, $form->analysis_package_id);
        $this->assertNotNull($form->activeRevision());

        $this->post('/intake/job-orders', [
            'customer_name' => 'Combined Package Customer',
            'customer_email' => 'pkg-combined@example.com',
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

        $firstLine = $job->analyses()->where('analysis_type_id', $total->id)->firstOrFail();
        $secondLine = $job->analyses()->where('analysis_type_id', $thermo->id)->firstOrFail();

        foreach ([$firstLine, $secondLine] as $line) {
            $line->refresh();
            $worker = $line->assignee ?? $analyst;
            $payload = $line->id === $firstLine->id
                ? [
                    'result_value' => 'Passed',
                    'result_measurement' => '<1.8',
                    'result_unit' => 'MPN/100ml',
                ]
                : [
                    'result_value' => 'Failed',
                    'result_measurement' => '16',
                    'result_unit' => 'MPN/100ml',
                ];

            $this->actingAs($worker)
                ->post("/analyst/tasks/{$line->id}/complete", $payload)
                ->assertRedirect();
        }

        $this->assertSame(JobOrderAnalysisStatus::Completed, $firstLine->fresh()->status);
        $this->assertSame(JobOrderAnalysisStatus::Completed, $secondLine->fresh()->status);
        $this->assertSame('Passed', $firstLine->fresh()->result_value);
        $this->assertSame('<1.8', $firstLine->fresh()->result_measurement);
        $this->assertSame('Failed', $secondLine->fresh()->result_value);
        $this->assertSame('16', $secondLine->fresh()->result_measurement);

        $this->actingAs($admin)
            ->getJson("/analyst/tasks/{$firstLine->id}/report")
            ->assertOk()
            ->assertJsonPath('kind', 'combined')
            ->assertJsonPath('values.test_1_result', 'Passed')
            ->assertJsonPath('values.test_2_result', 'Failed')
            ->assertJsonPath('values.test_1_measurement', '<1.8')
            ->assertJsonPath('values.test_2_measurement', '16');
    }

    public function test_coliform_package_rejects_numeric_results(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Pass Fail Customer',
            'customer_email' => 'pf@example.com',
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

        $line = $job->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->from('/analyst')
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '<1.8',
            ])
            ->assertRedirect('/analyst')
            ->assertSessionHasErrors('result_value');
    }

    public function test_admin_can_create_a_package(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $types = AnalysisType::query()->whereIn('code', ['MB-01', 'MB-03'])->orderBy('code')->get();
        $this->assertCount(2, $types);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get('/admin/packages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/packages')
                ->has('packages', 2)
                ->has('analysisGroups')
                ->has('analysts')
            );

        $this->actingAs($admin)
            ->post('/admin/packages', [
                'code' => 'PKG-TEST',
                'name' => 'Custom kiosk bundle',
                'description' => 'Admin-created package',
                'default_price' => 100,
                'classifications' => ['Drinking Water'],
                'form_code' => 'TEST-FO1',
                'is_active' => true,
                'analysis_type_ids' => $types->pluck('id')->all(),
            ])
            ->assertRedirect('/admin/packages');

        $package = AnalysisPackage::query()->where('code', 'PKG-TEST')->firstOrFail();
        $this->assertSame('Custom kiosk bundle', $package->name);
        $this->assertEqualsCanonicalizing(
            $types->pluck('id')->all(),
            $package->orderedTypeIds(),
        );
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
