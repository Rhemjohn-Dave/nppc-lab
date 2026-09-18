<?php

namespace Tests\Feature;

use App\Enums\ControlledFormCategory;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\FieldValueResolver;
use App\Support\ResultSignatories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class ResultSignatoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_to_head_requires_signatories_when_result_form_bound(): void
    {
        Storage::fake('local');
        $this->seed();

        [$form, $job, $analyst] = $this->prepareCompletedCombinedJob(release: false);
        $form->update(['analyst_require_prc' => true]);

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review")
            ->assertSessionHasErrors('signatories');

        $this->assertSame('in_analysis', $job->fresh()->status->value);

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'signatories' => [
                    ['name' => 'Headbound Analyst', 'prc_id' => ''],
                ],
            ])
            ->assertSessionHasErrors('signatories.0.prc_id');

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'signatories' => [
                    ['name' => 'Headbound Analyst', 'prc_id' => '445566'],
                ],
            ])
            ->assertRedirect();

        $job->refresh();
        $this->assertSame('pending_review', $job->status->value);
        $this->assertSame('Headbound Analyst', $job->result_signatories[0]['name']);
        $this->assertSame('445566', $job->result_signatories[0]['prc_id']);
    }

    public function test_send_to_head_allows_empty_prc_when_not_required(): void
    {
        Storage::fake('local');
        $this->seed();

        [, $job, $analyst] = $this->prepareCompletedCombinedJob(release: false);

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'signatories' => [
                    ['name' => 'No PRC Analyst', 'prc_id' => ''],
                ],
            ])
            ->assertRedirect();

        $job->refresh();
        $this->assertSame('pending_review', $job->status->value);
        $this->assertSame('No PRC Analyst', $job->result_signatories[0]['name']);
        $this->assertNull($job->result_signatories[0]['prc_id']);
    }

    public function test_auto_analyst_name_used_when_signatories_unset(): void
    {
        Storage::fake('local');
        $this->seed();

        [$form, $job, $analyst] = $this->prepareCompletedCombinedJob(release: false);

        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $values = app(FieldValueResolver::class)->forResult(
            $revision->load('fields'),
            $job->fresh(['analyses.assignee', 'packages.signatory']),
            $job->analyses,
            $form,
        );

        $this->assertSame($analyst->name, $values['results.analyst_name'] ?? $values['analyst_name'] ?? null);
        $this->assertNull($job->result_signatories);
    }

    public function test_analyst_can_save_signatories_before_head_release(): void
    {
        Storage::fake('local');
        $this->seed();

        [$form, $job, $analyst] = $this->prepareCompletedCombinedJob(release: false);
        $form->update(['analyst_require_prc' => true]);

        $this->assertNull($job->reviewed_at);

        $this->actingAs($analyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-signatories", [
                'signatories' => [
                    ['name' => 'Preview Analyst', 'prc_id' => '112233'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('signatory.confirmed', true);

        $this->assertSame('Preview Analyst', $job->fresh()->result_signatories[0]['name']);
    }

    public function test_saved_signatories_override_pdf_values_with_prc(): void
    {
        Storage::fake('local');
        $this->seed();

        [$form, $job, $analyst] = $this->prepareCompletedCombinedJob();
        $form->update([
            'analyst_signatory_slots' => 1,
            'analyst_require_prc' => true,
        ]);

        $this->actingAs($analyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-signatories", [
                'signatories' => [
                    ['name' => 'Custom Analyst', 'prc_id' => '998877'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('signatories.0.name', 'Custom Analyst')
            ->assertJsonPath('signatory.confirmed', true);

        $job->refresh();
        $revision = $form->fresh()->activeRevision();
        $values = app(FieldValueResolver::class)->forResult(
            $revision->load('fields'),
            $job,
            $job->analyses,
            $form->fresh(),
        );

        $this->assertSame('Custom Analyst', $values['results.analyst_name']);
        $this->assertSame('998877', $values['results.analyst_prc']);
    }

    public function test_two_slot_form_accepts_two_names_and_one_slot_ignores_extra(): void
    {
        Storage::fake('local');
        $this->seed();

        [$form, $job, $analyst] = $this->prepareCompletedCombinedJob();
        $form->update([
            'analyst_signatory_slots' => 2,
            'analyst_require_prc' => false,
        ]);

        $this->actingAs($analyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-signatories", [
                'signatories' => [
                    ['name' => 'First Analyst', 'prc_id' => null],
                    ['name' => 'Second Analyst', 'prc_id' => null],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'signatories');

        $job->refresh();
        $this->assertCount(2, $job->result_signatories);
        $this->assertSame('First Analyst', $job->result_signatories[0]['name']);
        $this->assertSame('Second Analyst', $job->result_signatories[1]['name']);
        $this->assertNull($job->result_signatories[1]['prc_id']);

        $form->update(['analyst_signatory_slots' => 1]);
        $normalized = ResultSignatories::normalizeForStorage([
            ['name' => 'Only One', 'prc_id' => null],
            ['name' => 'Ignored', 'prc_id' => null],
        ], $form->fresh());

        $this->assertCount(1, $normalized);
        $this->assertSame('Only One', $normalized[0]['name']);
    }

    public function test_prc_required_validation_and_optional_when_disabled(): void
    {
        Storage::fake('local');
        $this->seed();

        [$form, $job, $analyst] = $this->prepareCompletedCombinedJob();
        $form->update(['analyst_require_prc' => true]);

        $this->actingAs($analyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-signatories", [
                'signatories' => [
                    ['name' => 'Missing PRC', 'prc_id' => ''],
                ],
            ])
            ->assertStatus(422);

        $form->update(['analyst_require_prc' => false]);

        $this->actingAs($analyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-signatories", [
                'signatories' => [
                    ['name' => 'No PRC Needed', 'prc_id' => ''],
                ],
            ])
            ->assertOk();
    }

    public function test_unauthorized_user_cannot_set_signatories(): void
    {
        Storage::fake('local');
        $this->seed();

        [, $job] = $this->prepareCompletedCombinedJob();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $this->actingAs($receiving)
            ->putJson("/analyst/job-orders/{$job->id}/result-signatories", [
                'signatories' => [
                    ['name' => 'Nope', 'prc_id' => null],
                ],
            ])
            ->assertForbidden();
    }

    public function test_head_cannot_save_result_signatories(): void
    {
        Storage::fake('local');
        $this->seed();

        [, $job] = $this->prepareCompletedCombinedJob(release: false);
        $head = User::where('email', 'head@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'signatories' => [
                    ['name' => 'Sent Analyst', 'prc_id' => null],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($head)
            ->putJson("/head/{$job->id}/result-signatories", [
                'signatories' => [
                    ['name' => 'Head Should Not Edit', 'prc_id' => null],
                ],
            ])
            ->assertNotFound();

        $this->actingAs($head)
            ->getJson("/head/{$job->id}/result-report")
            ->assertOk()
            ->assertJsonPath('signatory.can_edit', false)
            ->assertJsonPath('signatory.save_url', null);

        $this->assertSame('Sent Analyst', $job->fresh()->result_signatories[0]['name']);
    }

    public function test_admin_can_update_form_signatory_settings(): void
    {
        Storage::fake('local');
        $this->seed();

        [$form] = $this->prepareCompletedCombinedJob();
        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}", [
                'name' => $form->name,
                'description' => $form->description,
                'department' => $form->department,
                'analysis_type_ids' => $form->orderedTypeIds(),
                'analyst_signatory_slots' => 2,
                'analyst_require_prc' => true,
            ])
            ->assertRedirect();

        $form->refresh();
        $this->assertSame(2, (int) $form->analyst_signatory_slots);
        $this->assertTrue($form->analyst_require_prc);
    }

    /**
     * @return array{0: ControlledForm, 1: JobOrder, 2: User}
     */
    private function prepareCompletedCombinedJob(bool $release = true): array
    {
        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $head = User::where('email', 'head@nppc.local')->firstOrFail();

        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'SIGN-RESULT-01',
                'name' => 'Signatory result form',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'analysis_type_ids' => [$total->id, $thermo->id],
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'SIGN-RESULT-01')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Signatory Customer',
            'customer_email' => 'signatory@example.com',
            'samples' => [
                ['description' => 'Water', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$total->id, $thermo->id],
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

        $job = $job->fresh(['analyses']);
        foreach ($job->analyses as $line) {
            $line->update(['assigned_to' => $analyst->id]);
        }

        foreach ($job->fresh()->analyses as $line) {
            $this->actingAs($analyst)
                ->post("/analyst/tasks/{$line->id}/complete", [
                    'result_value' => '<1.1',
                    'result_pass_fail' => 'Passed',
                    'result_method' => 'Standard Method',
                    'result_unit' => 'MPN/100ml',
                    'result_remarks' => 'Ok'
                ])
                ->assertRedirect();
        }

        if ($release) {
            $this->actingAs($analyst)
                ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                    'signatories' => [
                        ['name' => $analyst->name, 'prc_id' => '1234567'],
                    ],
                ])
                ->assertRedirect();

            $this->actingAs($head)
                ->post("/head/{$job->id}/sign", [
                    'review_notes' => 'Released',
                ])
                ->assertRedirect();
        }

        return [$form->fresh(), $job->fresh(['analyses.assignee', 'packages.signatory']), $analyst];
    }

    private function makeBlankResultPdf(): \Illuminate\Http\UploadedFile
    {
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(40, 10, 'Blank result');
        $binary = $pdf->Output('', 'S');
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, $binary);

        return new \Illuminate\Http\UploadedFile($path, 'blank.pdf', 'application/pdf', null, true);
    }
}
