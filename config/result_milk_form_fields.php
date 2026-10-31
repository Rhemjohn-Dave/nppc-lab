<?php

/**
 * Field blueprint for LSP-7.8-F016-MILK.
 * Captured from Form Designer ACTIVE revision 02
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
      'y' => 34.832,
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
      'y' => 38.832,
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
      'name' => 'results.sampling_datetime',
      'label' => 'Date & Time of Sampling',
      'type' => 'date',
      'page' => 1,
      'x' => 72.947,
      'y' => 47.031,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sampling_datetime',
    ),
    3 => 
    array (
      'name' => 'results.report_date',
      'label' => 'Report',
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
      'data_source_key' => 'results.report_date',
    ),
    4 => 
    array (
      'name' => 'results.ref_no',
      'label' => 'Ref. No.',
      'type' => 'text',
      'page' => 1,
      'x' => 72.947,
      'y' => 55.031,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.ref_no',
    ),
    5 => 
    array (
      'name' => 'results.specimen',
      'label' => 'Specimen',
      'type' => 'text',
      'page' => 1,
      'x' => 72.947,
      'y' => 43.031,
      'w' => 50.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.specimen',
    ),
    6 => 
    array (
      'name' => 'milk_f016_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 39.285,
      'y' => 73.02,
      'w' => 142.162,
      'h' => 92.0,
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
        'preview_rows' => 5,
        'test_name_bold' => true,
        'header_bold' => true,
        'method_font_size' => 9,
        'stretch_body' => false,
        'preview_data' => 
        array (
          0 => 
          array (
            'test' => '% Total Solids (Milk)',
            'test_method' => NULL,
            'result' => NULL,
          ),
          1 => 
          array (
            'test' => '% Total Soluble Solids (Milk)',
            'test_method' => NULL,
            'result' => NULL,
          ),
          2 => 
          array (
            'test' => 'pH (Milk)',
            'test_method' => NULL,
            'result' => NULL,
          ),
          3 => 
          array (
            'test' => '% Fat (Milk)',
            'test_method' => NULL,
            'result' => NULL,
          ),
          4 => 
          array (
            'test' => '% Solid Not Fat, SNF',
            'test_method' => NULL,
            'result' => NULL,
          ),
        ),
      ),
    ),
    7 => 
    array (
      'name' => 'milk_f016_notes',
      'label' => 'NOTES',
      'type' => 'multiline',
      'page' => 1,
      'x' => 39.285,
      'y' => 168.0,
      'w' => 142.162,
      'h' => 14.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'milk_f016_notes',
      'options' => 
      array (
        'cover' => true,
        'list_style' => 'numbered',
        'list_indent_mm' => 6,
        'anchor' => 
        array (
          'below' => 'milk_f016_matrix',
          'offset_mm' => 2,
        ),
        'static_text' => 'NOTES :
1. Results of examination specifically related to samples as received.
2. Test results shall not be reproduced without the approval of the Laboratory Head.',
      ),
    ),
    8 => 
    array (
      'name' => 'milk_f016_label_analyzed_by',
      'label' => 'Analyzed by label',
      'type' => 'text',
      'page' => 1,
      'x' => 39.285,
      'y' => 186.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'milk_f016_label_analyzed_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'milk_f016_matrix',
          'offset_mm' => 20,
        ),
        'static_text' => 'Analyzed by:',
      ),
    ),
    9 => 
    array (
      'name' => 'milk_f016_label_approved_by',
      'label' => 'Approved by label',
      'type' => 'text',
      'page' => 1,
      'x' => 120.0,
      'y' => 186.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'milk_f016_label_approved_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'milk_f016_matrix',
          'offset_mm' => 20,
        ),
        'static_text' => 'Approved by:',
      ),
    ),
    10 => 
    array (
      'name' => 'results.analyst_name',
      'label' => 'Analyzed by name',
      'type' => 'text',
      'page' => 1,
      'x' => 39.285,
      'y' => 192.0,
      'w' => 80.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'milk_f016_matrix',
          'offset_mm' => 26,
        ),
        'static_text' => 'Ferresa Mae O. Caberoy, RCh',
      ),
    ),
    11 => 
    array (
      'name' => 'milk_f016_approved_by_name',
      'label' => 'Approved by name',
      'type' => 'text',
      'page' => 1,
      'x' => 120.0,
      'y' => 192.0,
      'w' => 85.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'milk_f016_approved_by_name',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'milk_f016_matrix',
          'offset_mm' => 26,
        ),
        'static_text' => 'Wendy A. Tayson, RCh, MEE',
      ),
    ),
    12 => 
    array (
      'name' => 'milk_f016_role_analyzed',
      'label' => 'Analyzed by role',
      'type' => 'text',
      'page' => 1,
      'x' => 39.285,
      'y' => 197.0,
      'w' => 80.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'milk_f016_role_analyzed',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'milk_f016_matrix',
          'offset_mm' => 31,
        ),
        'static_text' => 'Laboratory Analyst',
      ),
    ),
    13 => 
    array (
      'name' => 'milk_f016_role_approved',
      'label' => 'Approved by role',
      'type' => 'text',
      'page' => 1,
      'x' => 120.0,
      'y' => 197.0,
      'w' => 85.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'milk_f016_role_approved',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'milk_f016_matrix',
          'offset_mm' => 31,
        ),
        'static_text' => 'Laboratory Head',
      ),
    ),
    14 => 
    array (
      'name' => 'results.analyst_prc',
      'label' => 'Analyzed by PRC',
      'type' => 'text',
      'page' => 1,
      'x' => 39.285,
      'y' => 201.0,
      'w' => 80.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_prc',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'milk_f016_matrix',
          'offset_mm' => 35,
        ),
        'static_text' => 'PRC License No. 16125',
      ),
    ),
    15 => 
    array (
      'name' => 'milk_f016_lic_approved',
      'label' => 'Approved by license',
      'type' => 'text',
      'page' => 1,
      'x' => 120.0,
      'y' => 201.0,
      'w' => 85.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'milk_f016_lic_approved',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'milk_f016_matrix',
          'offset_mm' => 35,
        ),
        'static_text' => 'PRC License No. 15131',
      ),
    ),
    16 => 
    array (
      'name' => 'milk_f016_form_meta',
      'label' => 'Form control footer',
      'type' => 'multiline',
      'page' => 1,
      'x' => 20.0,
      'y' => 210.0,
      'w' => 100.0,
      'h' => 16.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'milk_f016_form_meta',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'milk_f016_matrix',
          'offset_mm' => 50,
        ),
        'static_text' => 'NPPC ADL
LSP 7.8 F016
Rev 05 / Issue 06
Effectivity Date: 07/15/2026',
      ),
    ),
  ),
);
