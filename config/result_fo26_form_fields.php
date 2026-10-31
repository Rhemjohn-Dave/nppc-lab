<?php

/**
 * Field blueprint for LSP-7.8-FO26.
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
      'x' => 35.675,
      'y' => 40.7,
      'w' => 86.0,
      'h' => 4.2,
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
      'x' => 35.675,
      'y' => 45.2,
      'w' => 86.0,
      'h' => 4.2,
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
      'x' => 36.175,
      'y' => 50.2,
      'w' => 70.0,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'calibri',
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
      'x' => 55.217,
      'y' => 54.9,
      'w' => 64.0,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'calibri',
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
      'x' => 47.717,
      'y' => 59.6,
      'w' => 47.58,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'calibri',
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
      'x' => 149.5,
      'y' => 40.7,
      'w' => 45.0,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'calibri',
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
      'x' => 149.5,
      'y' => 45.7,
      'w' => 45.0,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'calibri',
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
      'x' => 149.5,
      'y' => 50.7,
      'w' => 45.0,
      'h' => 4.2,
      'font_size' => 11.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.report_date',
    ),
    8 => 
    array (
      'name' => 'food_micro_fo26_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 20.0,
      'y' => 75.072,
      'w' => 174.5,
      'h' => 105.944,
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
            'label' => 'TEST',
            'width_pct' => 54.5,
            'align' => 'L',
            'header_align' => 'C',
            'header_font_size' => 11,
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
            'width_pct' => 45.5,
            'align' => 'C',
            'header_align' => 'C',
            'header_font_size' => 9,
            'sublabels' => NULL,
            'font_size' => 9,
          ),
        ),
        'row_height_mm' => 3.5,
        'header_row_height_mm' => 8,
        'header_row' => true,
        'border' => true,
        'preview_rows' => 10,
        'test_name_bold' => true,
        'header_bold' => true,
        'method_font_size' => 9,
        'header_font_size' => 8,
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
          'below' => 'food_micro_fo26_matrix',
          'offset_mm' => 2,
        ),
        'static_text' => '**Test Methods and References:**
Aerobic Plate Count - Pour Plate Method, Plate Count Agar (PCA); BAM Online Chapter 3
Aerobic Plate Count - AOAC International Official Method of Analysis – Method 990.12
3M PetrifilmTM Rapid Aerobic Count Plates, AOAC Certificate Number 121403
Total Coliform - Multiple Tube Fermentation Technique; BAM Online
Fecal Coliform - Multiple Tube Fermentation Technique; BAM Online
E. coli - Compact Dry Media (AOAC Performance Tested, Cert. #110402)
S. aureus - Compact Dry Media (AOAC Performance Tested, Cert. #081001)
Salmonella - 3M Petrifilm Salmonella Express (AOAC Certified)
Listeria - Reveal 2.0 Test for Listeria (AOAC-RI License No. 041101)
Yeasts - Pour Plate Method; BAM
Molds - Pour Plate Method; BAM
V. parahaemolyticus - Compact Dry Media',
      ),
    ),
    10 => 
    array (
      'name' => 'fo26_notes',
      'label' => 'NOTE',
      'type' => 'multiline',
      'page' => 1,
      'x' => 20.0,
      'y' => 224.0,
      'w' => 174.5,
      'h' => 14.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo26_notes',
      'options' => 
      array (
        'cover' => true,
        'list_style' => 'numbered',
        'list_indent_mm' => 6,
        'anchor' => 
        array (
          'below' => 'food_micro_fo26_matrix',
          'offset_mm' => 40,
        ),
        'static_text' => 'NOTE :
1. Result of examination specifically related to samples as received.
2. Test results shall not be reproduced without the approval of the Laboratory Head.',
      ),
    ),
    11 => 
    array (
      'name' => 'fo26_label_analyzed_by',
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
      'data_source_key' => 'fo26_label_analyzed_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_fo26_matrix',
          'offset_mm' => 56,
        ),
        'static_text' => 'Analyzed by:',
      ),
    ),
    12 => 
    array (
      'name' => 'fo26_label_approved_by',
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
      'data_source_key' => 'fo26_label_approved_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_fo26_matrix',
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
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_fo26_matrix',
          'offset_mm' => 62,
        ),
        'static_text' => 'Riz Mae Fernandez, RMT',
        'bold' => true,
      ),
    ),
    14 => 
    array (
      'name' => 'fo26_approved_by_name',
      'label' => 'Approved by name',
      'type' => 'text',
      'page' => 1,
      'x' => 120.0,
      'y' => 246.0,
      'w' => 85.0,
      'h' => 5.0,
      'font_size' => 11.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo26_approved_by_name',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_fo26_matrix',
          'offset_mm' => 62,
        ),
        'static_text' => 'Wendy A. Tayson, RCh, MEnE',
        'bold' => true,
      ),
    ),
    15 => 
    array (
      'name' => 'fo26_role_analyzed',
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
      'data_source_key' => 'fo26_role_analyzed',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_fo26_matrix',
          'offset_mm' => 67,
        ),
        'static_text' => 'Laboratory Analyst',
      ),
    ),
    16 => 
    array (
      'name' => 'fo26_role_approved',
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
      'data_source_key' => 'fo26_role_approved',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_fo26_matrix',
          'offset_mm' => 67,
        ),
        'static_text' => 'Laboratory Head',
      ),
    ),
    17 => 
    array (
      'name' => 'fo26_form_meta',
      'label' => 'Form control footer',
      'type' => 'multiline',
      'page' => 1,
      'x' => 20.0,
      'y' => 258.0,
      'w' => 100.0,
      'h' => 14.0,
      'font_size' => 7.5,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo26_form_meta',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'food_micro_fo26_matrix',
          'offset_mm' => 74,
        ),
        'static_text' => 'NPPC-ADL LSP 7.8 FO26
Rev 03/Issue 04
Effectivity Date: 07/15/2026',
      ),
    ),
  ),
);
