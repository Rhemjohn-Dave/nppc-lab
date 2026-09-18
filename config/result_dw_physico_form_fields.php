<?php

/**
 * Field blueprint for LSP-7.8-FO37.
 * Captured from Form Designer ACTIVE revision 05
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
      'y' => 37.3,
      'w' => 74.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
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
      'y' => 41.3,
      'w' => 76.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
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
      'y' => 45.3,
      'w' => 68.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
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
      'y' => 49.3,
      'w' => 71.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
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
      'y' => 53.6,
      'w' => 68.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
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
      'x' => 159.0,
      'y' => 37.3,
      'w' => 42.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
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
      'x' => 155.0,
      'y' => 41.3,
      'w' => 46.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
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
      'x' => 154.0,
      'y' => 45.3,
      'w' => 47.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
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
      'x' => 145.0,
      'y' => 49.3,
      'w' => 56.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.report_date',
    ),
    9 => 
    array (
      'name' => 'dw_physico_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 18.635,
      'y' => 60.051,
      'w' => 176.976,
      'h' => 113.684,
      'font_size' => 7.0,
      'font_family' => 'times',
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
            'font_size' => 9,
          ),
          1 => 
          array (
            'key' => 'method',
            'label' => 'Method',
            'width_pct' => 33,
            'align' => 'L',
            'header_align' => 'C',
            'font_size' => 9,
          ),
          2 => 
          array (
            'key' => 'result',
            'label' => 'Results',
            'width_pct' => 10,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 9,
          ),
          3 => 
          array (
            'key' => 'acceptable_values',
            'label' => 'Acceptable Values',
            'width_pct' => 23,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 9,
          ),
          4 => 
          array (
            'key' => 'remarks',
            'label' => 'Remarks',
            'width_pct' => 11,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 9,
          ),
        ),
        'row_height_mm' => 7.7,
        'header_row_height_mm' => 5,
        'header_row' => true,
        'border' => true,
        'preview_rows' => 9,
        'test_name_bold' => true,
        'header_bold' => true,
        'method_font_size' => 9,
        'header_font_size' => 8,
        'preview_data' => 
        array (
          0 => 
          array (
            'test' => 'Color (Apparent Color)',
            'test_method' => 'Colorimetric Method (2120 B)',
            'result' => NULL,
            'method' => 'Colorimetric Method',
          ),
          1 => 
          array (
            'test' => 'Total Dissolved Solids (mg/L)',
            'test_method' => 'Total Dissolved Solids Dried at 180°C (2540D)',
            'result' => NULL,
          ),
          2 => 
          array (
            'test' => 'Turbidity (NTU)',
            'test_method' => 'In-House Laboratory Method (Turbidimetry)',
            'result' => NULL,
          ),
          3 => 
          array (
            'test' => 'pH',
            'test_method' => 'Electrometric Method (4500-H+ B)',
            'result' => NULL,
          ),
          4 => 
          array (
            'test' => 'Residual Chlorine (mg/L)',
            'test_method' => 'DPD Colorimetric Method',
            'result' => NULL,
          ),
          5 => 
          array (
            'test' => 'Nitrate (mg/L)',
            'test_method' => 'Nitrate Electrode Method (4500-NO3-D)',
            'result' => NULL,
          ),
          6 => 
          array (
            'test' => 'Arsenic (mg/L)',
            'test_method' => 'Electrothermal-AAS Method (3113B)
Nitric Acid Digestion (3030E)',
            'result' => NULL,
          ),
          7 => 
          array (
            'test' => 'Lead (mg/L)',
            'test_method' => 'Electrothermal-AAS Method (3113B)
Nitric Acid Digestion (3030E)',
            'result' => NULL,
          ),
          8 => 
          array (
            'test' => 'Cadmium (mg/L)',
            'test_method' => 'Electrothermal-AAS Method (3113B)
Nitric Acid Digestion (3030E)',
            'result' => NULL,
          ),
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
      'y' => 210.0,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 11.0,
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
      'x' => 67.529,
      'y' => 218.8,
      'w' => 20.942,
      'h' => 4.2,
      'font_size' => 11.0,
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
      'x' => 127.0,
      'y' => 210.0,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 11.0,
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
      'x' => 166.427,
      'y' => 219.0,
      'w' => 23.147,
      'h' => 4.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_prc_2',
    ),
  ),
);
