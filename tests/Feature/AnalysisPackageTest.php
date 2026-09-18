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
                ->has('packages')
                ->where('packages', function ($packages) {
                    $codes = collect($packages)->pluck('code');

                    return $codes->contains('PKG-MIC-NDW')
                        && $codes->contains('PKG-DW-BACT')
                        && $codes->contains('PKG-DW-PHYSICO')
                        && ! $codes->contains('PKG-MIC-DW')
                        && ! $codes->contains('PKG-AQUA-WATER')
                        && ! $codes->contains('PKG-PROXIMATE');
                })
                ->where('categories', function ($categories) {
                    $codes = collect($categories)
                        ->flatMap(fn ($group) => $group['items'])
                        ->pluck('code');

                    return ! $codes->contains('MB-02A')
                        && ! $codes->contains('MB-02B')
                        && ! $codes->contains('DW-BACT-HPC')
                        && $codes->contains('MB-01')
                        && $codes->contains('AQ-W-01')
                        && $codes->contains('WW-01');
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
        $this->assertEquals(750, (float) $job->total_cost);
    }

    public function test_selecting_drinking_water_bact_package_creates_three_analyses(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-DW-BACT')->firstOrFail();
        $this->assertTrue($package->is_active);
        $this->assertEquals(300, (float) $package->default_price);

        $hpc = AnalysisType::query()->where('code', 'DW-BACT-HPC')->firstOrFail();
        $tc = AnalysisType::query()->where('code', 'DW-BACT-TC')->firstOrFail();
        $fc = AnalysisType::query()->where('code', 'DW-BACT-FC')->firstOrFail();

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
        $this->assertEqualsCanonicalizing(
            [$hpc->id, $tc->id, $fc->id],
            $job->analyses()->pluck('analysis_type_id')->all(),
        );
        $this->assertEquals(300, (float) $job->total_cost);
    }

    public function test_legacy_mic_dw_package_is_inactive(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-DW')->firstOrFail();
        $this->assertFalse($package->is_active);

        $fo5 = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->firstOrFail();
        $this->assertNull($fo5->analysis_package_id);
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

        $existing = ControlledForm::query()->where('form_code', 'LSP-7.8-FO4')->first();
        if ($existing) {
            foreach ($existing->revisions as $revision) {
                $revision->fields()->delete();
                $revision->delete();
            }
            $existing->delete();
        }

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

        $this->approveJobOrder($job);

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
                    'result_value' => 'LT 1.8',
                    'result_unit' => 'MPN/100ml',
                ]
                : [
                    'result_value' => '16',
                    'result_unit' => 'MPN/100ml',
                ];

            $this->actingAs($worker)
                ->post("/analyst/tasks/{$line->id}/complete", $payload)
                ->assertRedirect();
        }

        $this->assertSame(JobOrderAnalysisStatus::Completed, $firstLine->fresh()->status);
        $this->assertSame(JobOrderAnalysisStatus::Completed, $secondLine->fresh()->status);
        $this->assertSame('LT 1.8', $firstLine->fresh()->result_value);
        $this->assertNull($firstLine->fresh()->result_pass_fail);
        $this->assertSame('16', $secondLine->fresh()->result_value);
        $this->assertNull($secondLine->fresh()->result_pass_fail);

        $report = $this->actingAs($admin)
            ->getJson("/analyst/tasks/{$firstLine->id}/report")
            ->assertOk()
            ->assertJsonPath('kind', 'combined')
            ->assertJsonPath('values', [])
            ->assertJsonPath('pdf_url', "/analyst/tasks/{$firstLine->id}/combined-pdf");

        $resolved = app(\App\Services\AnalysisResultReportResolver::class)
            ->forJobOrder($job->fresh(['analyses.assignee', 'analyses.analysisType', 'packages']), $admin);
        $values = $resolved->values;
        // FO4 maps test_N_result to measured MPN (no Pass/Fail on this sheet).
        $this->assertSame('LT 1.8', $values['test_1_result'] ?? null);
        $this->assertSame('16', $values['test_2_result'] ?? null);

        $revision = $form->fresh()->activeRevision();
        $this->assertNotNull($revision);
        $revision->setRelation('fields', collect());
        $bag = app(\App\Services\FieldValueResolver::class)->forResult(
            $revision,
            $job->fresh(['analyses.assignee', 'analyses.analysisType', 'packages', 'samples']),
            $resolved->analyses,
            $form->fresh(['analysisTypes', 'analysisPackage.analysisTypes']),
        );
        $this->assertSame('LT 1.8', $bag['test_1_result'] ?? null);
        $this->assertSame('LT 1.8', $bag['test_1_measurement'] ?? null);
        $this->assertSame('16', $bag['test_2_result'] ?? null);
        $this->assertSame('16', $bag['test_2_measurement'] ?? null);
        $this->assertSame(
            'Multiple Tube Fermentation Technique* 9221, SMEWW',
            $bag['test_1_method'] ?? null,
        );
        $this->assertNotEmpty($report->json('pdf_url'));
    }

    public function test_fo4_complete_skips_pass_fail_and_method_requirement(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $existing = ControlledForm::query()->where('form_code', 'LSP-7.8-FO4')->first();
        if ($existing) {
            foreach ($existing->revisions as $revision) {
                $revision->fields()->delete();
                $revision->delete();
            }
            $existing->delete();
        }

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

        $this->post('/intake/job-orders', [
            'customer_name' => 'FO4 Encode Customer',
            'customer_email' => 'fo4-encode@example.com',
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

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $line = $job->analyses()->firstOrFail();
        $this->assertFalse(
            app(\App\Services\AnalysisResultReportResolver::class)->requiresPassFail($job),
        );

        $this->actingAs($analyst)
            ->from('/analyst')
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '<1.8',
            ])
            ->assertRedirect('/analyst')
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('<1.8', $line->fresh()->result_value);
        $this->assertNull($line->fresh()->result_pass_fail);
    }

    public function test_fo5_complete_still_requires_pass_fail(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-DW')->firstOrFail();
        $package->update(['is_active' => true]);
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $existing = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->first();
        if ($existing) {
            foreach ($existing->revisions as $revision) {
                $revision->fields()->delete();
                $revision->delete();
            }
            $existing->delete();
        }

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'LSP-7.8-FO5',
                'name' => 'FO5 drinking water',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $this->post('/intake/job-orders', [
            'customer_name' => 'FO5 Encode Customer',
            'customer_email' => 'fo5-encode@example.com',
            'classification' => 'Potability',
            'samples' => [
                ['description' => 'Drinking water', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $lines = $job->analyses()->get();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $lines->map(fn ($line) => [
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

        $line = $job->analyses()->firstOrFail();
        $this->assertTrue(
            app(\App\Services\AnalysisResultReportResolver::class)->requiresPassFail($job),
        );

        $this->actingAs($analyst)
            ->from('/analyst')
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '<1.1',
            ])
            ->assertRedirect('/analyst')
            ->assertSessionHasErrors(['result_pass_fail'])
            ->assertSessionDoesntHaveErrors(['result_method']);
    }

    public function test_admin_can_create_a_package(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $types = AnalysisType::query()->whereIn('code', ['FM-01', 'FM-02'])->orderBy('code')->get();
        $this->assertCount(2, $types);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get('/admin/packages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/packages')
                ->has('packages')
                ->has('analysisGroups')
                ->has('analysts')
            );

        $this->actingAs($admin)
            ->post('/admin/packages', [
                'code' => 'PKG-TEST',
                'name' => 'Custom kiosk bundle',
                'description' => 'Admin-created package',
                'default_price' => 100,
                'classifications' => ['Potability'],
                'form_code' => 'TEST-FO1',
                'report_layout' => 'controlled_form',
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

    public function test_admin_can_delete_unused_package(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-PROXIMATE')->firstOrFail();

        $this->actingAs($admin)
            ->delete("/admin/packages/{$package->id}")
            ->assertRedirect('/admin/packages')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('analysis_packages', ['id' => $package->id]);
    }

    public function test_admin_can_delete_package_even_when_used_on_job_orders(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Keep Analyses Customer',
            'customer_email' => 'keep-analyses@example.com',
            'classification' => 'Wastewater',
            'samples' => [
                ['description' => 'Effluent', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->where('analysis_packages.id', $package->id)->exists());
        $analysisCount = $job->analyses()->count();
        $this->assertGreaterThan(0, $analysisCount);

        $this->actingAs($admin)
            ->delete("/admin/packages/{$package->id}")
            ->assertRedirect('/admin/packages')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('analysis_packages', ['id' => $package->id]);
        $this->assertSame($analysisCount, $job->fresh()->analyses()->count());
        $this->assertTrue($job->fresh()->packages()->doesntExist());
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
