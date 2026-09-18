<?php

namespace App\Enums;

enum AnalysisCategory: string
{
    case SpecialAnalysis = 'special_analysis';
    case WasteWater = 'waste_water';
    case FoodProductsMicrobiological = 'food_products_microbiological';
    case ProximateAnalysis = 'proximate_analysis';
    case Nutrifacts = 'nutrifacts';
    case OtherFoodAnalysis = 'other_food_analysis';
    case DrinkingWater = 'drinking_water';
    case LimeAnalysis = 'lime_analysis';
    case Fertilizer = 'fertilizer';
    case SoilAnalysisAquaculture = 'soil_analysis_aquaculture';
    case Metals = 'metals';
    case AdultFryPrawnAquaculture = 'adult_fry_prawn_aquaculture';
    case SoilAnalysisAgriculture = 'soil_analysis_agriculture';
    case WaterAnalysisAquaculture = 'water_analysis_aquaculture';
    /** @deprecated Retained for FO4/FO5 package member types */
    case Microbiological = 'microbiological';
    case Phytochemical = 'phytochemical';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SpecialAnalysis => 'Special Analysis',
            self::WasteWater => 'Waste Water',
            self::FoodProductsMicrobiological => 'Food Products - Microbiological Test',
            self::ProximateAnalysis => 'Proximate Analysis',
            self::Nutrifacts => 'Nutrifacts',
            self::OtherFoodAnalysis => 'Other Food Analysis',
            self::DrinkingWater => 'Drinking Water',
            self::LimeAnalysis => 'Lime Analysis',
            self::Fertilizer => 'Fertilizer',
            self::SoilAnalysisAquaculture => 'Soil Analysis - Aquaculture',
            self::Metals => 'Metals',
            self::AdultFryPrawnAquaculture => 'Adult/Fry Prawn Analysis - Aquaculture',
            self::SoilAnalysisAgriculture => 'Soil Analysis - Agriculture',
            self::WaterAnalysisAquaculture => 'Water Analysis - Aquaculture',
            self::Microbiological => 'Microbiological (Result forms)',
            self::Phytochemical => 'Phytochemical',
            self::Other => 'Other',
        };
    }

    public function catalogScope(): CatalogScope
    {
        return match ($this) {
            self::SoilAnalysisAquaculture,
            self::AdultFryPrawnAquaculture,
            self::WaterAnalysisAquaculture => CatalogScope::Aqua,
            self::Metals => CatalogScope::Both,
            default => CatalogScope::NonAqua,
        };
    }
}
