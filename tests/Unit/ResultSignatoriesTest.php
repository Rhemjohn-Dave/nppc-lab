<?php

namespace Tests\Unit;

use App\Models\ControlledForm;
use App\Models\JobOrder;
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
}
