<?php

namespace Tests\Unit;

use App\Enums\ControlledFormFieldType;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use App\Support\ResultSignatories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultSignatoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_line_appends_prc_when_present(): void
    {
        $this->assertSame('Ana Analyst', ResultSignatories::formatLine([
            'name' => 'Ana Analyst',
            'prc_id' => null,
        ]));
        $this->assertSame('Ana Analyst', ResultSignatories::formatLine([
            'name' => 'Ana Analyst',
            'prc_id' => '123',
        ]));
        $this->assertNull(ResultSignatories::formatLine(['name' => '', 'prc_id' => '1']));
    }

    public function test_fo2_slot_labels_and_four_bag_values(): void
    {
        $form = new ControlledForm([
            'form_code' => 'LSP-7.8-FO2',
            'analyst_signatory_slots' => 4,
        ]);

        $this->assertSame(4, ResultSignatories::slotsFor($form));
        $this->assertSame([
            'Reviewed by',
            'Noted By',
            'Certified Correct (1)',
            'Certified Correct (2)',
        ], ResultSignatories::slotLabels($form));

        $job = new JobOrder;
        $job->result_signatories = [
            ['name' => 'Reviewed', 'prc_id' => '1'],
            ['name' => 'Noted', 'prc_id' => '2'],
            ['name' => 'Cert1', 'prc_id' => '3'],
            ['name' => 'Cert2', 'prc_id' => '4'],
        ];

        $bag = ResultSignatories::bagValues($job, $form);
        $this->assertSame('Reviewed', $bag['name']);
        $this->assertSame('Noted', $bag['name_2']);
        $this->assertSame('Cert1', $bag['name_3']);
        $this->assertSame('Cert2', $bag['name_4']);
    }

    public function test_fo2a_uses_three_signatory_slots(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2A')->firstOrFail();

        $this->assertSame(3, ResultSignatories::slotsFor($form));
        $this->assertSame([
            'Reviewed by',
            'Certified Correct',
            'Noted By',
        ], ResultSignatories::slotLabels($form));
    }

    public function test_fo2b_uses_two_signatory_slots(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2B')->firstOrFail();

        $this->assertSame(2, ResultSignatories::slotsFor($form));
        $this->assertSame([
            'Reviewed by',
            'Certified Correct',
        ], ResultSignatories::slotLabels($form));
    }

    public function test_normalize_scales_to_configured_slots(): void
    {
        $form = new ControlledForm([
            'analyst_signatory_slots' => 2,
            'analyst_require_prc' => false,
        ]);

        $normalized = ResultSignatories::normalizeForStorage([
            ['name' => ' One ', 'prc_id' => ''],
            ['name' => 'Two', 'prc_id' => ' 55 '],
        ], $form);

        $this->assertSame([
            ['name' => 'One', 'prc_id' => null],
            ['name' => 'Two', 'prc_id' => '55'],
        ], $normalized);
    }

    public function test_bag_values_prefer_stored_signatories(): void
    {
        $job = new JobOrder;
        $job->result_signatories = [
            ['name' => 'Stored', 'prc_id' => '9'],
        ];
        $form = new ControlledForm(['analyst_signatory_slots' => 1]);

        $bag = ResultSignatories::bagValues($job, $form);

        $this->assertSame('Stored', $bag['line']);
        $this->assertSame('Stored', $bag['name']);
        $this->assertSame('9', $bag['prc']);
    }

    public function test_by_form_buckets_keep_fo2_and_fo2a_roles_separate(): void
    {
        $fo2 = new ControlledForm([
            'form_code' => 'LSP-7.8-FO2',
            'analyst_signatory_slots' => 4,
        ]);
        $fo2a = new ControlledForm([
            'form_code' => 'LSP-7.8-FO2A',
            'analyst_signatory_slots' => 3,
        ]);

        $stored = ResultSignatories::mergeIntoStorage(null, $fo2, [
            ['name' => 'FO2 Reviewed', 'prc_id' => null],
            ['name' => 'FO2 Noted', 'prc_id' => null],
            ['name' => 'FO2 Cert1', 'prc_id' => null],
            ['name' => 'FO2 Cert2', 'prc_id' => null],
        ]);
        $stored = ResultSignatories::mergeIntoStorage($stored, $fo2a, [
            ['name' => 'FO2A Reviewed', 'prc_id' => null],
            ['name' => 'FO2A Certified', 'prc_id' => null],
            ['name' => 'FO2A Noted', 'prc_id' => null],
        ]);

        $job = new JobOrder;
        $job->result_signatories = $stored;

        $fo2Bag = ResultSignatories::bagValues($job, $fo2);
        $this->assertSame('FO2 Reviewed', $fo2Bag['name']);
        $this->assertSame('FO2 Noted', $fo2Bag['name_2']);
        $this->assertSame('FO2 Cert1', $fo2Bag['name_3']);

        $fo2aBag = ResultSignatories::bagValues($job, $fo2a);
        $this->assertSame('FO2A Reviewed', $fo2aBag['name']);
        $this->assertSame('FO2A Certified', $fo2aBag['name_2']);
        $this->assertSame('FO2A Noted', $fo2aBag['name_3']);
        $this->assertNull($fo2aBag['name_4']);

        $this->assertTrue(ResultSignatories::isConfirmedForForm($stored, $fo2));
        $this->assertTrue(ResultSignatories::isConfirmedForForm($stored, $fo2a));
        $this->assertSame([], ResultSignatories::missingFormCodes($stored, [$fo2, $fo2a]));
    }

    public function test_defaults_prefer_designer_static_text_over_assignees(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2A')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $nameField = $revision->fields()
            ->where('data_source_key', 'results.analyst_name')
            ->firstOrFail();
        $nameField->update([
            'options' => array_merge(
                is_array($nameField->options) ? $nameField->options : [],
                ['static_text' => 'Designer Reviewed Name'],
            ),
        ]);

        $licField = $revision->fields()
            ->where('data_source_key', 'fo2_lic_reviewed')
            ->firstOrFail();
        $licField->update([
            'options' => array_merge(
                is_array($licField->options) ? $licField->options : [],
                ['static_text' => 'Registered Chemical Technician, License #00761'],
            ),
        ]);

        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $job = JobOrder::query()->create([
            'reference_no' => 'SIG-STATIC-1',
            'customer_name' => 'Static Prefill',
            'status' => 'in_analysis',
        ]);
        JobOrderAnalysis::query()->create([
            'job_order_id' => $job->id,
            'name' => 'pH',
            'status' => 'assigned',
            'assigned_to' => $analyst->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $defaults = ResultSignatories::defaults(
            $job->fresh(['analyses.assignee', 'packages.signatory']),
            $form->fresh(),
        );

        $this->assertSame('Designer Reviewed Name', $defaults[0]['name']);
        $this->assertSame('00761', $defaults[0]['prc_id']);
        $this->assertNotSame($analyst->name, $defaults[0]['name']);
        $this->assertCount(3, $defaults);
    }

    public function test_defaults_fall_back_to_assignee_when_designer_static_empty(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        foreach (['results.analyst_name', 'results.analyst_name_2', 'results.analyst_name_3', 'results.analyst_name_4'] as $key) {
            ControlledFormField::query()
                ->where('controlled_form_revision_id', $revision->id)
                ->where(function ($query) use ($key) {
                    $query->where('data_source_key', $key)->orWhere('name', $key);
                })
                ->get()
                ->each(function (ControlledFormField $field) {
                    $options = is_array($field->options) ? $field->options : [];
                    unset($options['static_text']);
                    $field->update(['options' => $options]);
                });
        }

        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $job = JobOrder::query()->create([
            'reference_no' => 'SIG-ASSIGNEE-1',
            'customer_name' => 'Assignee Fallback',
            'status' => 'in_analysis',
        ]);
        JobOrderAnalysis::query()->create([
            'job_order_id' => $job->id,
            'name' => 'BOD',
            'status' => 'assigned',
            'assigned_to' => $analyst->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $defaults = ResultSignatories::defaults(
            $job->fresh(['analyses.assignee', 'packages.signatory']),
            $form->fresh(),
        );

        $this->assertSame($analyst->name, $defaults[0]['name']);
        $this->assertNull($defaults[0]['prc_id']);
    }

    public function test_designer_static_slots_parse_prc_field_and_license_chrome(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        ControlledFormField::query()->updateOrCreate(
            [
                'controlled_form_revision_id' => $revision->id,
                'data_source_key' => 'results.analyst_prc',
            ],
            [
                'name' => 'results.analyst_prc',
                'label' => 'PRC 1',
                'field_type' => ControlledFormFieldType::Text,
                'page_number' => 1,
                'x' => 10,
                'y' => 10,
                'width' => 40,
                'height' => 5,
                'font_size' => 9,
                'z_order' => 99,
                'options' => ['static_text' => '998877'],
            ],
        );

        $slots = ResultSignatories::designerStaticSlots($form->fresh(), 4);
        $this->assertSame('998877', $slots[0]['prc_id']);
    }
}
