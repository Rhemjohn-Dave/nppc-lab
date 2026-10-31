<?php

/**
 * Field blueprint for LSP-7.8-FO27.
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
      'label' => 'Client',
      'type' => 'text',
      'page' => 1,
      'x' => 35.46,
      'y' => 40.2,
      'w' => 90.0,
      'h' => 4.2,
      'font_size' => 11.0,
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
      'x' => 35.46,
      'y' => 45.2,
      'w' => 90.0,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'times',
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
      'x' => 35.46,
      'y' => 50.2,
      'w' => 70.0,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.ref_no',
    ),
    3 => 
    array (
      'name' => 'results.sample_description',
      'label' => 'Sample Description',
      'type' => 'text',
      'page' => 1,
      'x' => 54.46,
      'y' => 54.7,
      'w' => 140.0,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sample_description',
    ),
    4 => 
    array (
      'name' => 'results.test_requested',
      'label' => 'Test Requested',
      'type' => 'text',
      'page' => 1,
      'x' => 46.96,
      'y' => 59.7,
      'w' => 140.0,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.test_requested',
    ),
    5 => 
    array (
      'name' => 'results.sample_received_at',
      'label' => 'Date Submitted',
      'type' => 'date',
      'page' => 1,
      'x' => 149.46,
      'y' => 40.2,
      'w' => 45.0,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sample_received_at',
    ),
    6 => 
    array (
      'name' => 'results.analysis_datetime',
      'label' => 'Date Analyzed',
      'type' => 'date',
      'page' => 1,
      'x' => 149.46,
      'y' => 45.2,
      'w' => 45.0,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analysis_datetime',
    ),
    7 => 
    array (
      'name' => 'results.report_date',
      'label' => 'Date Reported',
      'type' => 'date',
      'page' => 1,
      'x' => 149.46,
      'y' => 50.2,
      'w' => 45.0,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.report_date',
    ),
    8 => 
    array (
      'name' => 'food_micro_sugar_fo27_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 21.3,
      'y' => 75.7,
      'w' => 177.4,
      'h' => 128.0,
      'font_size' => 12.0,
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
            'label' => 'TEST',
            'width_pct' => 55,
            'align' => 'L',
            'header_align' => 'C',
            'header_font_size' => 11,
          ),
          1 => 
          array (
            'key' => 'result',
            'label' => 'Control Number',
            'label_data_source' => 'results.control_no',
            'sublabel' => 'Sample Description',
            'sublabel_data_source' => 'results.sample_description',
            'sublabel_align' => 'C',
            'width_pct' => 45,
            'align' => 'C',
            'header_align' => 'C',
            'sublabels' => NULL,
            'header_font_size' => 11,
          ),
        ),
        'row_height_mm' => 8.5,
        'header_row_height_mm' => 8,
        'header_row' => true,
        'border' => true,
        'preview_rows' => 9,
        'test_name_bold' => false,
        'header_bold' => true,
        'method_font_size' => 9,
        'stretch_body' => false,
      ),
    ),
    9 => 
    array (
      'name' => 'results.test_methods_references',
      'label' => 'Test Methods and References',
      'type' => 'multiline',
      'page' => 1,
      'x' => 20.0,
      'y' => 185.0,
      'w' => 174.5,
      'h' => 36.0,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => null,
      'options' => 
      array (
        'cover' => true,
        'prefer_static' => true,
        'anchor' => 
        array (
          'below' => 'food_micro_sugar_fo27_matrix',
          'offset_mm' => 2,
        ),
        'static_text' => '**Test Methods and References:**
Mesophilic Bacteria Count – ICUMSA (Method GS2/3-43, 1994)
Thermophilic Bacteria Count – ICUMSA (Method GS2/3-49, 1994)
Total & Fecal Coliforms –Multiple Tube Fermentation Technique; BAM
Total Coliforms – Serial Dilution Method, Compact Dry Media (AOAC Performance Tested, Cert. #110402)
E. coli –  Compact Dry Media (AOAC Performance Tested, Cert. #110402)
Salmonella –  3M Petrifilm Salmonella Express (AOAC Certified)
Yeast & Molds – ICUMSA (Method GS2/3-47, 1994), ICUMSA and BAM',
      ),
    ),
    10 => 
    array (
      'name' => 'fo27_notes',
      'label' => 'NOTE',
      'type' => 'multiline',
      'page' => 1,
      'x' => 20.0,
      'y' => 224.0,
      'w' => 174.5,
      'h' => 14.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo27_notes',
      'options' => 
      array (
        'cover' => true,
        'list_style' => 'numbered',
        'list_indent_mm' => 6,
        'anchor' => 
        array (
          'below' => 'food_micro_sugar_fo27_matrix',
          'offset_mm' => 40,
        ),
        'static_text' => 'NOTE :
1. Result of examination specifically related to samples as received.
2. Test results shall not be reproduced without the approval of the Laboratory Head.',
      ),
    ),
    11 => 
    array (
      'name' => 'fo27_label_analyzed_by',
      'label' => 'Analyzed by label',
      'type' => 'text',
      'page' => 1,
      'x' => 20.0,
      'y' => 240.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo27_label_analyzed_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_sugar_fo27_matrix',
          'offset_mm' => 56,
        ),
        'static_text' => 'Analyzed by:',
      ),
    ),
    12 => 
    array (
      'name' => 'fo27_label_approved_by',
      'label' => 'Approved by label',
      'type' => 'text',
      'page' => 1,
      'x' => 120.0,
      'y' => 240.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo27_label_approved_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_sugar_fo27_matrix',
          'offset_mm' => 56,
        ),
        'static_text' => 'Approved by:',
      ),
    ),
    13 => 
    array (
      'name' => 'results.analyst_name',
      'label' => 'Analyzed by name',
      'type' => 'text',
      'page' => 1,
      'x' => 20.0,
      'y' => 246.0,
      'w' => 90.0,
      'h' => 5.0,
      'font_size' => 12.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_sugar_fo27_matrix',
          'offset_mm' => 62,
        ),
        'static_text' => 'Riz Mae Fernandez',
        'bold' => true,
      ),
    ),
    14 => 
    array (
      'name' => 'fo27_approved_by_name',
      'label' => 'Approved by name',
      'type' => 'text',
      'page' => 1,
      'x' => 120.0,
      'y' => 246.0,
      'w' => 85.0,
      'h' => 5.0,
      'font_size' => 12.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo27_approved_by_name',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_sugar_fo27_matrix',
          'offset_mm' => 62,
        ),
        'static_text' => 'Wendy A. Tayson, RCh, MEnE',
        'bold' => true,
      ),
    ),
    15 => 
    array (
      'name' => 'fo27_role_analyzed',
      'label' => 'Analyzed by role',
      'type' => 'text',
      'page' => 1,
      'x' => 20.0,
      'y' => 251.0,
      'w' => 90.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo27_role_analyzed',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_sugar_fo27_matrix',
          'offset_mm' => 67,
        ),
        'static_text' => 'Laboratory Analyst',
      ),
    ),
    16 => 
    array (
      'name' => 'fo27_role_approved',
      'label' => 'Approved by role',
      'type' => 'text',
      'page' => 1,
      'x' => 120.0,
      'y' => 251.0,
      'w' => 85.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo27_role_approved',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_sugar_fo27_matrix',
          'offset_mm' => 67,
        ),
        'static_text' => 'Laboratory Head',
      ),
    ),
    17 => 
    array (
      'name' => 'fo27_form_meta',
      'label' => 'Form control footer',
      'type' => 'multiline',
      'page' => 1,
      'x' => 20.0,
      'y' => 258.0,
      'w' => 100.0,
      'h' => 14.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo27_form_meta',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_sugar_fo27_matrix',
          'offset_mm' => 74,
        ),
        'static_text' => 'NPPC-ADL LSP 7.8 FO27
Rev.03/Issue04
Effectivity Date: 07/15/2026',
      ),
    ),
  ),
);
