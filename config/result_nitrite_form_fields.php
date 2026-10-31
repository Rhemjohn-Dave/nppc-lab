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
      'name' => 'no2_f016_max_level',
      'label' => 'Maximum Level',
      'type' => 'text',
      'page' => 1,
      'x' => 35.45,
      'y' => 170.0,
      'w' => 145.0,
      'h' => 5.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'no2_f016_max_level',
      'options' => 
      array (
        'cover' => true,
        'anchor' => 
        array (
          'below' => 'nitrite_f016_matrix',
          'offset_mm' => 2,
        ),
        'static_text' => 'Maximum Level = **167 mg/kg** (Food Cat. No. 8.2.3 – DOH-BFAD)',
      ),
    ),
    9 => 
    array (
      'name' => 'no2_f016_notes',
      'label' => 'NOTES',
      'type' => 'multiline',
      'page' => 1,
      'x' => 35.45,
      'y' => 178.0,
      'w' => 145.0,
      'h' => 14.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'no2_f016_notes',
      'options' => 
      array (
        'cover' => true,
        'list_style' => 'numbered',
        'list_indent_mm' => 6,
        'anchor' => 
        array (
          'below' => 'nitrite_f016_matrix',
          'offset_mm' => 10,
        ),
        'static_text' => 'NOTES :
1. Results of examination specifically related to samples as received.
2. Test results shall not be reproduced without the approval of the Laboratory Head.',
      ),
    ),
    10 => 
    array (
      'name' => 'no2_f016_label_analyzed_by',
      'label' => 'Analyzed by label',
      'type' => 'text',
      'page' => 1,
      'x' => 35.45,
      'y' => 196.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'no2_f016_label_analyzed_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'nitrite_f016_matrix',
          'offset_mm' => 28,
        ),
        'static_text' => 'Analyzed by:',
      ),
    ),
    11 => 
    array (
      'name' => 'no2_f016_label_approved_by',
      'label' => 'Approved by label',
      'type' => 'text',
      'page' => 1,
      'x' => 120.0,
      'y' => 196.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'no2_f016_label_approved_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'nitrite_f016_matrix',
          'offset_mm' => 28,
        ),
        'static_text' => 'Approved by:',
      ),
    ),
    12 => 
    array (
      'name' => 'results.analyst_name',
      'label' => 'Analyzed by name',
      'type' => 'text',
      'page' => 1,
      'x' => 35.45,
      'y' => 202.0,
      'w' => 80.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'nitrite_f016_matrix',
          'offset_mm' => 34,
        ),
        'static_text' => 'Ferresa Mae O. Caberoy, RCh',
        'bold' => true,
      ),
    ),
    13 => 
    array (
      'name' => 'no2_f016_approved_by_name',
      'label' => 'Approved by name',
      'type' => 'text',
      'page' => 1,
      'x' => 120.0,
      'y' => 202.0,
      'w' => 85.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'no2_f016_approved_by_name',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'nitrite_f016_matrix',
          'offset_mm' => 34,
        ),
        'static_text' => 'Wendy A. Tayson, RCh, MEE',
        'bold' => true,
      ),
    ),
    14 => 
    array (
      'name' => 'no2_f016_role_analyzed',
      'label' => 'Analyzed by role',
      'type' => 'text',
      'page' => 1,
      'x' => 35.45,
      'y' => 207.0,
      'w' => 80.0,
      'h' => 4.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'no2_f016_role_analyzed',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'nitrite_f016_matrix',
          'offset_mm' => 39,
        ),
        'static_text' => 'Laboratory Analyst',
      ),
    ),
    15 => 
    array (
      'name' => 'no2_f016_role_approved',
      'label' => 'Approved by role',
      'type' => 'text',
      'page' => 1,
      'x' => 120.0,
      'y' => 207.0,
      'w' => 85.0,
      'h' => 4.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'no2_f016_role_approved',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'nitrite_f016_matrix',
          'offset_mm' => 39,
        ),
        'static_text' => 'Laboratory Head',
      ),
    ),
    16 => 
    array (
      'name' => 'results.analyst_prc',
      'label' => 'Analyzed by PRC',
      'type' => 'text',
      'page' => 1,
      'x' => 35.45,
      'y' => 211.0,
      'w' => 80.0,
      'h' => 4.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_prc',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'nitrite_f016_matrix',
          'offset_mm' => 43,
        ),
        'static_text' => 'PRC License No. 16125',
      ),
    ),
    17 => 
    array (
      'name' => 'no2_f016_lic_approved',
      'label' => 'Approved by license',
      'type' => 'text',
      'page' => 1,
      'x' => 120.0,
      'y' => 211.0,
      'w' => 85.0,
      'h' => 4.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'no2_f016_lic_approved',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'nitrite_f016_matrix',
          'offset_mm' => 43,
        ),
        'static_text' => 'PRC License No. 15131',
      ),
    ),
    18 => 
    array (
      'name' => 'no2_f016_form_meta',
      'label' => 'Form control footer',
      'type' => 'multiline',
      'page' => 1,
      'x' => 35.45,
      'y' => 220.0,
      'w' => 100.0,
      'h' => 16.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'no2_f016_form_meta',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'nitrite_f016_matrix',
          'offset_mm' => 52,
        ),
        'static_text' => 'NPPC ADL
LSP 7.8 F016
Rev 03 / Issue 04
Effectivity Date: 07/15/2026',
      ),
    ),
  ),
);
