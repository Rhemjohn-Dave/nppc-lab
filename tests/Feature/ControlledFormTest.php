<?php

namespace Tests\Feature;

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Enums\JobOrderStatus;
use App\Exceptions\ObsoleteFormRevisionException;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\ControlledDocumentGenerator;
use App\Services\ControlledFormService;
use App\Services\DocxToPdfConverter;
use App\Services\FieldValueResolver;
use App\Services\RevisionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class ControlledFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_form_design_two_fields_and_generate_from_job_order(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $form = ControlledForm::query()
            ->where('form_code', ControlledForm::RFA_FORM_CODE)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/controlled-forms/{$form->id}/revisions", [
                'revision' => '03',
                'effective_date' => '2026-08-01',
                'file' => $this->makeBlankFolioPdf(),
                'activate' => 1,
            ])
            ->assertRedirect();

        $form->refresh();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);
        $this->assertTrue($revision->hasCanonicalPdf());
        $this->assertSame(ControlledFormRevisionStatus::Active, $revision->status);

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/fields", [
                'fields' => [
                    [
                        'name' => 'customer_name',
                        'label' => 'Customer Name',
                        'field_type' => 'text',
                        'page_number' => 1,
                        'x' => 38,
                        'y' => 48,
                        'width' => 100,
                        'height' => 4.5,
                        'font_size' => 8,
                        'data_source_key' => 'job_orders.customer_name',
                    ],
                    [
                        'name' => 'reference_no',
                        'label' => 'Reference Number',
                        'field_type' => 'text',
                        'page_number' => 1,
                        'x' => 165,
                        'y' => 22,
                        'width' => 35,
                        'height' => 5,
                        'font_size' => 11,
                        'data_source_key' => 'job_orders.reference_no',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(2, $revision->fields()->count());
        $this->assertTrue(
            ControlledFormField::query()->where('data_source_key', 'job_orders.customer_name')->exists(),
        );

        $job = JobOrder::query()->create([
            'reference_no' => '26-7999',
            'customer_name' => 'ABC Corporation',
            'customer_address' => 'Bacolod',
            'customer_contact' => '09170000000',
            'status' => JobOrderStatus::ReadyForPickup,
            'reviewed_at' => now(),
            'total_cost' => 250,
        ]);

        $response = $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertDatabaseCount('generated_documents', 0);
    }

    public function test_rfa_pdf_requires_active_controlled_form_canonical_pdf(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $form = ControlledForm::query()
            ->where('form_code', ControlledForm::RFA_FORM_CODE)
            ->firstOrFail();

        foreach ($form->revisions as $revision) {
            $revision->update([
                'canonical_pdf_path' => null,
                'canonical_pdf_sha256' => null,
            ]);
        }

        $job = JobOrder::query()->create([
            'reference_no' => '26-NOCAN',
            'customer_name' => 'No Canonical Corp',
            'customer_address' => 'Bacolod',
            'status' => JobOrderStatus::ReadyForPickup,
            'reviewed_at' => now(),
            'total_cost' => 0,
        ]);

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/pdf")
            ->assertStatus(422);
    }

    public function test_superseded_revision_cannot_generate_new_official_document(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();

        $form = ControlledForm::query()
            ->where('form_code', ControlledForm::RFA_FORM_CODE)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/controlled-forms/{$form->id}/revisions", [
                'revision' => '02',
                'file' => $this->makeBlankFolioPdf(),
                'activate' => 1,
            ])
            ->assertRedirect();

        $form->refresh();
        $old = $form->activeRevision();
        $this->assertNotNull($old);

        $this->actingAs($admin)
            ->post("/admin/controlled-forms/{$form->id}/revisions", [
                'revision' => '03',
                'file' => $this->makeBlankFolioPdf(),
                'activate' => 1,
            ])
            ->assertRedirect();

        $old->refresh();
        $this->assertSame(ControlledFormRevisionStatus::Superseded, $old->status);

        $job = JobOrder::query()->create([
            'reference_no' => '26-8000',
            'customer_name' => 'Obsolete Customer',
            'status' => JobOrderStatus::Priced,
            'total_cost' => 0,
        ]);

        try {
            app(ControlledDocumentGenerator::class)->fromJobOrder(
                $job,
                $admin,
                false,
                true,
                $old,
            );
            $this->fail('Expected obsolete revision to be rejected.');
        } catch (ObsoleteFormRevisionException $e) {
            $payload = $e->payload();
            $this->assertSame('NPPC-LAB-FRM-001', $payload['form_code']);
            $this->assertSame('02', $payload['selected_revision']);
            $this->assertSame('03', $payload['active_revision']);
        }
    }

    public function test_non_admin_cannot_open_designer(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'NPPC-LAB-FRM-009',
                'name' => 'Internal form',
                'category' => ControlledFormCategory::Other->value,
                'revision' => '01',
                'file' => $this->makeBlankFolioPdf(),
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'NPPC-LAB-FRM-009')->firstOrFail();
        $revision = $form->revisions()->firstOrFail();

        $this->actingAs($analyst)
            ->get("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/designer")
            ->assertForbidden();
    }

    public function test_admin_can_upload_pdf_with_unsupported_fpdi_compression(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $fixture = base_path('tests/fixtures/pdfs/object-streams.pdf');
        $this->assertFileExists($fixture);

        $copy = tempnam(sys_get_temp_dir(), 'cff').'.pdf';
        copy($fixture, $copy);

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'NPPC-LAB-FRM-COMP',
                'name' => 'Compressed official form',
                'category' => ControlledFormCategory::Other->value,
                'revision' => '01',
                'file' => new UploadedFile($copy, 'official-form.pdf', 'application/pdf', null, true),
                'activate' => 1,
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'NPPC-LAB-FRM-COMP')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);
        $canonical = Storage::disk('local')->path($revision->canonical_pdf_path);
        $this->assertFileExists($canonical);

        $pdf = new Fpdi('P', 'mm');
        $this->assertSame(1, $pdf->setSourceFile($canonical));
    }

    public function test_docx_upload_is_rejected_when_libreoffice_is_missing(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();

        if (app(DocxToPdfConverter::class)->isAvailable()) {
            $this->markTestSkipped('LibreOffice is installed in this environment.');
        }

        $path = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($path, 'not-a-real-docx');
        $file = new UploadedFile($path, 'form.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $this->actingAs($admin)
            ->from('/admin/controlled-forms')
            ->post('/admin/controlled-forms', [
                'form_code' => 'NPPC-LAB-FRM-DOCX',
                'name' => 'DOCX form',
                'category' => ControlledFormCategory::Other->value,
                'revision' => '01',
                'file' => $file,
            ])
            ->assertRedirect('/admin/controlled-forms')
            ->assertSessionHasErrors('file');
    }

    public function test_activating_revision_supersedes_previous_active(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->create([
            'form_code' => 'NPPC-LAB-FRM-010',
            'name' => 'Workflow form',
            'category' => ControlledFormCategory::Other,
        ]);

        $first = $this->revisionWithPdf($form, '01', $admin);
        $second = $this->revisionWithPdf($form, '02', $admin);

        app(RevisionWorkflow::class)->transition($first, ControlledFormRevisionStatus::Active, $admin);
        app(RevisionWorkflow::class)->transition($second, ControlledFormRevisionStatus::Active, $admin);

        $this->assertSame(ControlledFormRevisionStatus::Superseded, $first->fresh()->status);
        $this->assertSame(ControlledFormRevisionStatus::Active, $second->fresh()->status);
        $this->assertSame($second->id, $form->fresh()->current_revision_id);
    }

    public function test_admin_can_bind_tests_to_an_analysis_result_form(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $types = AnalysisType::query()->orderBy('id')->take(2)->get();
        $this->assertCount(2, $types);

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'NPPC-LAB-RES-UI',
                'name' => 'Combined result sheet',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankFolioPdf(),
                'analysis_type_ids' => $types->pluck('id')->all(),
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'NPPC-LAB-RES-UI')->firstOrFail();
        $this->assertSame(
            ControlledFormService::combinationKey($types->pluck('id')->all()),
            $form->combination_key,
        );

        $this->actingAs($admin)
            ->get("/admin/controlled-forms/{$form->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/controlled-forms/show')
                ->has('analysisGroups')
                ->where('form.analysis_type_ids', $types->pluck('id')->map(fn ($id) => (int) $id)->values()->all()));

        $keep = (int) $types->first()->id;

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}", [
                'name' => $form->name,
                'analysis_type_ids' => [$keep],
            ])
            ->assertRedirect();

        $this->assertSame(
            ControlledFormService::combinationKey([$keep]),
            $form->fresh()->combination_key,
        );
    }

    public function test_admin_can_rename_a_controlled_form(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO3')->firstOrFail();
        $typeIds = $form->orderedTypeIds();
        $this->assertNotEmpty($typeIds);

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}", [
                'name' => 'DW Physico Issue 11 (renamed)',
                'description' => 'Updated Document Control label.',
                'department' => 'Laboratory',
                'analysis_type_ids' => $typeIds,
            ])
            ->assertRedirect();

        $form->refresh();
        $this->assertSame('DW Physico Issue 11 (renamed)', $form->name);
        $this->assertSame('Updated Document Control label.', $form->description);
        $this->assertNull($form->analysis_package_id);
        $this->assertSame($typeIds, $form->orderedTypeIds());
    }

    public function test_package_binding_exposes_named_result_sources(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO4')->firstOrFail();
        $this->assertSame($package->id, $form->analysis_package_id);
        $this->assertSame(
            ControlledFormService::combinationKey($package->orderedTypeIds()),
            $form->combination_key,
        );

        $catalog = collect(FieldValueResolver::catalog(
            ControlledFormCategory::AnalysisResult->value,
            $form->load(['analysisTypes', 'analysisPackage.analysisTypes']),
        ));

        $total = $catalog->firstWhere('key', 'test_1_result');
        $measured = $catalog->firstWhere('key', 'test_1_measurement');
        $thermo = $catalog->firstWhere('key', 'test_2_result');

        $this->assertNotNull($total);
        $this->assertNotNull($measured);
        $this->assertNotNull($thermo);
        $this->assertStringContainsString('Total Coliform', (string) $total['label']);
        $this->assertStringContainsString('result', (string) $total['label']);
        $this->assertStringContainsString('measured value', (string) $measured['label']);
        $this->assertStringContainsString('Thermotolerant Coliform', (string) $thermo['label']);
        $this->assertNotSame('test_1_result', $total['label']);
        $this->assertTrue($total['focused']);
        $this->assertTrue($catalog->contains(
            fn (array $source): bool => $source['key'] === 'results.sample_received_at' && ($source['focused'] ?? false),
        ));
        $this->assertFalse($catalog->contains(
            fn (array $source): bool => str_starts_with($source['key'], 'analyses.selected:') && ($source['focused'] ?? false),
        ));

        $this->withoutVite();
        $revision = $form->revisions()->firstOrFail();

        $this->actingAs($admin)
            ->get("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/designer")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/form-designer')
                ->where('form.analysis_package_id', $package->id)
                ->has('packages')
                ->has('sources')
                ->where('sources', function ($sources) {
                    $labels = collect($sources)->pluck('label');

                    return $labels->contains(fn ($label) => str_contains((string) $label, 'Total Coliform'))
                        && ! $labels->contains('test_1_result');
                })
            );
    }

    public function test_new_revision_with_pdf_copies_overlay_fields_from_specified_revision(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'NPPC-LAB-FRM-REV',
                'name' => 'Result overlay reuse',
                'department' => 'Laboratory',
                'category' => ControlledFormCategory::JobOrder->value,
                'revision' => '01',
                'file' => $this->makeBlankFolioPdf('Original sheet'),
                'activate' => 1,
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'NPPC-LAB-FRM-REV')->firstOrFail();
        $active = $form->activeRevision();
        $this->assertNotNull($active);

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}/revisions/{$active->id}/fields", [
                'fields' => [
                    [
                        'name' => 'customer_name',
                        'label' => 'Customer Name',
                        'field_type' => 'text',
                        'page_number' => 1,
                        'x' => 38,
                        'y' => 48,
                        'width' => 100,
                        'height' => 4.5,
                        'font_size' => 8,
                        'font_family' => 'calibri',
                        'data_source_key' => 'job_orders.customer_name',
                    ],
                ],
            ])
            ->assertRedirect();

        $draft = $this->revisionWithPdf($form, '02', $admin);

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}/revisions/{$draft->id}/fields", [
                'fields' => [
                    [
                        'name' => 'customer_name',
                        'label' => 'Customer Name',
                        'field_type' => 'text',
                        'page_number' => 1,
                        'x' => 41.25,
                        'y' => 50.5,
                        'width' => 98,
                        'height' => 4.5,
                        'font_size' => 8,
                        'font_family' => 'calibri',
                        'data_source_key' => 'job_orders.customer_name',
                    ],
                    [
                        'name' => 'reference_no',
                        'label' => 'Reference Number',
                        'field_type' => 'text',
                        'page_number' => 1,
                        'x' => 165,
                        'y' => 22,
                        'width' => 35,
                        'height' => 5,
                        'font_size' => 11,
                        'font_family' => 'calibri',
                        'data_source_key' => 'job_orders.reference_no',
                    ],
                ],
            ])
            ->assertRedirect();

        $draft->refresh();
        $oldCanonical = $draft->canonical_pdf_path;
        $oldSha = $draft->sha256;

        $response = $this->actingAs($admin)
            ->post("/admin/controlled-forms/{$form->id}/revisions", [
                'revision' => '03',
                'file' => $this->makeBlankFolioPdf('Updated official sheet'),
                'copy_from_revision_id' => $draft->id,
                'notes' => 'Layout reused from designer',
            ]);

        $created = $form->revisions()->where('revision', '03')->firstOrFail();
        $response->assertRedirect(route('admin.form-designer.show', [$form, $created]));
        $this->assertSame(ControlledFormRevisionStatus::Draft, $created->status);
        $this->assertNotSame($oldCanonical, $created->canonical_pdf_path);
        $this->assertNotSame($oldSha, $created->sha256);
        $this->assertSame(2, $created->fields()->count());

        $copied = $created->fields()->orderBy('z_order')->get();
        $this->assertSame('customer_name', $copied[0]->name);
        $this->assertSame('41.250', number_format((float) $copied[0]->x, 3));
        $this->assertSame('50.500', number_format((float) $copied[0]->y, 3));
        $this->assertSame('job_orders.customer_name', $copied[0]->data_source_key);
        $this->assertSame('reference_no', $copied[1]->name);
        $this->assertSame('165.000', number_format((float) $copied[1]->x, 3));

        $form->refresh();
        $this->assertSame($active->id, $form->current_revision_id);
    }

    public function test_designer_preview_uses_posted_fields_without_saving(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'NPPC-LAB-PREVIEW-TMP',
                'name' => 'Preview scratch form',
                'category' => ControlledFormCategory::JobOrder->value,
                'revision' => '01',
                'file' => $this->makeBlankFolioPdf(),
            ])
            ->assertRedirect();

        $form = ControlledForm::query()
            ->where('form_code', 'NPPC-LAB-PREVIEW-TMP')
            ->firstOrFail();
        $revision = $form->revisions()->firstOrFail();
        $revision->fields()->delete();

        $this->assertSame(0, $revision->fields()->count());

        $response = $this->actingAs($admin)
            ->post("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/preview", [
                'fields' => [
                    [
                        'name' => 'unsaved_customer',
                        'label' => 'Customer',
                        'field_type' => 'text',
                        'page_number' => 1,
                        'x' => 20,
                        'y' => 30,
                        'width' => 40,
                        'height' => 5,
                        'font_size' => 11,
                        'data_source_key' => 'job_orders.customer_name',
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertSame(0, $revision->fields()->count());
    }

    public function test_analysis_result_form_requires_bound_tests(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();

        $this->actingAs($admin)
            ->from('/admin/controlled-forms')
            ->post('/admin/controlled-forms', [
                'form_code' => 'NPPC-LAB-RES-EMPTY',
                'name' => 'Unbound result sheet',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankFolioPdf(),
            ])
            ->assertRedirect('/admin/controlled-forms')
            ->assertSessionHasErrors('analysis_type_ids');
    }

    public function test_saving_fields_touches_revision_and_busts_overlay_pdf_cache(): void
    {
        $this->seed();
        Storage::fake('local');

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO4')->firstOrFail();

        $this->actingAs($admin)
            ->post("/admin/controlled-forms/{$form->id}/revisions", [
                'revision' => 'cache-bust',
                'file' => $this->makeBlankFolioPdf('FO4 cache bust'),
                'activate' => 1,
                'fill_mode' => 'overlay',
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $form->refresh();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $fields = [
            [
                'name' => 'results.customer',
                'label' => 'Customer',
                'field_type' => 'text',
                'page_number' => 1,
                'x' => 40,
                'y' => 40,
                'width' => 50,
                'height' => 5,
                'font_size' => 11,
                'data_source_key' => 'results.customer',
            ],
        ];

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/fields", [
                'fields' => $fields,
            ])
            ->assertRedirect();

        $revision->refresh();
        $touchedAt = $revision->updated_at?->getTimestamp();
        $this->assertNotNull($touchedAt);

        $this->travel(2)->seconds();

        $fields[0]['font_size'] = 18;
        $fields[0]['x'] = 55;

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/fields", [
                'fields' => $fields,
            ])
            ->assertRedirect();

        $revision->refresh();
        $this->assertGreaterThan($touchedAt, $revision->updated_at?->getTimestamp());
        $this->assertSame(18.0, (float) $revision->fields()->firstOrFail()->font_size);
        $this->assertEqualsWithDelta(55.0, (float) $revision->fields()->firstOrFail()->x, 0.01);

        $this->post('/intake/job-orders', [
            'customer_name' => 'Cache Bust Customer',
            'customer_email' => 'cache-bust@example.com',
            'classification' => 'Wastewater',
            'samples' => [
                ['description' => 'Effluent', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $revision->forceFill(['fill_mode' => 'overlay'])->save();

        $fillCalls = 0;
        $this->mock(\App\Services\ControlledPdfFiller::class, function ($mock) use (&$fillCalls) {
            $mock->shouldReceive('fill')
                ->andReturnUsing(function () use (&$fillCalls) {
                    $fillCalls++;

                    return '%PDF-1.4 overlay-'.$fillCalls;
                });
        });

        $reports = app(\App\Services\AnalysisResultReportResolver::class);
        $makeResolved = function () use ($job, $form, $revision) {
            $freshRevision = $revision->fresh(['fields']);

            return new \App\Support\AnalysisResultReport(
                kind: \App\Support\AnalysisResultReport::KIND_COMBINED,
                filename: 'Result-cache-bust.pdf',
                title: 'FO4',
                message: null,
                jobOrder: $job->fresh(['analyses', 'packages', 'samples']),
                analyses: $job->analyses,
                values: ['results.customer' => 'Cache Bust Customer'],
                controlledForm: $form->fresh(),
                controlledRevision: $freshRevision,
            );
        };

        $first = $reports->renderOverlayPdf($makeResolved());
        $this->assertSame('%PDF-1.4 overlay-1', $first);
        $this->assertSame(1, $fillCalls);

        // Same layout → cache hit (no second fill).
        $cached = $reports->renderOverlayPdf($makeResolved());
        $this->assertSame($first, $cached);
        $this->assertSame(1, $fillCalls);

        $this->travel(2)->seconds();
        $fields[0]['font_size'] = 9;
        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/fields", [
                'fields' => $fields,
            ])
            ->assertRedirect();

        $second = $reports->renderOverlayPdf($makeResolved());
        $this->assertSame('%PDF-1.4 overlay-2', $second);
        $this->assertSame(2, $fillCalls);
    }

    private function revisionWithPdf(ControlledForm $form, string $number, User $admin): ControlledFormRevision
    {
        $this->actingAs($admin)
            ->post("/admin/controlled-forms/{$form->id}/revisions", [
                'revision' => $number,
                'file' => $this->makeBlankFolioPdf(),
            ])
            ->assertRedirect();

        return $form->revisions()->where('revision', $number)->firstOrFail();
    }

    private function makeBlankFolioPdf(string $label = 'Blank controlled form template'): UploadedFile
    {
        $pdf = new Fpdi('P', 'mm', [215.9, 330.2], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Text(20, 20, $label);
        $binary = $pdf->Output('', 'S');

        $path = tempnam(sys_get_temp_dir(), 'cff');
        file_put_contents($path, $binary);

        return new UploadedFile($path, 'controlled-test.pdf', 'application/pdf', null, true);
    }
}
