<?php

/**
 * Field blueprint for LSP-7.8-FO3.
 * Captured from Form Designer ACTIVE revision 1
 * via `php artisan controlled-forms:export-blueprints`.
 */

return array (
  'page' => 
  array (
    'width' => 215.9,
    'height' => 279.4,
    'unit' => 'mm',
  ),
  'fields' => 
  array (
    0 => 
    array (
      'name' => 'results.customer',
      'label' => 'Customer',
      'type' => 'text',
      'page' => 1,
      'x' => 42.0,
      'y' => 33.0,
      'w' => 78.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.customer',
    ),
    1 => 
    array (
      'name' => 'results.address',
      'label' => 'Address',
      'type' => 'text',
      'page' => 1,
      'x' => 40.0,
      'y' => 37.0,
      'w' => 80.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.address',
    ),
    2 => 
    array (
      'name' => 'results.ref_no',
      'label' => 'Reference No',
      'type' => 'text',
      'page' => 1,
      'x' => 48.0,
      'y' => 41.0,
      'w' => 72.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.ref_no',
    ),
    3 => 
    array (
      'name' => 'results.control_no',
      'label' => 'Control No',
      'type' => 'text',
      'page' => 1,
      'x' => 45.0,
      'y' => 45.0,
      'w' => 75.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.control_no',
    ),
    4 => 
    array (
      'name' => 'results.sample_code',
      'label' => 'Sample Code',
      'type' => 'text',
      'page' => 1,
      'x' => 48.0,
      'y' => 49.2,
      'w' => 72.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sample_code',
    ),
    5 => 
    array (
      'name' => 'results.collection_datetime',
      'label' => 'Date/Time of Collection',
      'type' => 'text',
      'page' => 1,
      'x' => 165.464,
      'y' => 32.8,
      'w' => 42.0,
      'h' => 4.2,
      'font_size' => 8.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.collection_datetime',
    ),
    6 => 
    array (
      'name' => 'results.receipt_at',
      'label' => 'Date/Time Submitted',
      'type' => 'text',
      'page' => 1,
      'x' => 155.5,
      'y' => 37.0,
      'w' => 47.0,
      'h' => 4.2,
      'font_size' => 8.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.receipt_at',
    ),
    7 => 
    array (
      'name' => 'results.analysis_datetime',
      'label' => 'Date/Time Analyzed',
      'type' => 'text',
      'page' => 1,
      'x' => 155.0,
      'y' => 41.0,
      'w' => 48.0,
      'h' => 4.2,
      'font_size' => 8.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analysis_datetime',
    ),
    8 => 
    array (
      'name' => 'results.report_date',
      'label' => 'Date Reported',
      'type' => 'text',
      'page' => 1,
      'x' => 148.0,
      'y' => 45.0,
      'w' => 54.0,
      'h' => 4.2,
      'font_size' => 8.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.report_date',
    ),
    9 => 
    array (
      'name' => 'dw_old_fo3_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 25.146,
      'y' => 53.858,
      'w' => 171.568,
      'h' => 181.0,
      'font_size' => 8.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'table_config' => 
      array (
        'columns' => 
        array (
          0 => 
          array (
            'key' => 'test',
            'label' => 'Tests Performed',
            'width_pct' => 23,
            'align' => 'L',
            'header_align' => 'C',
            'font_size' => 8,
          ),
          1 => 
          array (
            'key' => 'method',
            'label' => 'Method',
            'width_pct' => 33,
            'align' => 'L',
            'header_align' => 'C',
            'font_size' => 8,
          ),
          2 => 
          array (
            'key' => 'result',
            'label' => 'Results',
            'width_pct' => 10,
            'align' => 'C',
            'header_align' => 'C',
          ),
          3 => 
          array (
            'key' => 'acceptable_values',
            'label' => 'Acceptable Values',
            'width_pct' => 23,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 7.5,
          ),
          4 => 
          array (
            'key' => 'remarks',
            'label' => 'Remarks',
            'width_pct' => 11,
            'align' => 'C',
            'header_align' => 'C',
          ),
        ),
        'row_height_mm' => 0,
        'header_row_height_mm' => 0,
        'header_row' => true,
        'border' => true,
        'preview_rows' => 15,
        'test_name_bold' => true,
        'header_bold' => true,
        'method_font_size' => 5,
        'header_font_size' => 9,
        'preview_data' => 
        array (
          0 => 
          array (
            'test' => 'Color (Apparent Color)',
            'test_method' => 'Colorimetric Method (2120 B)',
            'test_detail' => NULL,
            'method' => 'Colorimetric Method (2120 B)',
            'acceptable_values' => '10 color units',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          1 => 
          array (
            'test' => 'Total Dissolved Solids (mg/L)',
            'test_method' => 'Total Dissolved Solids Dried at 180°C
(2540D)',
            'test_detail' => NULL,
            'method' => 'Total Dissolved Solids Dried at 180°C
(2540D)',
            'acceptable_values' => '600 for product that has not undergone reverse osmosis / distillation process
<10 for product that undergone reverse osmosis/distillation process',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          2 => 
          array (
            'test' => 'Electrical Conductivity (µS/cm)',
            'test_method' => 'EC Meter (2510 B)',
            'test_detail' => NULL,
            'method' => 'EC Meter (2510 B)',
            'acceptable_values' => NULL,
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          3 => 
          array (
            'test' => 'Turbidity (NTU)',
            'test_method' => 'In-House Laboratory Method (Turbidimetry)',
            'test_detail' => NULL,
            'method' => 'In-House Laboratory Method (Turbidimetry)',
            'acceptable_values' => '5 NTU',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          4 => 
          array (
            'test' => 'pH',
            'test_method' => 'Electrometric Method (4500-H+ B)',
            'test_detail' => NULL,
            'method' => 'Electrometric Method (4500-H+ B)',
            'acceptable_values' => '6.5-8.5
5-7 for product water that undergone reverse osmosis/distillation process',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          5 => 
          array (
            'test' => 'Chloride (mg/L)',
            'test_method' => 'Argentometric Method
4500-Cl- B',
            'test_detail' => NULL,
            'method' => 'Argentometric Method 4500-Cl- B',
            'acceptable_values' => '250 mg/L',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          6 => 
          array (
            'test' => 'Residual Chlorine (mg/L)',
            'test_method' => 'DPD Colorimetric Method',
            'test_detail' => NULL,
            'method' => 'DPD Colorimetric Method',
            'acceptable_values' => '<1.0 mg/L for RO water
0.3 - 1.5 mg/L for chlorinated drinking water
Not applicable to Raw Water',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          7 => 
          array (
            'test' => 'Total Hardness (mg/L)',
            'test_method' => 'Titrimetric Method (2340 C)',
            'test_detail' => NULL,
            'method' => 'Titrimetric Method (2340 C)',
            'acceptable_values' => '300 mg/L as CaCO3',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          8 => 
          array (
            'test' => 'Calcium Hardness (mg/L)',
            'test_method' => 'Titrimetric Method (2340 C)',
            'test_detail' => NULL,
            'method' => 'Titrimetric Method (2340 C)',
            'acceptable_values' => NULL,
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          9 => 
          array (
            'test' => 'Magnesium Hardness (mg/L)',
            'test_method' => 'Titrimetric Method (2340 C)',
            'test_detail' => NULL,
            'method' => 'Titrimetric Method (2340 C)',
            'acceptable_values' => NULL,
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          10 => 
          array (
            'test' => 'Iron (mg/L)',
            'test_method' => 'Phenanthroline Method
3500 - Fe B',
            'test_detail' => NULL,
            'method' => 'Phenanthroline Method
3500 - Fe B',
            'acceptable_values' => '1.0 mg/L',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          11 => 
          array (
            'test' => 'Nitrate (mg/L)',
            'test_method' => 'Nitrate Electrode Method (4500-NO3-D)',
            'test_detail' => NULL,
            'method' => 'Nitrate Electrode Method (4500-NO3-D)',
            'acceptable_values' => '50 mg/L',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          12 => 
          array (
            'test' => 'Nitrite (mg/L)',
            'test_method' => 'Diazotization Method',
            'test_detail' => NULL,
            'method' => 'Diazotization Method',
            'acceptable_values' => '3 mg/L',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          13 => 
          array (
            'test' => 'Sulfate (mg/L)',
            'test_method' => 'Turbidimetric Method
4500 - SO4-2 E',
            'test_detail' => NULL,
            'method' => 'Turbidimetric Method 4500 - SO4-2 E',
            'acceptable_values' => '250 mg/L',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
          14 => 
          array (
            'test' => 'Odor',
            'test_method' => 'Threshold Odor Test
(Sensory Evaluation Technique, 2150 B)',
            'test_detail' => NULL,
            'method' => 'Threshold Odor Test
(Sensory Evaluation Technique, 2150 B)',
            'acceptable_values' => 'No objectionable odor',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
          ),
        ),
        'stretch_body' => false,
        'row_heights_mm' => 
        array (
          'total dissolved solids (mg/l)' => 20,
          'chloride (mg/l)' => 3,
        ),
      ),
    ),
    10 => 
    array (
      'name' => 'results.analyst_name',
      'label' => 'Analyzed by',
      'type' => 'text',
      'page' => 1,
      'x' => 12.0,
      'y' => 222.0,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name',
    ),
    11 => 
    array (
      'name' => 'results.analyst_prc',
      'label' => 'Analyzed by PRC',
      'type' => 'text',
      'page' => 1,
      'x' => 68.189,
      'y' => 231.0,
      'w' => 14.511,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_prc',
    ),
    12 => 
    array (
      'name' => 'results.analyst_name_2',
      'label' => 'Certified Correct',
      'type' => 'text',
      'page' => 1,
      'x' => 126.714,
      'y' => 222.0,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name_2',
    ),
    13 => 
    array (
      'name' => 'results.analyst_prc_2',
      'label' => 'Certified Correct PRC',
      'type' => 'text',
      'page' => 1,
      'x' => 167.278,
      'y' => 231.0,
      'w' => 18.186,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_prc_2',
    ),
  ),
);
