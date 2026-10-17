<?php

/**
 * Field blueprint for LSP-7.8-F016-NO2.
 * Captured from Form Designer ACTIVE revision 03
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
      'x' => 72.95,
      'y' => 37.641,
      'w' => 70.0,
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
      'x' => 72.95,
      'y' => 41.641,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.address',
    ),
    2 => 
    array (
      'name' => 'results.specimen',
      'label' => 'Specimen',
      'type' => 'text',
      'page' => 1,
      'x' => 72.95,
      'y' => 45.641,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.specimen',
    ),
    3 => 
    array (
      'name' => 'results.sample_received_at',
      'label' => 'Date Submitted',
      'type' => 'date',
      'page' => 1,
      'x' => 72.95,
      'y' => 50.141,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sample_received_at',
    ),
    4 => 
    array (
      'name' => 'results.report_date',
      'label' => 'Date Reported',
      'type' => 'date',
      'page' => 1,
      'x' => 72.95,
      'y' => 54.141,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.report_date',
    ),
    5 => 
    array (
      'name' => 'results.test_requested',
      'label' => 'Test Requested',
      'type' => 'text',
      'page' => 1,
      'x' => 72.95,
      'y' => 58.141,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.test_requested',
    ),
    6 => 
    array (
      'name' => 'results.ref_no',
      'label' => 'Reference No',
      'type' => 'text',
      'page' => 1,
      'x' => 72.95,
      'y' => 62.192,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.ref_no',
    ),
    7 => 
    array (
      'name' => 'nitrite_f016_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 35.45,
      'y' => 89.633,
      'w' => 145.0,
      'h' => 76.693,
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
            'key' => 'control_no',
            'label' => 'Control Number',
            'width_pct' => 28,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 11,
          ),
          1 => 
          array (
            'key' => 'sample_description',
            'label' => 'Sample Description',
            'width_pct' => 36,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 11,
          ),
          2 => 
          array (
            'key' => 'result',
            'label' => 'Nitrite (mg/kg)',
            'sublabel' => 'Spectrophotometric Method',
            'sublabel_align' => 'C',
            'width_pct' => 36,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 11,
          ),
        ),
        'row_height_mm' => 12,
        'header_row_height_mm' => 15,
        'header_row' => true,
        'border' => true,
        'preview_rows' => 1,
        'test_name_bold' => true,
        'header_bold' => true,
        'method_font_size' => 9,
        'stretch_body' => false,
      ),
    ),
    8 => 
    array (
      'name' => 'results.analyst_name',
      'label' => 'Analyst name',
      'type' => 'signature',
      'page' => 1,
      'x' => 32.102,
      'y' => 197.032,
      'w' => 55.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name',
    ),
    9 => 
    array (
      'name' => 'results.analyst_prc',
      'label' => 'Analyst PRC',
      'type' => 'text',
      'page' => 1,
      'x' => 60.102,
      'y' => 205.602,
      'w' => 35.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_prc',
    ),
  ),
);
