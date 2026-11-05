<?php

namespace App\Support;

use App\Enums\AnalysisCategory;
use App\Enums\CatalogScope;
use App\Enums\JobOrderVariant;
use App\Models\JobOrder;

class OfficialAnalysisCatalog
{
    /**
     * Official price-list catalog (NPPC_Test_Parameters_and_Prices).
     * Each row: [code, name, price, ?scope]. Scope defaults from the category when omitted.
     * Chemicals/Reagents and unclear prices are omitted. Temperature FREE → 0.
     *
     * @return array<string, list<array{0: string, 1: string, 2: int|float, 3?: string}>>
     */
    public static function definitions(): array
    {
        return [
            AnalysisCategory::SpecialAnalysis->value => [
                ['SA-01', 'Acidity', 650],
                ['SA-02', 'Alcohol Content (Titrimetric & Pycnometer)', 850],
                ['SA-03', 'Alkalinity', 500],
                ['SA-04', 'Ascorbic Acid Content, Vit A, Vit E', 5000],
                ['SA-05', 'Carbonates and Bicarbonates', 450],
                ['SA-06', 'Chloride (mg/L)', 750],
                ['SA-07', 'Chlorine (Residual)', 650],
                ['SA-08', 'Ellagic Acid', 4000],
                ['SA-09', 'Free Carbon Dioxide', 300],
                ['SA-10', 'Liquid (Sodium Hypochlorite)', 1200],
                ['SA-11', 'Phenolic Profiling', 4000],
                ['SA-12', 'Phytochemical Test, Qualitative', 2000],
                ['SA-13', 'Quercetin', 4000],
                ['SA-14', 'Refractive Index', 750],
                ['SA-15', 'Rutin', 5000],
                ['SA-16', 'Salinity', 200],
                ['SA-17', 'Settleable Solids (mL/L)', 750],
                ['SA-18', 'Solid (Calcium Hypochlorite)', 1200],
                ['SA-19', 'Sulfate (mg/L)', 650],
                ['SA-20', 'Sulfide', 650],
                ['SA-21', 'Total Anthocyanin Content (HPLC)', 5000],
                ['SA-22', 'Total Anthocyanin Content (Spectro)', 2500],
                ['SA-23', 'Total Flavonoids', 3000],
                ['SA-24', 'Total Phenolics', 5000],
                ['SA-25', 'Total Solids (mg/L)', 1000],
                ['SA-26', 'Total Volatile Solids', 750],
                ['SA-27', 'Vitamin C (HPLC)', 5000],
                ['SA-28', 'Vitamin C (Titration)', 1000],
                ['SA-29', 'Other Analysis (Anti-oxidants, Phenolics, etc.) using HPLC Techniques', 5000],
                ['SA-30', 'Titratable Acidity', 1000],
                ['SA-31', 'Fluoride', 1000],
                ['SA-32', 'Reducing Sugar', 850],
                ['SA-33', 'Insoluble Matter', 1000],
                ['SA-34', 'Zooplankton', 750],
                ['SA-35', 'Free Fatty Acid', 1000],
                ['SA-36', 'Total Tannin', 2500],
                ['SA-37', '% Chlorine', 1200],
                ['SA-38', 'HPLC (per analyte)', 5000],
                ['SA-39', 'UV-VIS (Wavelength Scanning & Analysis)', 2500],
                ['SA-40', 'Appearance', 200],
                ['SA-41', 'FTIR', 700],
            ],
            AnalysisCategory::WasteWater->value => [
                ['WW-01', 'Ammonia as NH3-N (mg/L)', 750],
                ['WW-02', 'Biochemical Oxygen Demand (mg/L)', 1500],
                ['WW-03', 'Chemical Oxygen Demand (mg/L)', 1000],
                ['WW-04', 'Color (TCU)', 400],
                ['WW-05', 'Lab Dissolved Oxygen (mg/L)', 200],
                ['WW-06', 'Nitrate as NO3-N (mg/L)', 750],
                ['WW-07', 'Oil and Grease (mg/L)', 750],
                ['WW-08', 'Lab pH', 200],
                ['WW-09', 'Phosphate (mg/L)', 700],
                ['WW-10', 'Surfactants (mg/L)', 750],
                ['WW-11', 'Lab Temperature (°C)', 0],
                ['WW-12', 'Total Dissolved Solids (mg/L)', 750],
                ['WW-13', 'Total Fecal Coliform', 750],
                ['WW-14', 'Total Suspended Solids (mg/L)', 750],
            ],
            AnalysisCategory::FoodProductsMicrobiological->value => [
                // FO26 Issue 4 print order (FM-04 Listeria spp. kept off the FO26 sheet).
                ['FM-01', 'Aerobic Plate Count (CFU/g)', 1000],
                ['FM-08', 'Total Coliform (MPN/g)', 850],
                ['FM-11', 'Fecal Coliform (MPN/g)', 850],
                ['FM-02', 'E. coli (MPN/g)', 1500],
                ['FM-06', 'S. aureus (CFU/g)', 1000],
                ['FM-07', 'Salmonella', 1500],
                ['FM-03', 'Listeria', 1500],
                ['FM-09', 'Yeasts (CFU/g)', 500],
                ['FM-05', 'Molds (CFU/g)', 500],
                ['FM-10', 'Vibrio parahaemolyticus (CFU/g)', 1000],
                ['FM-04', 'Listeria spp.', 1000],
                // FO27 sugar panel — distinct SM codes (no FM overlap for types-only resolve).
                ['SM-01', 'Mesophilic Bacteria Count (CFU/g)', 1000],
                ['SM-02', 'Aerobic Thermophilic Spore Count (CFU/g)', 1000],
                ['SM-03', 'E. coli (CFU/g)', 1500],
                ['SM-04', 'Total Coliform (CFU/g)', 850],
                ['SM-05', 'Total Coliform (MPN/g)', 850],
                ['SM-06', 'Fecal Coliform (MPN/g)', 850],
                ['SM-07', 'Salmonella', 1500],
                ['SM-08', 'Yeasts (CFU/g)', 500],
                ['SM-09', 'Molds (CFU/g)', 500],
            ],
            AnalysisCategory::ProximateAnalysis->value => [
                ['PX-06', 'Ash', 850],
                ['PX-08', 'Brix', 850],
                ['PX-09', 'Calorie', 500],
                ['PX-05', 'Carbohydrates', 1000],
                ['PX-01', 'Fats', 1100],
                ['PX-04', 'Fiber', 1500],
                ['PX-03', 'Moisture', 850],
                ['PX-10', 'Potassium', 1450],
                ['PX-02', 'Protein', 1500],
                ['PX-07', 'Sodium', 1450],
                ['PX-11', 'Sample Preparation', 500],
            ],
            AnalysisCategory::Nutrifacts->value => [
                ['NF-01', 'Ash', 850],
                ['NF-02', 'Calorie', 500],
                ['NF-03', 'Carbohydrates', 1000],
                ['NF-04', 'Fats', 1100],
                ['NF-05', 'Fiber', 1500],
                ['NF-06', 'Moisture', 850],
                ['NF-07', 'Protein', 2000],
                ['NF-08', 'Sodium', 1450],
                ['NF-09', 'Sugar', 650],
                ['NF-10', 'Sample Preparation', 500],
            ],
            AnalysisCategory::OtherFoodAnalysis->value => [
                ['OF-01', 'Caffeine', 5000],
                ['FD-CAP', 'Chloramphenicol', 5000, CatalogScope::Both->value],
                ['OF-02', 'Iodine Value', 800],
                ['OF-03', 'Peroxide Value', 1000],
                ['OF-04', 'Saponification Value', 800],
                ['OF-05', 'Soluble Solids', 850],
                ['WA-01', 'Water Activity', 850],
                ['OF-06', 'Saturated Fat', 600],
                ['OF-07', 'Trans Fat', 600],
                ['OF-08', 'Cholesterol', 1000],
                ['FD-NO2', 'Nitrite Content (Food mg/kg)', 500],
                ['MK-01', '% Total Solids (Milk)', 350],
                ['MK-02', '% Total Soluble Solids (Milk)', 300],
                ['MK-03', 'pH (Milk)', 250],
                ['MK-04', '% Fat (Milk)', 400],
                ['MK-05', '% Solid Not Fat, SNF', 300],
            ],
            AnalysisCategory::DrinkingWater->value => [
                ['DW-01', 'Color (Apparent Color)', 400],
                ['DW-02', 'pH', 200],
                ['DW-03', 'Total Dissolved Solids (mg/L)', 750],
                ['DW-04', 'Turbidity (NTU)', 450],
                ['DW-05', 'Residual Chlorine (mg/L)', 650],
                ['DW-06', 'Nitrate (mg/L)', 850],
                ['DW-07', 'Arsenic (mg/L)', 2500],
                ['DW-08', 'Lead (mg/L)', 2000],
                ['DW-09', 'Cadmium (mg/L)', 2000],
                ['DW-10', 'Sample Preparation', 500],
                ['DW-11', 'E. coli', 400],
                ['DW-12', 'Odor', 400],
                ['DW-13', 'Electrical Conductivity (µS/cm)', 450],
                ['DW-14', 'Chloride (mg/L)', 750],
                ['DW-15', 'Total Hardness (mg/L)', 500],
                ['DW-16', 'Calcium Hardness (mg/L)', 400],
                ['DW-17', 'Magnesium Hardness (mg/L)', 400],
                ['DW-18', 'Iron (mg/L)', 650],
                ['DW-19', 'Nitrite (mg/L)', 850],
                ['DW-20', 'Sulfate (mg/L)', 650],
            ],
            AnalysisCategory::LimeAnalysis->value => [
                ['LM-02', 'Neutralizing Value', 600],
            ],
            AnalysisCategory::Fertilizer->value => [
                ['FZ-01', 'Humic Acid', 1000],
                ['FZ-02', 'pH', 200],
                ['FZ-03', 'Total Nitrogen', 1500],
                ['FZ-04', 'Total Phosphorus', 850],
                ['FZ-05', 'Total Potassium', 1450],
                ['FZ-06', 'Sample Preparation', 500],
            ],
            AnalysisCategory::SoilAnalysisAquaculture->value => [
                ['AQ-S-01', 'Available Iron', 450],
                ['AQ-S-02', 'Available Phosphorus', 450],
                ['AQ-S-03', 'Available Sulfur', 450],
                ['AQ-S-04', 'Organic Matter Content', 850],
                ['AQ-S-05', 'pH', 200],
                ['AQ-S-06', 'Potential Acidity', 350],
                ['AQ-S-07', 'Total Bacterial Count/Luminous Bacterial Count', 400],
                ['AQ-S-08', 'Total Vibrio Count and Profile', 750],
            ],
            AnalysisCategory::Metals->value => [
                ['MT-01', 'Aluminum', 2000, CatalogScope::Both->value],
                ['MT-02', 'Arsenic', 2500, CatalogScope::Both->value],
                ['MT-03', 'Cadmium', 2000, CatalogScope::Both->value],
                ['MT-04', 'Calcium', 2000, CatalogScope::Both->value],
                ['MT-05', 'Chromium', 2000, CatalogScope::Both->value],
                ['MT-06', 'Copper', 2000, CatalogScope::Both->value],
                ['MT-07', 'Iron', 2000, CatalogScope::Both->value],
                ['MT-08', 'Lead', 2000, CatalogScope::Both->value],
                ['MT-09', 'Magnesium', 2000, CatalogScope::Both->value],
                ['MT-10', 'Manganese', 2000, CatalogScope::Both->value],
                ['MT-11', 'Mercury', 2500, CatalogScope::Both->value],
                ['MT-12', 'Nickel', 2000, CatalogScope::Both->value],
                ['MT-13', 'Selenium', 2000, CatalogScope::Both->value],
                ['MT-14', 'Silver', 2000, CatalogScope::Both->value],
                ['MT-15', 'Sodium', 2000, CatalogScope::Both->value],
                ['MT-16', 'Zinc', 2000, CatalogScope::Both->value],
                ['MT-17', 'Sample Preparation', 500, CatalogScope::Both->value],
            ],
            AnalysisCategory::AdultFryPrawnAquaculture->value => [
                ['AQ-P-01', 'Microscopic Analysis', 300],
                ['AQ-P-02', 'Total Bacterial Count/Luminous Bacteria', 400],
                ['AQ-P-03', 'Vibrio Count', 400],
                ['AQ-P-04', 'V. parahaemolyticus', 400],
                ['PCR-01', 'WSSV (PCR Technique)', 1450],
                ['PCR-02', 'APHND/EMS', 1000],
                ['PCR-03', 'EHP', 1000],
            ],
            AnalysisCategory::SoilAnalysisAgriculture->value => [
                ['AG-S-01', 'Available Phosphorus', 650],
                ['AG-S-02', 'Calcium', 1450],
                ['AG-S-03', 'Humic Acid', 850],
                ['AG-S-04', 'Magnesium', 1450],
                ['AG-S-05', 'Organic Matter Content', 850],
                ['AG-S-06', 'pH', 200],
                ['AG-S-07', 'Potassium', 1450],
                ['AG-S-08', 'Texture', 300],
                ['AG-S-09', 'Total Nitrogen', 1500],
                ['AG-S-10', 'Total Phosphorus', 1000],
                ['AG-S-11', 'Total Potassium', 2000],
                ['AG-S-12', 'Sample Preparation', 500],
            ],
            AnalysisCategory::WaterAnalysisAquaculture->value => [
                ['AQ-W-01', 'Ammonia-Nitrogen', 300],
                ['AQ-W-02', 'Iron', 450],
                ['AQ-W-03', 'Nitrite-Nitrogen', 450],
                ['AQ-W-04', 'Orthophosphate', 450],
                ['AQ-W-05', 'pH', 200],
                ['AQ-W-06', 'Salinity', 100],
                ['AQ-W-07', 'Total Bacterial Count', 400],
                ['AQ-W-08', 'Luminous Bacterial Count', 400],
                ['AQ-W-09', 'Total Vibrio Count and Profile', 450],
                ['AQ-W-10', 'Total Alkalinity', 450],
                ['AQ-W-11', 'Total Hardness (CalMag)', 450],
                ['AQ-W-12', 'Total Phytoplankton Count', 200],
                ['AQ-W-13', 'V. parahaemolyticus', 400],
                ['AQ-W-14', 'Calcium Hardness', 400],
                ['AQ-W-15', 'Magnesium Hardness', 400],
            ],
            // FO4 / FO5 package members (result sheets) — not kiosk-primary browse groups
            AnalysisCategory::Microbiological->value => [
                ['MB-01', 'Aerobic Plate Count (HPC)', 450],
                ['MB-02A', 'Total Coliform (MPN/100ml)', 375],
                ['MB-02B', 'Thermotolerant Coliform (MPN/100ml)', 375],
                ['DW-BACT-HPC', 'Bacteriological — HPC', 100],
                ['DW-BACT-TC', 'Bacteriological — Total Coliform', 100],
                ['DW-BACT-FC', 'Bacteriological — Fecal Coliform', 100],
            ],
            AnalysisCategory::Phytochemical->value => [
                ['PHY-01', 'Alkaloids', 300],
                ['PHY-02', 'Tannins', 300],
                ['PHY-03', 'Saponin', 300],
                ['PHY-04', 'Proteins (Phytochemical)', 300],
                ['PHY-05', 'Phenols', 300],
                ['PHY-06', 'Flavonoids', 300],
                ['PHY-07', 'Glycosides', 300],
                ['PHY-08', 'Carbohydrates (Phytochemical)', 300],
                ['PHY-09', 'Terpenoids', 300],
            ],
        ];
    }

    public static function scopeForDefinition(string $categorySlug, array $row): CatalogScope
    {
        if (isset($row[3]) && is_string($row[3])) {
            return CatalogScope::from($row[3]);
        }

        $category = AnalysisCategory::tryFrom($categorySlug);

        return $category?->catalogScope() ?? CatalogScope::NonAqua;
    }

    public static function methodForCode(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return match ($code) {
            'PX-01' => 'Soxhlet Extraction Method',
            'PX-02' => 'Kjeldahl Method',
            'PX-03' => 'Gravimetric Oven Drying at 105°C',
            'PX-04' => 'Weende Method',
            'PX-05' => 'Phenol Sulfuric Acid Method',
            'PX-06' => 'Oxidation at 550°C',
            'PX-07' => 'FLAME-AES',
            'PX-08' => 'Refractometer',
            'WA-01' => 'Decagon AquaLab Series 3TE',
            'FD-CAP' => 'Chloramphenicol ELISA Assay',
            'FD-NO2' => 'Spectrophotometric Method',
            'DW-01' => 'Colorimetric Method (2120 B)',
            'DW-02' => 'Electrometric Method (4500-H+ B)',
            'DW-03' => "Total Dissolved Solids Dried at 180°C\n(2540D)",
            'DW-04' => 'In-House Laboratory Method (Turbidimetry)',
            'DW-05' => 'DPD Colorimetric Method',
            'DW-06' => 'Nitrate Electrode Method (4500-NO3-D)',
            'DW-07', 'DW-08', 'DW-09' => "Electrothermal-AAS Method (3113B)\nNitric Acid Digestion (3030E)",
            'DW-12' => "Threshold Odor Test\n(Sensory Evaluation Technique, 2150 B)",
            'DW-13' => 'EC Meter (2510 B)',
            'DW-14' => "Argentometric Method\n4500-Cl- B",
            'DW-15' => 'Titrimetric Method (2340 C)',
            'DW-16', 'DW-17' => 'Titrimetric Method (2340 C)',
            'DW-18' => "Phenanthroline Method\n3500 - Fe B",
            'DW-19' => 'Diazotization Method',
            'DW-20' => "Turbidimetric Method\n4500 - SO4-2 E",
            'MB-02A', 'MB-02B' => 'Multiple Tube Fermentation Technique* 9221, SMEWW',
            'WW-08' => '4500-H+ B – Electrometric Method',
            'WW-05' => '4500-O C – Azide Modification',
            'WW-02' => '5210 B 5-Day BOD Test',
            'WW-14' => '2540 D – Total Suspended Solids Dried at 103-105°C',
            'WW-12' => '2540 C – Total Dissolved Solids Dried at 180°C',
            'SA-25' => '2540 B – Total Solids Dried at 103-105°C',
            'SA-17' => '2540 F – Settleable Solids',
            'WW-07' => '5520 B – Liquid-Liquid, Partition-Gravimetric Method',
            'WW-06' => 'US EPA 352.1 – Colorimetric Brucine',
            'WW-01' => '4500-NH3 F – Phenate Method',
            'WW-09' => '4500-P E – Ascorbic Acid Method',
            'WW-04' => '2120 B – Visual Comparison Method',
            'WW-10' => '5540 C – Anionic Surfactants as MBAS',
            'WW-03' => '5220 B – Open Reflux Method',
            'WW-11' => '2550 B – Laboratory and Field Method',
            'SA-19' => '4500-SO4-2 – Turbidimetric Method',
            'SA-06' => '4500-Cl- B – Argentometric Method',
            'FM-01' => "Pour Plate Method, Plate Count Agar (PCA); BAM Online Chapter 3\nAOAC International Official Method of Analysis – Method 990.12\n3M PetrifilmTM Rapid Aerobic Count Plates, AOAC Certificate Number 121403",
            'FM-08', 'FM-11' => 'Multiple Tube Fermentation Technique; BAM Online',
            'FM-02' => 'Compact Dry Media (AOAC Performance Tested, Cert. #110402)',
            'FM-06' => 'Compact Dry Media (AOAC Performance Tested, Cert. #081001)',
            'FM-07' => '3M Petrifilm Salmonella Express (AOAC Certified)',
            'FM-03' => 'Reveal 2.0 Test for Listeria (AOAC-RI License No. 041101)',
            'FM-09', 'FM-05' => 'Pour Plate Method; BAM',
            'FM-10' => 'Compact Dry Media',
            // Nested matrix methods (FO27 Issue 4 parentheses under test names).
            'SM-01', 'SM-02' => 'Pour Plate Method',
            'SM-03', 'SM-04' => 'Compact Dry Media',
            'SM-05', 'SM-06' => 'Multiple Tube Fermentation Technique',
            'SM-07' => '3M Petrifilm Salmonella Express (AOAC Certified)',
            'SM-08', 'SM-09' => 'ICUMSA (Method GS2/3-47, 1994), ICUMSA and BAM',
            'MT-02' => 'HVG-Flame – AAS Method',
            'MT-11' => 'Cold Vapor HVG AAS Method',
            'MT-01', 'MT-03', 'MT-04', 'MT-05', 'MT-06', 'MT-07', 'MT-08', 'MT-09', 'MT-10', 'MT-12', 'MT-13', 'MT-14', 'MT-15', 'MT-16' => 'Flame – AAS Method',
            default => null,
        };
    }

    /**
     * Codes that print two matrix rows (Raw + Finish) with separate measured values.
     */
    public static function usesRawFinishResults(?string $code): bool
    {
        return in_array($code, ['DW-02', 'DW-03'], true);
    }

    /**
     * Metals result rows (Sample Preparation MT-17 is billable only).
     *
     * @return list<string>
     */
    public static function metalsResultTypeCodes(): array
    {
        return [
            'MT-01', 'MT-02', 'MT-03', 'MT-04', 'MT-05', 'MT-06', 'MT-07', 'MT-08',
            'MT-09', 'MT-10', 'MT-11', 'MT-12', 'MT-13', 'MT-14', 'MT-15', 'MT-16',
        ];
    }

    public static function metalsDetectionLimitPpm(?string $code): ?string
    {
        return match ($code) {
            'MT-02' => '6.0 x 10-5',
            'MT-03' => '0.002',
            'MT-05' => '0.01',
            'MT-06' => '0.01',
            'MT-07' => '0.02',
            'MT-08' => '0.003',
            'MT-09' => '0.02',
            'MT-10' => '0.01',
            'MT-11' => '2.0 x 10-4',
            'MT-12' => '0.02',
            default => null,
        };
    }

    /**
     * MDL line for the metals that were actually selected, in catalog order.
     *
     * @param  iterable<int, string|null>  $codes
     */
    public static function metalsDetectionLimitText(iterable $codes): ?string
    {
        $wanted = [];
        foreach ($codes as $code) {
            $code = trim((string) $code);
            if ($code !== '') {
                $wanted[$code] = true;
            }
        }

        $parts = [];
        foreach (self::definitions()[AnalysisCategory::Metals->value] ?? [] as $row) {
            $code = (string) ($row[0] ?? '');
            if (! isset($wanted[$code])) {
                continue;
            }
            $limit = self::metalsDetectionLimitPpm($code);
            if ($limit === null) {
                continue;
            }
            $parts[] = (string) ($row[1] ?? $code).' = '.$limit.' ppm';
        }

        if ($parts === []) {
            return null;
        }

        return 'Method Detection Limit: '.implode('; ', $parts);
    }

    /**
     * Residual Chlorine (DW-05): exclusive Finish | Chlorinated | Raw (dash only in Raw mode).
     */
    public static function usesResidualChlorineResults(?string $code): bool
    {
        return $code === 'DW-05';
    }

    /**
     * Acceptable-value text for a matrix variant (DW-02 pH, DW-03 TDS, DW-05 Cl).
     */
    public static function acceptableValuesForCodeVariant(?string $code, string $variant): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $key = strtolower(trim($variant));

        if ($code === 'DW-02') {
            return match ($key) {
                'raw' => '6.5-8.5',
                'finish' => '5-7 for product water that undergone reverse osmosis/distillation process',
                default => null,
            };
        }

        if ($code === 'DW-03') {
            return match ($key) {
                'raw' => '600 for product that has not undergone reverse osmosis / distillation process',
                'finish' => '<10 for product that undergone reverse osmosis/distillation process',
                default => null,
            };
        }

        if ($code === 'DW-05') {
            return match ($key) {
                'finish' => '<1.0 mg/L for RO water',
                'chlorinated' => '0.3 - 1.5 mg/L for chlorinated drinking water',
                'raw' => 'Not applicable to Raw Water',
                default => null,
            };
        }

        return null;
    }

    /**
     * Keep only the limit line for a span role / variant (strip siblings if both were stored together).
     *
     * @param  string|null  $variant  Explicit variant (finish|chlorinated|raw); preferred for DW-05.
     */
    public static function acceptableValuesForCodeRole(
        ?string $code,
        ?string $role,
        ?string $current = null,
        ?string $variant = null,
    ): ?string {
        $variantKey = strtolower(trim((string) $variant));
        if ($variantKey === '') {
            $roleKey = strtolower(trim((string) $role));
            $variantKey = match ($roleKey) {
                'start', 'raw' => 'raw',
                'continue', 'finish' => 'finish',
                'chlorinated' => 'chlorinated',
                default => '',
            };
        }
        if ($variantKey === '') {
            return $current;
        }

        $canonical = self::acceptableValuesForCodeVariant($code, $variantKey);
        if ($canonical === null) {
            return $current;
        }

        $text = trim((string) $current);
        if ($text === '') {
            return $canonical;
        }

        $siblings = match ($code) {
            'DW-05' => ['finish', 'chlorinated', 'raw'],
            'DW-02', 'DW-03' => ['raw', 'finish'],
            default => [],
        };
        foreach ($siblings as $sibling) {
            if ($sibling === $variantKey) {
                continue;
            }
            $other = self::acceptableValuesForCodeVariant($code, $sibling);
            if ($other !== null && str_contains($text, $other)) {
                return $canonical;
            }
        }

        return $text;
    }

    /**
     * PNSDW / Issue 7–11 Acceptable Values text for DW physico-chemical parameters.
     */
    public static function acceptableValuesForCode(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return match ($code) {
            'DW-01' => '10 color units',
            'DW-02' => (string) self::acceptableValuesForCodeVariant('DW-02', 'raw')
                ."\n".(string) self::acceptableValuesForCodeVariant('DW-02', 'finish'),
            'DW-03' => (string) self::acceptableValuesForCodeVariant('DW-03', 'raw')
                ."\n".(string) self::acceptableValuesForCodeVariant('DW-03', 'finish'),
            'DW-04' => '5 NTU',
            'DW-05' => (string) self::acceptableValuesForCodeVariant('DW-05', 'finish')
                ."\n".(string) self::acceptableValuesForCodeVariant('DW-05', 'chlorinated')
                ."\n".(string) self::acceptableValuesForCodeVariant('DW-05', 'raw'),
            'DW-06' => '50 mg/L',
            'DW-07', 'DW-08' => '0.01 mg/L',
            'DW-09' => '0.003 mg/L',
            'DW-12' => 'No objectionable odor',
            'DW-14' => '250 mg/L',
            'DW-15' => '300 mg/L as CaCO3',
            'DW-18' => '1.0 mg/L',
            'DW-19' => '3 mg/L',
            'DW-20' => '250 mg/L',
            default => null,
        };
    }

    /**
     * Combined result_value summary for list/complete checks (Raw + Finish assays).
     */
    public static function summarizeRawFinishResults(?string $raw, ?string $finish): ?string
    {
        $rawText = trim((string) $raw);
        $finishText = trim((string) $finish);
        if ($rawText === '' && $finishText === '') {
            return null;
        }

        $parts = [];
        if ($rawText !== '') {
            $parts[] = 'Raw: '.$rawText;
        }
        if ($finishText !== '') {
            $parts[] = 'Finish: '.$finishText;
        }

        return implode('; ', $parts);
    }

    /** Stored in result_raw when Residual Chlorine encode mode is Raw. */
    public const RESIDUAL_CHLORINE_RAW_SENTINEL = '-';

    /**
     * Combined result_value summary for Residual Chlorine (exclusive Finish | Chlorinated | Raw).
     */
    public static function summarizeResidualChlorineResults(
        ?string $finish,
        ?string $chlorinated,
        ?string $raw = null,
    ): ?string {
        $rawText = trim((string) $raw);
        if ($rawText !== '') {
            return 'Raw: '.self::RESIDUAL_CHLORINE_RAW_SENTINEL;
        }

        $finishText = trim((string) $finish);
        $chlorinatedText = trim((string) $chlorinated);
        if ($finishText === '' && $chlorinatedText === '') {
            return null;
        }

        $parts = [];
        if ($finishText !== '') {
            $parts[] = 'Finish: '.$finishText;
        }
        if ($chlorinatedText !== '') {
            $parts[] = 'Chlorinated: '.$chlorinatedText;
        }

        return implode('; ', $parts);
    }

    /**
     * Normalize exclusive Residual Chlorine encode fields (Finish | Chlorinated | Raw).
     *
     * @return array{0: ?string, 1: ?string, 2: ?string} [raw, finish, chlorinated]
     */
    public static function normalizeResidualChlorineEncode(
        ?string $raw,
        ?string $finish,
        ?string $chlorinated,
    ): array {
        $rawText = trim((string) $raw);
        $finishText = trim((string) $finish);
        $chlorinatedText = trim((string) $chlorinated);

        if ($rawText !== '') {
            return [self::RESIDUAL_CHLORINE_RAW_SENTINEL, null, null];
        }
        if ($finishText !== '') {
            return [null, $finishText, null];
        }
        if ($chlorinatedText !== '') {
            return [null, null, $chlorinatedText];
        }

        return [null, null, null];
    }

    /**
     * Issue 11 OLD physico sheet member codes (individual pay / types-only FO3), print order.
     *
     * @return list<string>
     */
    public static function drinkingWaterOldIssue11TypeCodes(): array
    {
        return [
            'DW-01', // Color
            'DW-03', // TDS
            'DW-13', // EC
            'DW-04', // Turbidity
            'DW-02', // pH
            'DW-14', // Chloride
            'DW-05', // Residual Chlorine
            'DW-15', // Total Hardness
            'DW-16', // Calcium Hardness
            'DW-17', // Magnesium Hardness
            'DW-18', // Iron
            'DW-06', // Nitrate
            'DW-19', // Nitrite
            'DW-20', // Sulfate
            'DW-12', // Odor
        ];
    }

    /**
     * Issue 18 wastewater physico sheet member codes (types-only FO2), print order.
     *
     * @return list<string>
     */
    public static function wastewaterPhysicoIssue18TypeCodes(): array
    {
        return [
            'WW-08', // Lab pH
            'WW-05', // Lab Dissolved Oxygen
            'WW-02', // BOD
            'WW-14', // TSS
            'WW-12', // TDS
            'SA-25', // Total Solids
            'SA-17', // Settleable Solids
            'WW-07', // Oil and Grease
            'WW-06', // Nitrate
            'WW-01', // Ammonia
            'WW-09', // Phosphate
            'WW-04', // Color
            'WW-10', // Surfactants
            'WW-03', // COD
            'WW-11', // Lab Temperature
            'SA-19', // Sulfate
            'SA-06', // Chloride
        ];
    }

    /**
     * FO2 default member codes for Abstracted water (subset of Issue 18), print order.
     * Wastewater and River water use the full {@see wastewaterPhysicoIssue18TypeCodes()} panel.
     *
     * @return list<string>
     */
    public static function wastewaterPhysicoAbstractedTypeCodes(): array
    {
        return ['WW-08', 'WW-05', 'WW-02', 'WW-14'];
    }

    /**
     * Wastewater sample-description kinds shown at intake (also printed on FO2).
     *
     * @return list<string>
     */
    public static function wastewaterKinds(): array
    {
        return ['Wastewater', 'River water', 'Abstracted water'];
    }

    /**
     * FO2 default type codes for a wastewater sample-description kind.
     *
     * @return list<string>
     */
    public static function wastewaterFo2DefaultTypeCodes(?string $kind): array
    {
        return match ($kind) {
            'Abstracted water' => self::wastewaterPhysicoAbstractedTypeCodes(),
            'Wastewater', 'River water' => self::wastewaterPhysicoIssue18TypeCodes(),
            default => [],
        };
    }

    public static function isWastewaterAbstractedKind(?string $description): bool
    {
        return trim((string) $description) === 'Abstracted water';
    }

    /**
     * FO2 under-table footer variant for a sample description kind.
     *
     * @return 'abstracted'|'full'
     */
    public static function fo2FooterVariant(?string $description): string
    {
        return self::isWastewaterAbstractedKind($description) ? 'abstracted' : 'full';
    }

    /**
     * Designer/print default NOTE block for FO2 (full WW/River sheet).
     */
    public static function fo2FullNotesText(): string
    {
        return <<<'TXT'
Notes:
1. This report shall not be reproduced, except in full, without the written approval of the laboratory.
2. The results and interpretations in this Test Report relate only to the samples received, coded and tested by the laboratory.
3. Measurement uncertainty is available upon request.
4. *Not PAB Accredited but EMB DENR accredited.
5. **Not EMB –DENR but PAB accredited.
6. ***Not PAB and EMB - DENR Accredited.
TXT;
    }

    /**
     * Designer/print default NOTE block for FO2A Abstracted water sheet.
     */
    public static function fo2AbstractedNotesText(): string
    {
        return <<<'TXT'
Notes:
1. This report shall not be reproduced, except in full, without the written approval of the laboratory.
2. The results and interpretations in this Test Report relate only to the samples received, coded and tested by the laboratory.
3. Measurement uncertainty is available upon request.
4. *Not EMB- DENR Accredited.
TXT;
    }

    public static function fo2ReferencesText(): string
    {
        return <<<'TXT'
References: SMEWW,24th Edition 2023 –(Method Reference) DENR–Environmental Management Bureau DAO No. 2016-08 and 2021-19
US – EPA
TXT;
    }

    public static function fo2LabConditionsText(string $variant = 'full'): string
    {
        // Full WW/River and Abstracted both print Temp/Humidity + NA under the matrix.
        return <<<'TXT'
Laboratory Conditions during the conduct of test: Temp =      °C ; Humidity =      %
NA – Not Applicable for specified water body .
TXT;
    }

    /**
     * Result form code for a wastewater sample-description kind.
     */
    public static function wastewaterPhysicoFormCode(?string $description): string
    {
        return self::isWastewaterAbstractedKind($description) ? 'LSP-7.8-FO2A' : 'LSP-7.8-FO2';
    }

    /**
     * Proximate Analysis result sheet member codes (types-only F016-PROX), print order.
     *
     * @return list<string>
     */
    public static function proximateAnalysisTypeCodes(): array
    {
        return ['PX-01', 'PX-02', 'PX-03', 'PX-04', 'PX-05', 'PX-06', 'PX-07', 'PX-08', 'PX-09', 'PX-10', 'PX-11'];
    }

    /**
     * Milk Sample Test Result Form (F016-MILK) member codes, print order.
     *
     * @return list<string>
     */
    public static function milkAnalysisTypeCodes(): array
    {
        return ['MK-01', 'MK-02', 'MK-03', 'MK-04', 'MK-05'];
    }

    /**
     * Water Activity Test Result Form (F016-WA) member codes, print order.
     *
     * @return list<string>
     */
    public static function waterActivityTypeCodes(): array
    {
        return ['WA-01'];
    }

    /**
     * Chloramphenicol Test Result Form (F016-CAP) member codes, print order.
     *
     * @return list<string>
     */
    public static function chloramphenicolTypeCodes(): array
    {
        return ['FD-CAP'];
    }

    /**
     * Nitrite Test Result Form (F016-NO2) member codes, print order.
     *
     * @return list<string>
     */
    public static function nitriteTypeCodes(): array
    {
        return ['FD-NO2'];
    }

    /**
     * Microbiological Food Test Result Form (FO26 Issue 4) member codes, print order.
     *
     * @return list<string>
     */
    public static function foodMicroFo26TypeCodes(): array
    {
        return ['FM-01', 'FM-08', 'FM-11', 'FM-02', 'FM-06', 'FM-07', 'FM-03', 'FM-09', 'FM-05', 'FM-10'];
    }

    /**
     * Food Micro Sugar Test Result Form (FO27 Issue 4) member codes, print order.
     *
     * @return list<string>
     */
    public static function foodMicroSugarTypeCodes(): array
    {
        return ['SM-01', 'SM-02', 'SM-03', 'SM-04', 'SM-05', 'SM-06', 'SM-07', 'SM-08', 'SM-09'];
    }

    /**
     * Sheet-facing labels for FO2 Issue 18 (print / intake display).
     *
     * @return array<string, string>
     */
    public static function wastewaterPhysicoIssue18Labels(): array
    {
        return [
            'WW-08' => 'Lab pH',
            'WW-05' => 'Lab Dissolved Oxygen (mg/L)',
            'WW-02' => 'Biochemical Oxygen Demand (mg/L)',
            'WW-14' => 'Total Suspended Solids (mg/L)',
            'WW-12' => 'Total Dissolved Solids (mg/L)',
            'SA-25' => 'Total Solids (mg/L)',
            'SA-17' => 'Settleable Solids (mL/L)',
            'WW-07' => 'Oil and Grease (mg/L)',
            'WW-06' => 'Nitrate as NO3-N (mg/L)',
            'WW-01' => 'Ammonia as NH3-N (mg/L)',
            'WW-09' => 'Phosphate (mg/L)',
            'WW-04' => 'Color (TCU)',
            'WW-10' => 'Surfactants (mg/L)',
            'WW-03' => 'Chemical Oxygen Demand (mg/L)',
            'WW-11' => 'Lab Temperature (°C)',
            'SA-19' => 'Sulfate (mg/L)',
            'SA-06' => 'Chloride (mg/L)',
        ];
    }

    /**
     * Per-test caption under the name on FO2 Issue 18 (Time vs Date/Time of Analysis).
     */
    public static function wastewaterPhysicoIssue18AnalysisCaption(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return match ($code) {
            'WW-14', 'WW-12', 'SA-25', 'SA-17', 'WW-07', 'WW-10', 'SA-06' => 'Date/Time of Analysis',
            'WW-08', 'WW-05', 'WW-02', 'WW-06', 'WW-01', 'WW-09', 'WW-04', 'WW-03', 'WW-11', 'SA-19' => 'Time of Analysis',
            default => null,
        };
    }

    /**
     * Whether the analysis type is on the FO2 / FO2A wastewater physico panel.
     */
    public static function isWastewaterPhysicoPanelCode(?string $code): bool
    {
        if ($code === null || $code === '') {
            return false;
        }

        return in_array($code, self::wastewaterPhysicoIssue18TypeCodes(), true);
    }

    /**
     * Official paper accreditation mark for FO2 TEST names (*, **, ***), or null for none.
     *
     * @return '*'|'**'|'***'|null
     */
    public static function wastewaterAccreditationFootnoteTier(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return match ($code) {
            'WW-09', 'WW-11', 'SA-06' => '*',
            'WW-12', 'SA-25', 'SA-17' => '**',
            'WW-04', 'WW-10', 'WW-03', 'SA-19' => '***',
            default => null,
        };
    }

    /**
     * Prefix a test label with the accreditation footnote tier (paper style: *Phosphate).
     */
    public static function wastewaterTestLabelWithFootnote(string $baseName, ?string $tier): string
    {
        $name = preg_replace('/^\*{1,3}\s*/u', '', trim($baseName)) ?? trim($baseName);
        if ($tier === null || $tier === '') {
            return $name;
        }

        if (! in_array($tier, ['*', '**', '***'], true)) {
            return $name;
        }

        return $tier.$name;
    }

    /**
     * Resolve effective footnote tier for a stored JO analysis line.
     * null column → catalog default; empty string → explicit none; otherwise stored mark.
     *
     * @return '*'|'**'|'***'|null
     */
    public static function resolveWastewaterAccreditationFootnote(?string $stored, ?string $code): ?string
    {
        if (! self::isWastewaterPhysicoPanelCode($code)) {
            return null;
        }

        if ($stored === null) {
            return self::wastewaterAccreditationFootnoteTier($code);
        }

        $trimmed = trim($stored);
        if ($trimmed === '') {
            return null;
        }

        return in_array($trimmed, ['*', '**', '***'], true) ? $trimmed : null;
    }

    /**
     * Distinct internal Controlled Form codes for package result sheets.
     *
     * @return array<string, array{form_code: string, name: string, official: string, revision: string, effective: string, package_code?: string, type_codes?: list<string>, source_pdf?: string}>
     */
    public static function resultFormRegistry(): array
    {
        return [
            'micro_non_drinking_water' => [
                'form_code' => 'LSP-7.8-FO4',
                'name' => 'Microbiological Examination — Non-Drinking Water Result Form',
                'official' => 'LSP 7.8 FO4',
                'revision' => '01',
                'effective' => 'TBD',
                'package_code' => 'PKG-MIC-NDW',
                'source_pdf' => 'lsp-7.8-fo4-micro-non-drinking-water.pdf',
            ],
            'micro_drinking_water' => [
                'form_code' => 'LSP-7.8-FO5',
                'name' => 'Microbiological Examination — Drinking Water Result Form',
                'official' => 'LSP 7.8 FO5',
                'revision' => '01',
                'effective' => 'TBD',
                // Prefer package bind; type_codes kept as fallback sync for member slots.
                'package_code' => 'PKG-DW-BACT',
                'type_codes' => ['DW-BACT-HPC', 'DW-BACT-TC', 'DW-BACT-FC'],
                'source_pdf' => 'lsp-7.8-fo5-micro-drinking-water.pdf',
            ],
            'ww_physico' => [
                'form_code' => 'LSP-7.8-FO2',
                'name' => 'Physico-Chemical Test Results — Wastewater',
                'official' => 'LSP 7.8 FO2',
                'revision' => '17/Issue 18',
                'effective' => '09/16/2026',
                'type_codes' => self::wastewaterPhysicoIssue18TypeCodes(),
                'source_pdf' => 'PC Wastewater result Form Issue 18 09012026 blank.pdf',
            ],
            'ww_physico_abstracted' => [
                'form_code' => 'LSP-7.8-FO2A',
                'name' => 'Physico-Chemical Test Results — Abstracted Water',
                'official' => 'LSP 7.8 FO2',
                'revision' => '17/Issue 18',
                'effective' => '09/16/2026',
                'type_codes' => self::wastewaterPhysicoIssue18TypeCodes(),
                'source_pdf' => 'PC Wastewater result Form Issue 18 09012026 blank.pdf',
            ],
            'proximate' => [
                'form_code' => 'LSP-7.8-F016-PROX',
                'name' => 'Proximate Analysis Result Form',
                'official' => 'LSP 7.8 F016',
                'revision' => '05/Issue 06',
                'effective' => '07/15/2026',
                'type_codes' => ['PX-01', 'PX-02', 'PX-03', 'PX-04', 'PX-05', 'PX-06', 'PX-07', 'PX-08', 'PX-09', 'PX-10', 'PX-11'],
                'source_pdf' => 'Proximate Analysis Result Form.pdf',
            ],
            'food_micro' => [
                'form_code' => 'LSP-7.8-FO26',
                'name' => 'Microbiological Food Test Result Form',
                'official' => 'LSP 7.8 FO26',
                'revision' => '03/Issue 04',
                'effective' => '07/15/2026',
                'type_codes' => self::foodMicroFo26TypeCodes(),
                'source_pdf' => 'micro food test result form issue4 07152026.pdf',
            ],
            'food_micro_sugar' => [
                'form_code' => 'LSP-7.8-FO27',
                'name' => 'Food Micro Sugar Test Result Form',
                'official' => 'LSP 7.8 FO27',
                'revision' => '03/Issue 04',
                'effective' => '07/15/2026',
                'type_codes' => self::foodMicroSugarTypeCodes(),
                'source_pdf' => 'FOOD MICRO SUGAR Test Result form Issue4 07152026.pdf',
            ],
            'water_activity' => [
                'form_code' => 'LSP-7.8-F016-WA',
                'name' => 'Water Activity Test Result Form',
                'official' => 'LSP 7.8 F016',
                'revision' => '05/Issue 06',
                'effective' => '07/15/2026',
                'type_codes' => self::waterActivityTypeCodes(),
                'source_pdf' => 'water activity test result form - blank.pdf',
            ],
            'nitrite_food' => [
                'form_code' => 'LSP-7.8-F016-NO2',
                'name' => 'Nitrite Test Result Form',
                'official' => 'LSP 7.8 F016',
                'revision' => '03/Issue 04',
                'effective' => '07/15/2026',
                'type_codes' => self::nitriteTypeCodes(),
                'source_pdf' => 'Nitrite Test Result Form-blank.pdf',
            ],
            'chloramphenicol' => [
                'form_code' => 'LSP-7.8-F016-CAP',
                'name' => 'Chloramphenicol Test Result Form',
                'official' => 'LSP 7.8 F016',
                'revision' => '03/Issue 04',
                'effective' => '07/15/2026',
                'type_codes' => self::chloramphenicolTypeCodes(),
                'source_pdf' => 'Chloramphenicol Test Result Form-blank.pdf',
            ],
            'phytochemical' => [
                'form_code' => 'LSP-7.8-F016-PHYTO',
                'name' => 'Phytochemical Qualitative Test Result Form',
                'official' => 'LSP 7.8 F016',
                'revision' => '05/Issue 06',
                'effective' => '07/15/2026',
                'type_codes' => ['PHY-01', 'PHY-02', 'PHY-03', 'PHY-04', 'PHY-05', 'PHY-06', 'PHY-07', 'PHY-08', 'PHY-09'],
            ],
            'milk' => [
                'form_code' => 'LSP-7.8-F016-MILK',
                'name' => 'Milk Sample Test Result Form',
                'official' => 'LSP 7.8 F016',
                'revision' => '05/Issue 06',
                'effective' => '07/15/2026',
                'type_codes' => self::milkAnalysisTypeCodes(),
                'source_pdf' => 'Milk Sample Test Result Form.pdf',
            ],
            'dw_physico' => [
                'form_code' => 'LSP-7.8-FO37',
                'name' => 'Physico-Chemical Analysis Report — Drinking Water',
                'official' => 'LSP 7.8 FO37',
                'revision' => '06/Issue 07',
                'effective' => '09/01/2026',
                'package_code' => 'PKG-DW-PHYSICO',
                'source_pdf' => 'PC Drinking Water Test Result Form Issue 7 09012026.pdf',
            ],
            'metals' => [
                'form_code' => 'LSP-7.8-FO28',
                'name' => 'Metal Test Results',
                'official' => 'LSP 7.8 FO28',
                'revision' => '00/Issue 1',
                'effective' => '06/01/2024',
                'type_codes' => self::metalsResultTypeCodes(),
            ],
            'dw_physico_old' => [
                'form_code' => 'LSP-7.8-FO3',
                'name' => 'Physico-Chemical Analysis Report — Drinking Water (Issue 11)',
                'official' => 'LSP 7.8 FO3',
                'revision' => '10/Issue 11',
                'effective' => '09/01/2026',
                'type_codes' => self::drinkingWaterOldIssue11TypeCodes(),
                // Blank must keep letterhead/header only: strip notes + signature chrome
                // below the table so anchored overlays (options.anchor) can flow under the matrix.
                'source_pdf' => 'PC OLD Drinking Water Test Result Form Issue 11 blank.pdf',
            ],
        ];
    }

    /**
     * @return array{lab: string, form: string, code: string, revision: string, effective: string, variant: string}
     */
    public static function documentControl(?JobOrderVariant $variant = null): array
    {
        $variant ??= JobOrderVariant::General;

        return match ($variant) {
            JobOrderVariant::Aqua => [
                'lab' => 'NPPC-ADL',
                'form' => 'LSP 7.1 FO4',
                'code' => 'NPPC-ADL',
                'revision' => '02/Issue 03',
                'effective' => '08/01/2026',
                'variant' => JobOrderVariant::Aqua->value,
            ],
            JobOrderVariant::General => [
                'lab' => 'NPPC-ADL',
                'form' => 'LSP 7.1 FO1',
                'code' => 'NPPC-ADL',
                'revision' => '10/Issue 11',
                'effective' => '09/01/2026',
                'variant' => JobOrderVariant::General->value,
            ],
        };
    }

    public static function documentControlForJobOrder(?JobOrder $jobOrder): array
    {
        return self::documentControl(self::variantForClassification($jobOrder?->classification));
    }

    public static function variantForClassification(?string $classification): JobOrderVariant
    {
        $value = mb_strtolower(trim((string) $classification));

        if ($value === '') {
            return JobOrderVariant::General;
        }

        $isAqua = str_contains($value, 'aqua');
        $isWaterGeneral = str_contains($value, 'potability') || str_contains($value, 'wastewater');

        if ($isAqua && ! $isWaterGeneral) {
            return JobOrderVariant::Aqua;
        }

        return JobOrderVariant::General;
    }

    public static function isAquaClassification(?string $classification): bool
    {
        return self::variantForClassification($classification) === JobOrderVariant::Aqua;
    }

    /** @return list<string> */
    public static function classifications(): array
    {
        return ['Aqua', 'Potability', 'Wastewater', 'Agriculture', 'Academic/Research', 'Others'];
    }

    /** @return list<string> */
    public static function classificationsForVariant(JobOrderVariant $variant): array
    {
        return match ($variant) {
            JobOrderVariant::Aqua => ['Aqua', 'Agriculture', 'Academic/Research', 'Others'],
            JobOrderVariant::General => self::classifications(),
        };
    }

    /** @return list<string> */
    public static function ownershipTypes(): array
    {
        return ['Private', 'Commercial', 'Public'];
    }

    /** @return list<string> */
    public static function potabilitySampleSources(): array
    {
        return ['Local water district', 'Tank', 'Faucet', 'Deepwell', 'Others'];
    }

    /** @return list<string> */
    public static function aquaSampleSources(): array
    {
        return ['Sea Water', 'Brackish Water', 'River Water', 'Others'];
    }

    /**
     * Paid packages offered at intake. Everything else is individual (types-only).
     *
     * @return list<string>
     */
    public static function activePaidPackageCodes(): array
    {
        return [
            'PKG-DW-PHYSICO',
            'PKG-DW-BACT',
            'PKG-MIC-NDW',
        ];
    }

    /**
     * Codes hidden from intake browse (active package members only).
     *
     * @return list<string>
     */
    public static function packageOnlyCodes(): array
    {
        return ['MB-02A', 'MB-02B', 'DW-BACT-HPC', 'DW-BACT-TC', 'DW-BACT-FC'];
    }
}
