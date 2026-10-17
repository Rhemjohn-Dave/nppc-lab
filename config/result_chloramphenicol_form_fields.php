<?php

/**
 * Field blueprint for LSP-7.8-F016-CAP.
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
      'x' => 49.796,
      'y' => 39.5,
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
      'x' => 49.796,
      'y' => 43.5,
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
      'name' => 'results.ref_no',
      'label' => 'LSO No.',
      'type' => 'text',
      'page' => 1,
      'x' => 49.796,
      'y' => 47.5,
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
      'name' => 'results.specimen',
      'label' => 'Specimen',
      'type' => 'text',
      'page' => 1,
      'x' => 49.796,
      'y' => 52.184,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.specimen',
    ),
    4 => 
    array (
      'name' => 'results.sample_received_at',
      'label' => 'Date Submitted',
      'type' => 'date',
      'page' => 1,
      'x' => 146.88,
      'y' => 39.5,
      'w' => 45.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sample_received_at',
    ),
    5 => 
    array (
      'name' => 'results.report_date',
      'label' => 'Date Reported',
      'type' => 'date',
      'page' => 1,
      'x' => 146.88,
      'y' => 44.5,
      'w' => 45.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.report_date',
    ),
    6 => 
    array (
      'name' => 'chloramphenicol_f016_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 35.0,
      'y' => 85.0,
      'w' => 145.0,
      'h' => 40.0,
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
            'width_pct' => 30,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 11,
          ),
          1 => 
          array (
            'key' => 'sample_description',
            'label' => 'Sample Description',
            'width_pct' => 40,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 11,
          ),
          2 => 
          array (
            'key' => 'result',
            'label' => 'Results',
            'sublabel' => '(ppb)',
            'sublabel_align' => 'C',
            'width_pct' => 30,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 11,
          ),
        ),
        'row_height_mm' => 12,
        'header_row_height_mm' => 14,
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
            'control_no' => 'CAP-2026-001',
            'sample_description' => 'Prawn sample',
            'result' => NULL,
          ),
        ),
      ),
    ),
    7 => 
    array (
      'name' => 'results.analyst_name',
      'label' => 'Analyst name',
      'type' => 'signature',
      'page' => 1,
      'x' => 18.903,
      'y' => 204.698,
      'w' => 55.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name',
    ),
    8 => 
    array (
      'name' => 'results.analyst_prc',
      'label' => 'Analyst PRC',
      'type' => 'text',
      'page' => 1,
      'x' => 46.403,
      'y' => 212.481,
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
