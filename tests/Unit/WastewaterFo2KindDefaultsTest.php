<?php

namespace Tests\Unit;

use App\Support\OfficialAnalysisCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WastewaterFo2KindDefaultsTest extends TestCase
{
    #[Test]
    public function abstracted_codes_are_the_four_expected_ww_codes(): void
    {
        $this->assertSame(
            ['WW-08', 'WW-05', 'WW-02', 'WW-14'],
            OfficialAnalysisCatalog::wastewaterPhysicoAbstractedTypeCodes(),
        );
    }

    #[Test]
    public function abstracted_codes_are_a_subset_of_issue_18_panel(): void
    {
        $full = OfficialAnalysisCatalog::wastewaterPhysicoIssue18TypeCodes();
        $abstracted = OfficialAnalysisCatalog::wastewaterPhysicoAbstractedTypeCodes();

        foreach ($abstracted as $code) {
            $this->assertContains($code, $full);
        }

        $this->assertLessThan(count($full), count($abstracted));
    }

    #[Test]
    public function wastewater_and_river_water_use_full_fo2_panel(): void
    {
        $full = OfficialAnalysisCatalog::wastewaterPhysicoIssue18TypeCodes();

        $this->assertSame(
            $full,
            OfficialAnalysisCatalog::wastewaterFo2DefaultTypeCodes('Wastewater'),
        );
        $this->assertSame(
            $full,
            OfficialAnalysisCatalog::wastewaterFo2DefaultTypeCodes('River water'),
        );
    }

    #[Test]
    public function abstracted_water_uses_subset_defaults(): void
    {
        $this->assertSame(
            OfficialAnalysisCatalog::wastewaterPhysicoAbstractedTypeCodes(),
            OfficialAnalysisCatalog::wastewaterFo2DefaultTypeCodes('Abstracted water'),
        );
    }

    #[Test]
    public function wastewater_kinds_list_the_three_descriptions(): void
    {
        $this->assertSame(
            ['Wastewater', 'River water', 'Abstracted water'],
            OfficialAnalysisCatalog::wastewaterKinds(),
        );
    }

    #[Test]
    public function fo2_footer_variant_follows_sample_description_kind(): void
    {
        $this->assertSame('abstracted', OfficialAnalysisCatalog::fo2FooterVariant('Abstracted water'));
        $this->assertSame('full', OfficialAnalysisCatalog::fo2FooterVariant('Wastewater'));
        $this->assertSame('full', OfficialAnalysisCatalog::fo2FooterVariant('River water'));
        $this->assertSame('full', OfficialAnalysisCatalog::fo2FooterVariant(null));
        $this->assertTrue(OfficialAnalysisCatalog::isWastewaterAbstractedKind('Abstracted water'));
        $this->assertFalse(OfficialAnalysisCatalog::isWastewaterAbstractedKind('River water'));
    }

    #[Test]
    public function accreditation_footnote_tiers_match_paper_map(): void
    {
        $this->assertSame('*', OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier('WW-09'));
        $this->assertSame('*', OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier('WW-11'));
        $this->assertSame('*', OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier('SA-06'));
        $this->assertSame('**', OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier('WW-12'));
        $this->assertSame('**', OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier('SA-25'));
        $this->assertSame('**', OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier('SA-17'));
        $this->assertSame('***', OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier('WW-04'));
        $this->assertSame('***', OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier('WW-10'));
        $this->assertSame('***', OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier('WW-03'));
        $this->assertSame('***', OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier('SA-19'));
        $this->assertNull(OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier('WW-08'));
        $this->assertNull(OfficialAnalysisCatalog::wastewaterAccreditationFootnoteTier('WW-14'));
    }

    #[Test]
    public function wastewater_test_label_prefixes_footnote_tier(): void
    {
        $this->assertSame(
            '***Color (TCU)',
            OfficialAnalysisCatalog::wastewaterTestLabelWithFootnote('Color (TCU)', '***'),
        );
        $this->assertSame(
            'Color (TCU)',
            OfficialAnalysisCatalog::wastewaterTestLabelWithFootnote('***Color (TCU)', null),
        );
        $this->assertSame(
            '*Phosphate (mg/L)',
            OfficialAnalysisCatalog::wastewaterTestLabelWithFootnote('**Phosphate (mg/L)', '*'),
        );
    }

    #[Test]
    public function resolve_accreditation_footnote_treats_empty_as_explicit_none(): void
    {
        $this->assertSame(
            '***',
            OfficialAnalysisCatalog::resolveWastewaterAccreditationFootnote(null, 'WW-03'),
        );
        $this->assertNull(
            OfficialAnalysisCatalog::resolveWastewaterAccreditationFootnote('', 'WW-03'),
        );
        $this->assertSame(
            '*',
            OfficialAnalysisCatalog::resolveWastewaterAccreditationFootnote('*', 'WW-03'),
        );
    }

    #[Test]
    public function fo2_note_blocks_differ_by_variant(): void
    {
        $full = OfficialAnalysisCatalog::fo2FullNotesText();
        $abs = OfficialAnalysisCatalog::fo2AbstractedNotesText();

        $this->assertStringContainsString('***Not PAB and EMB', $full);
        $this->assertStringContainsString('*Not EMB- DENR Accredited.', $abs);
        $this->assertStringNotContainsString('***Not PAB', $abs);
    }

    #[Test]
    public function fo2_lab_conditions_include_temp_humidity_and_na(): void
    {
        $full = OfficialAnalysisCatalog::fo2LabConditionsText('full');
        $this->assertStringContainsString('Laboratory Conditions during the conduct of test', $full);
        $this->assertStringContainsString('Temp =', $full);
        $this->assertStringContainsString('Humidity =', $full);
        $this->assertStringContainsString('NA – Not Applicable for specified water body', $full);

        $refs = OfficialAnalysisCatalog::fo2ReferencesText();
        $this->assertStringContainsString('SMEWW,24th Edition 2023', $refs);
        $this->assertStringContainsString('US – EPA', $refs);
    }

    #[Test]
    public function wastewater_physico_form_code_follows_kind(): void
    {
        $this->assertSame('LSP-7.8-FO2A', OfficialAnalysisCatalog::wastewaterPhysicoFormCode('Abstracted water'));
        $this->assertSame('LSP-7.8-FO2', OfficialAnalysisCatalog::wastewaterPhysicoFormCode('Wastewater'));
        $this->assertSame('LSP-7.8-FO2', OfficialAnalysisCatalog::wastewaterPhysicoFormCode('River water'));
        $this->assertSame('LSP-7.8-FO2', OfficialAnalysisCatalog::wastewaterPhysicoFormCode(null));
    }
}
