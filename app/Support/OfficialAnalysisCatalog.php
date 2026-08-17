<?php

namespace App\Support;

use App\Enums\AnalysisCategory;

class OfficialAnalysisCatalog
{
    /**
     * Official Request for Analysis form catalog (order as printed).
     *
     * @return array<string, list<array{0: string, 1: string, 2: int}>>
     */
    public static function definitions(): array
    {
        return [
            AnalysisCategory::Microbiological->value => [
                ['MB-01', 'Aerobic Plate Count (HPC)', 450],
                ['MB-02', 'Total & Fecal Coliform', 450],
                ['MB-03', 'E. coli', 500],
                ['MB-04', 'S. aureus', 550],
                ['MB-05', 'V. parahaemolyticus', 650],
                ['MB-06', 'Total Vibrio Count', 550],
                ['MB-07', 'Listeria', 700],
                ['MB-08', 'Enterobacter', 500],
                ['MB-09', 'Yeast and Mold', 450],
                ['MB-10', 'Phytoplankton Count & Identification', 700],
            ],
            AnalysisCategory::PhysicoChemical->value => [
                ['PC-01', 'Acidity', 350],
                ['PC-02', 'Alkalinity', 350],
                ['PC-03', 'BOD', 550],
                ['PC-04', 'Color', 300],
                ['PC-05', 'Dissolved Oxygen (DO)', 350],
                ['PC-06', 'Odor', 250],
                ['PC-07', 'pH', 250],
                ['PC-08', 'Total Dissolved Solids', 350],
                ['PC-09', 'Total Suspended Solids', 350],
                ['PC-10', 'Total Volatile Solids', 350],
                ['PC-11', 'Total Solids', 300],
                ['PC-12', 'Temperature', 250],
                ['PC-13', 'Turbidity', 300],
                ['PC-14', 'Oil & Grease', 500],
                ['PC-15', 'Total Ammonia-N', 400],
                ['PC-16', 'Nitrates', 400],
                ['PC-17', 'Nitrites', 400],
                ['PC-18', 'Total Nitrogen', 400],
                ['PC-19', 'Moisture', 350],
                ['PC-20', 'Salinity', 300],
                ['PC-21', 'Total Hardness', 350],
                ['PC-22', 'Phosphates', 400],
                ['PC-23', 'Sulfates', 350],
                ['PC-24', 'Potassium', 400],
                ['PC-25', 'Soil pH', 250],
                ['PC-26', 'Organic Matter (Soil)', 400],
                ['PC-27', 'Available Phosphorus (Soil)', 400],
                ['PC-28', 'Exchangeable Potassium (Soil)', 400],
                ['PC-29', 'Electrical Conductivity', 300],
                ['PC-30', 'Chloride', 350],
            ],
            AnalysisCategory::TraceHeavyMetals->value => [
                ['HM-01', 'Aluminum (Al)', 700],
                ['HM-02', 'Arsenic (As)', 800],
                ['HM-03', 'Beryllium (Be)', 800],
                ['HM-04', 'Zinc (Zn)', 700],
                ['HM-05', 'Calcium (Ca)', 650],
                ['HM-06', 'Chromium (Cr)', 800],
                ['HM-07', 'Copper (Cu)', 700],
                ['HM-08', 'Iron (Fe)', 650],
                ['HM-09', 'Lead (Pb)', 800],
                ['HM-10', 'Magnesium (Mg)', 650],
                ['HM-11', 'Manganese (Mn)', 650],
                ['HM-12', 'Mercury (Hg)', 900],
                ['HM-13', 'Selenium (Se)', 850],
                ['HM-14', 'Cadmium (Cd)', 800],
                ['HM-15', 'Sodium (Na)', 650],
                ['HM-16', 'Molybdenum (Mo)', 750],
            ],
            AnalysisCategory::Lime->value => [
                ['LM-01', '% CaO', 450],
                ['LM-02', '% NV', 400],
                ['LM-03', '% ER', 350],
            ],
        ];
    }

    /**
     * @return array{lab: string, form: string, code: string, revision: string, effective: string}
     */
    public static function documentControl(): array
    {
        return [
            'lab' => 'NPPC-ADL',
            'form' => 'LSP 7.1 F01',
            'code' => 'NPPC-ADL',
            'revision' => '9/Issue 10',
            'effective' => '01/02/2026',
        ];
    }

    /** @return list<string> */
    public static function classifications(): array
    {
        return ['Aqua', 'Potability', 'Wastewater', 'Agriculture', 'Academic/Research', 'Others'];
    }

    /** @return list<string> */
    public static function ownershipTypes(): array
    {
        return ['Private', 'Commercial', 'Public'];
    }
}
