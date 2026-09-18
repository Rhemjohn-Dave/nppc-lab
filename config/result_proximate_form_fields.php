<?php

/**
 * Field blueprint for LSP-7.8-F016-PROX.
 * Captured from Form Designer ACTIVE revision 07
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
      'x' => 72.947,
      'y' => 38.832,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
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
      'x' => 72.947,
      'y' => 42.832,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.address',
    ),
    2 => 
    array (
      'name' => 'results.ref_no',
      'label' => 'Ref. No.',
      'type' => 'text',
      'page' => 1,
      'x' => 72.947,
      'y' => 59.031,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.ref_no',
    ),
    3 => 
    array (
      'name' => 'results.report_date',
      'label' => 'Report',
      'type' => 'date',
      'page' => 1,
      'x' => 72.947,
      'y' => 55.031,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.report_date',
    ),
    4 => 
    array (
      'name' => 'results.sampling_datetime',
      'label' => 'Date & Time of Sampling',
      'type' => 'date',
      'page' => 1,
      'x' => 72.947,
      'y' => 51.031,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sampling_datetime',
    ),
    5 => 
    array (
      'name' => 'dynamic_test_matrix_6',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 39.285,
      'y' => 73.02,
      'w' => 142.162,
      'h' => 126.046,
      'font_size' => 11.0,
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
            'label' => 'TEST',
            'width_pct' => 46,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 11,
            'sublabel' => NULL,
            'sublabels' => NULL,
          ),
          1 => 
          array (
            'key' => 'result',
            'label' => 'Control Number',
            'label_data_source' => 'results.control_no',
            'sublabel' => 'Sample Description',
            'sublabel_data_source' => 'results.sample_description',
            'sublabel_align' => 'C',
            'width_pct' => 52.326,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 11,
          ),
        ),
        'row_height_mm' => 9,
        'header_row_height_mm' => 15,
        'header_row' => true,
        'border' => true,
        'preview_rows' => 11,
        'test_name_bold' => true,
        'header_bold' => true,
        'method_font_size' => 9,
        'preview_data' => 
        array (
          0 => 
          array (
            'test' => '%Calorie',
            'test_method' => NULL,
            'result' => NULL,
          ),
          1 => 
          array (
            'test' => '%Potassium',
            'test_method' => NULL,
            'result' => NULL,
          ),
          2 => 
          array (
            'test' => '%Sample Preparation',
            'test_method' => NULL,
            'result' => NULL,
          ),
          3 => 
          array (
            'test' => '%Fats',
            'test_method' => 'Soxhlet Extraction Method',
            'result' => NULL,
          ),
          4 => 
          array (
            'test' => 'Protein',
            'test_method' => 'Kjeldahl Method',
            'result' => NULL,
          ),
          5 => 
          array (
            'test' => 'Moisture',
            'test_method' => 'Gravimetric Oven Drying at 105°C',
            'result' => NULL,
          ),
          6 => 
          array (
            'test' => 'Fiber',
            'test_method' => 'Weende Method',
            'result' => NULL,
          ),
          7 => 
          array (
            'test' => 'Carbohydrates',
            'test_method' => 'Phenol Sulfuric Acid Method',
            'result' => NULL,
          ),
          8 => 
          array (
            'test' => 'Ash',
            'test_method' => 'Oxidation at 550°C',
            'result' => NULL,
          ),
          9 => 
          array (
            'test' => 'Sodium',
            'test_method' => 'FLAME-AES',
            'result' => NULL,
          ),
          10 => 
          array (
            'test' => 'Brix',
            'test_method' => 'Refractometer',
            'result' => NULL,
          ),
        ),
      ),
    ),
    6 => 
    array (
      'name' => 'results.analyst_name',
      'label' => 'Analyst name (slot 1)',
      'type' => 'signature',
      'page' => 1,
      'x' => 32.079,
      'y' => 225.814,
      'w' => 45.88,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name',
    ),
    7 => 
    array (
      'name' => 'results.analyst_prc',
      'label' => 'Analyst PRC (slot 1)',
      'type' => 'text',
      'page' => 1,
      'x' => 58.788,
      'y' => 234.082,
      'w' => 28.319,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_prc',
    ),
    8 => 
    array (
      'name' => 'results.analyst_name_2',
      'label' => 'Analyst name (slot 2)',
      'type' => 'signature',
      'page' => 1,
      'x' => 32.079,
      'y' => 243.453,
      'w' => 38.714,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name_2',
    ),
    9 => 
    array (
      'name' => 'results.analyst_prc_2',
      'label' => 'Analyst PRC (slot 2)',
      'type' => 'text',
      'page' => 1,
      'x' => 58.788,
      'y' => 251.354,
      'w' => 28.319,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_prc_2',
    ),
    10 => 
    array (
      'name' => 'results.specimen',
      'label' => 'Specimen',
      'type' => 'text',
      'page' => 1,
      'x' => 72.947,
      'y' => 47.031,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.specimen',
    ),
  ),
);
