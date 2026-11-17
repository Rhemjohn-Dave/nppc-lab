<?php

namespace Tests\Unit;

use App\Models\ControlledForm;
use App\Support\ReportDate;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportDateTest extends TestCase
{
    public function test_add_calendar_days_includes_weekends(): void
    {
        $from = Carbon::parse('2026-07-21', 'Asia/Manila')->startOfDay(); // Tuesday

        $this->assertSame(
            '2026-07-28',
            WorkingDays::addCalendarDays($from, 7)->toDateString(),
        );
        $this->assertSame(
            '2026-07-25',
            WorkingDays::addCalendarDays($from, 4)->toDateString(),
        );
    }

    public function test_add_working_days_skips_weekends(): void
    {
        $from = Carbon::parse('2026-07-21', 'Asia/Manila')->startOfDay(); // Tuesday
        // +7 working: Wed22 Thu23 Fri24 Mon27 Tue28 Wed29 Thu30
        $this->assertSame(
            '2026-07-30',
            WorkingDays::addWorkingDays($from, 7)->toDateString(),
        );
    }

    #[DataProvider('formRuleProvider')]
    public function test_rule_for_form_codes(string $code, string $mode, int $days): void
    {
        $form = new ControlledForm(['form_code' => $code]);
        $rule = ReportDate::ruleFor($form);

        $this->assertSame($mode, $rule['mode']);
        $this->assertSame($days, $rule['days']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function formRuleProvider(): array
    {
        return [
            'fo2' => ['LSP-7.8-FO2', 'calendar', 7],
            'fo2a' => ['LSP-7.8-FO2A', 'calendar', 7],
            'fo2b' => ['LSP-7.8-FO2B', 'calendar', 7],
            'fo4' => ['LSP-7.8-FO4', 'calendar', 4],
            'fo5' => ['LSP-7.8-FO5', 'calendar', 4],
            'fo3' => ['LSP-7.8-FO3', 'working', 7],
            'fo37' => ['LSP-7.8-FO37', 'working', 7],
            'other' => ['LSP-7.8-FO26', 'calendar', 7],
        ];
    }
}
