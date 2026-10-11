<?php

namespace Tests\Unit;

use App\Models\JobOrder;
use App\Support\ResultPdfFilename;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResultPdfFilenameTest extends TestCase
{
    public function test_type_code_from_category_slugs(): void
    {
        $this->assertSame('DW', ResultPdfFilename::typeCode(['drinking_water']));
        $this->assertSame('WW', ResultPdfFilename::typeCode(['waste_water']));
        $this->assertSame('FOOD', ResultPdfFilename::typeCode(['proximate_analysis']));
        $this->assertSame('FOOD', ResultPdfFilename::typeCode(['food_products_microbiological']));
        $this->assertSame('AQUA', ResultPdfFilename::typeCode(['water_analysis_aquaculture']));
        $this->assertSame('OTH', ResultPdfFilename::typeCode(['metals']));
        $this->assertSame('OTH', ResultPdfFilename::typeCode([]));
    }

    public function test_legacy_microbiological_is_drinking_water_only_on_fo5(): void
    {
        $this->assertSame('DW', ResultPdfFilename::typeCode(['microbiological'], 'LSP-7.8-FO5'));
        $this->assertSame('OTH', ResultPdfFilename::typeCode(['microbiological'], 'LSP-7.8-FO4'));
    }

    public function test_control_number_drops_year_prefix(): void
    {
        $this->assertSame('0002', ResultPdfFilename::controlPart('26-0002'));
        $this->assertSame('0002A', ResultPdfFilename::controlPart('26-0002A'));
    }

    public function test_customer_name_keeps_letters_and_digits_only(): void
    {
        $this->assertSame('RhemjohnDavePitong', ResultPdfFilename::customerPart('Rhemjohn Dave Pitong'));
        $this->assertSame('MaNina', ResultPdfFilename::customerPart('Ma. Niña'));
        $this->assertSame('Customer', ResultPdfFilename::customerPart('  '));
    }

    public function test_full_filename_matches_example(): void
    {
        $job = new JobOrder([
            'reference_no' => '26-0002',
            'customer_name' => 'Rhemjohn Dave Pitong',
        ]);
        $job->created_at = Carbon::create(2026, 10, 1, 9, 30);

        $this->assertSame(
            'Result_OTH_26-0002_0002_RhemjohnDavePitong_10-01-26.pdf',
            ResultPdfFilename::for($job, collect(), '26-0002'),
        );
        $this->assertSame(
            'Result_OTH_26-0002_0002_RhemjohnDavePitong_10-01-26_FO3.pdf',
            ResultPdfFilename::for($job, collect(), '26-0002', 'LSP-7.8-FO3', withFormCode: true),
        );
    }

    public function test_job_order_form_uses_jo_prefix(): void
    {
        $job = new JobOrder([
            'reference_no' => '26-0002',
            'customer_name' => 'Rhemjohn Dave Pitong',
        ]);
        $job->created_at = Carbon::create(2026, 10, 1, 9, 30);
        $job->setRelation('analyses', collect());

        $this->assertSame(
            'JO_OTH_26-0002_0002_RhemjohnDavePitong_10-01-26.pdf',
            ResultPdfFilename::forJobOrder($job),
        );
    }
}
