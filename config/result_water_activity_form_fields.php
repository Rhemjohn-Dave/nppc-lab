<?php

/**
 * Field blueprint for LSP-7.8-F016-WA.
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
      'x' => 72.947,
      'y' => 36.832,
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
      'y' => 41.332,
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
      'name' => 'results.specimen',
      'label' => 'Specimen',
      'type' => 'text',
      'page' => 1,
      'x' => 72.947,
      'y' => 45.031,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.specimen',
    ),
    3 => 
    array (
      'name' => 'results.sampling_datetime',
      'label' => 'Date & Time of Sampling',
      'type' => 'date',
      'page' => 1,
      'x' => 72.947,
      'y' => 49.531,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sampling_datetime',
    ),
    4 => 
    array (
      'name' => 'results.report_date',
      'label' => 'Report',
      'type' => 'date',
      'page' => 1,
      'x' => 72.947,
      'y' => 53.531,
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
      'name' => 'results.ref_no',
      'label' => 'Ref. No.',
      'type' => 'text',
      'page' => 1,
      'x' => 72.947,
      'y' => 61.531,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.ref_no',
    ),
    6 => 
    array (
      'name' => 'water_activity_f016_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 36.685,
      'y' => 82.759,
      'w' => 142.529,
      'h' => 32.015,
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
            'label' => 'Water Activity, Aw',
            'sublabel' => 'Water Activity Meter',
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
        'preview_data' => 
        array (
          0 => 
          array (
            'control_no' => 'WA-2026-001',
            'sample_description' => 'Dried fruit',
            'result' => NULL,
          ),
        ),
      ),
    ),
    7 => 
    array (
      'name' => 'results.analyst_name',
      'label' => 'Analyst name (slot 1)',
      'type' => 'signature',
      'page' => 1,
      'x' => 31.946,
      'y' => 166.65,
      'w' => 45.88,
      'h' => 5.0,
      'font_size' => 12.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name',
    ),
    8 => 
    array (
      'name' => 'results.analyst_prc',
      'label' => 'Analyst PRC (slot 1)',
      'type' => 'text',
      'page' => 1,
      'x' => 59.155,
      'y' => 175.418,
      'w' => 28.319,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_prc',
    ),
    9 => 
    array (
      'name' => 'results.test_requested',
      'label' => 'Test requested',
      'type' => 'text',
      'page' => 1,
      'x' => 72.947,
      'y' => 57.031,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.test_requested',
    ),
  ),
);
