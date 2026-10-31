<?php

/**
 * Field blueprint for LSP-7.8-F016-PROX.
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
    3 => 
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
    4 => 
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
    5 => 
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
    6 => 
    array (
      'name' => 'proximate_f016_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 39.489,
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
        'header_row_height_mm' => 11.5,
        'header_row' => true,
        'border' => true,
        'preview_rows' => 11,
        'test_name_bold' => true,
        'header_bold' => true,
        'method_font_size' => 9,
        'stretch_body' => false,
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
    7 => 
    array (
      'name' => 'prox_f016_notes',
      'label' => 'NOTES',
      'type' => 'multiline',
      'page' => 1,
      'x' => 26.162,
      'y' => 205.0,
      'w' => 159.233,
      'h' => 19.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'prox_f016_notes',
      'options' => 
      array (
        'cover' => true,
        'list_style' => 'numbered',
        'list_indent_mm' => 6,
        'anchor' => 
        array (
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 2,
        ),
        'static_text' => 'NOTES :
1. Results of examination specifically related to samples as received.
2. Test results shall not be reproduced without the approval of the Laboratory Head.',
      ),
    ),
    8 => 
    array (
      'name' => 'prox_f016_label_analyzed_by',
      'label' => 'Analyzed by label',
      'type' => 'text',
      'page' => 1,
      'x' => 29.0,
      'y' => 224.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'prox_f016_label_analyzed_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 22,
        ),
        'static_text' => 'Analyzed by:',
      ),
    ),
    9 => 
    array (
      'name' => 'prox_f016_label_approved_by',
      'label' => 'Approved by label',
      'type' => 'text',
      'page' => 1,
      'x' => 124.0,
      'y' => 224.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'prox_f016_label_approved_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 22,
        ),
        'static_text' => 'Approved by:',
      ),
    ),
    10 => 
    array (
      'name' => 'results.analyst_name',
      'label' => 'Analyzed by name (slot 1)',
      'type' => 'text',
      'page' => 1,
      'x' => 29.0,
      'y' => 230.0,
      'w' => 95.0,
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
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 28,
        ),
        'static_text' => 'Ferresa Mae O. Caberoy, RCh',
        'bold' => true,
      ),
    ),
    11 => 
    array (
      'name' => 'prox_f016_approved_by_name',
      'label' => 'Approved by name',
      'type' => 'text',
      'page' => 1,
      'x' => 124.0,
      'y' => 230.0,
      'w' => 85.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'prox_f016_approved_by_name',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 28,
        ),
        'static_text' => 'Wendy A. Tayson, RCh, MEE',
        'bold' => true,
      ),
    ),
    12 => 
    array (
      'name' => 'prox_f016_role_analyzed_1',
      'label' => 'Analyzed by role (slot 1)',
      'type' => 'text',
      'page' => 1,
      'x' => 29.0,
      'y' => 235.0,
      'w' => 95.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'prox_f016_role_analyzed_1',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 32,
        ),
        'static_text' => 'Laboratory Analyst',
      ),
    ),
    13 => 
    array (
      'name' => 'prox_f016_role_approved',
      'label' => 'Approved by role',
      'type' => 'text',
      'page' => 1,
      'x' => 124.0,
      'y' => 235.0,
      'w' => 85.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'prox_f016_role_approved',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 32,
        ),
        'static_text' => 'Laboratory Head',
      ),
    ),
    14 => 
    array (
      'name' => 'results.analyst_prc',
      'label' => 'Analyzed by PRC (slot 1)',
      'type' => 'text',
      'page' => 1,
      'x' => 29.0,
      'y' => 239.0,
      'w' => 95.0,
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
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 35,
        ),
        'static_text' => 'PRC License No. 16125',
      ),
    ),
    15 => 
    array (
      'name' => 'prox_f016_lic_approved',
      'label' => 'Approved by license',
      'type' => 'text',
      'page' => 1,
      'x' => 124.0,
      'y' => 239.0,
      'w' => 85.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'prox_f016_lic_approved',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 35,
        ),
        'static_text' => 'PRC License No. 15131',
      ),
    ),
    16 => 
    array (
      'name' => 'results.analyst_name_2',
      'label' => 'Analyzed by name (slot 2)',
      'type' => 'text',
      'page' => 1,
      'x' => 29.0,
      'y' => 248.0,
      'w' => 95.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name_2',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 46,
        ),
        'static_text' => 'Ma. Antoniela C. Lanaca',
        'bold' => true,
      ),
    ),
    17 => 
    array (
      'name' => 'prox_f016_role_analyzed_2',
      'label' => 'Analyzed by role (slot 2)',
      'type' => 'text',
      'page' => 1,
      'x' => 29.0,
      'y' => 253.0,
      'w' => 95.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'prox_f016_role_analyzed_2',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 50,
        ),
        'static_text' => 'Laboratory Analyst',
      ),
    ),
    18 => 
    array (
      'name' => 'results.analyst_prc_2',
      'label' => 'Analyzed by PRC (slot 2)',
      'type' => 'text',
      'page' => 1,
      'x' => 29.0,
      'y' => 257.0,
      'w' => 95.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_prc_2',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 53,
        ),
        'static_text' => 'PRC License No. 00761',
      ),
    ),
    19 => 
    array (
      'name' => 'prox_f016_form_meta',
      'label' => 'Form control footer',
      'type' => 'multiline',
      'page' => 1,
      'x' => 29.0,
      'y' => 265.0,
      'w' => 100.0,
      'h' => 16.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'prox_f016_form_meta',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'proximate_f016_matrix',
          'offset_mm' => 64,
        ),
        'static_text' => 'NPPC ADL
LSP 7.8 F016
Rev 05 / Issue 06
Effectivity Date: 07/15/2026',
      ),
    ),
  ),
);
