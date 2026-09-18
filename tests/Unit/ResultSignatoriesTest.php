<?php

namespace Tests\Unit;

use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Support\ResultSignatories;
use Tests\TestCase;

class ResultSignatoriesTest extends TestCase
{
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
}
