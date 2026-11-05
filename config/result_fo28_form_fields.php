<?php

/**
 * Field blueprint for LSP-7.8-FO28.
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
      'x' => 91.943,
      'y' => 32.026,
      'w' => 113.057,
      'h' => 5.0,
      'font_size' => 10.0,
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
      'x' => 91.943,
      'y' => 35.526,
      'w' => 113.057,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.address',
    ),
    2 => 
    array (
      'name' => 'results.sample_description',
      'label' => 'Sample description',
      'type' => 'text',
      'page' => 1,
      'x' => 91.943,
      'y' => 39.526,
      'w' => 113.057,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sample_description',
    ),
    3 => 
    array (
      'name' => 'results.sample_received_at',
      'label' => 'Date of sample received',
      'type' => 'date',
      'page' => 1,
      'x' => 91.943,
      'y' => 43.526,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sample_received_at',
    ),
    4 => 
    array (
      'name' => 'results.sampling_datetime',
      'label' => 'Date of sampling',
      'type' => 'date',
      'page' => 1,
      'x' => 91.943,
      'y' => 47.026,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sampling_datetime',
    ),
    5 => 
    array (
      'name' => 'results.report_date',
      'label' => 'Date reported',
      'type' => 'date',
      'page' => 1,
      'x' => 91.943,
      'y' => 51.026,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.report_date',
    ),
    6 => 
    array (
      'name' => 'results.ref_no',
      'label' => 'Reference no.',
      'type' => 'text',
      'page' => 1,
      'x' => 91.943,
      'y' => 54.526,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.ref_no',
    ),
    7 => 
    array (
      'name' => 'results.control_no',
      'label' => 'Laboratory control no.',
      'type' => 'text',
      'page' => 1,
      'x' => 91.943,
      'y' => 58.526,
      'w' => 70.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.control_no',
    ),
    8 => 
    array (
      'name' => 'metals_fo28_matrix',
      'label' => 'Metals matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 43.468,
      'y' => 68.383,
      'w' => 143.065,
      'h' => 123.0,
      'font_size' => 9.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'table_config' => 
      array (
        'columns' => 
        array (
          0 => 
          array (
            'key' => 'test',
            'label' => 'Test',
            'width_pct' => 34,
            'align' => 'C',
            'header_align' => 'C',
            'valign' => 'M',
          ),
          1 => 
          array (
            'key' => 'method',
            'label' => 'Method',
            'width_pct' => 46,
            'align' => 'C',
            'header_align' => 'C',
            'valign' => 'M',
          ),
          2 => 
          array (
            'key' => 'result',
            'label' => 'Result',
            'width_pct' => 20,
            'align' => 'C',
            'header_align' => 'C',
          ),
        ),
        'row_height_mm' => 6,
        'header_row_height_mm' => 11,
        'header_row' => true,
        'border' => true,
        'preview_rows' => 10,
        'test_name_bold' => true,
        'header_bold' => true,
        'method_font_size' => 10,
        'max_rows_per_page' => 10,
        'stretch_body' => false,
        'preview_data' => 
        array (
          0 => 
          array (
            'test' => 'Arsenic (ppm)',
            'method' => 'HVG-Flame – AAS Method',
            'result' => '0.0008',
          ),
          1 => 
          array (
            'test' => 'Lead (ppm)',
            'method' => 'Flame – AAS Method',
            'result' => '<0.003',
          ),
          2 => 
          array (
            'test' => 'Cadmium (ppm)',
            'method' => 'Flame – AAS Method',
            'result' => '<0.002',
          ),
          3 => 
          array (
            'test' => 'Mercury (ppm)',
            'method' => 'Cold Vapor HVG AAS Method',
            'result' => '<2.0 x 10-4',
          ),
          4 => 
          array (
            'test' => 'Copper (ppm)',
            'method' => 'Flame – AAS Method',
            'result' => '<0.01',
          ),
          5 => 
          array (
            'test' => 'Magnesium (ppm)',
            'method' => 'Flame – AAS Method',
            'result' => '15.033',
          ),
          6 => 
          array (
            'test' => 'Manganese (ppm)',
            'method' => 'Flame – AAS Method',
            'result' => '0.223',
          ),
          7 => 
          array (
            'test' => 'Chromium (ppm)',
            'method' => 'Flame – AAS Method',
            'result' => '<0.01',
          ),
          8 => 
          array (
            'test' => 'Nickel (ppm)',
            'method' => 'Flame – AAS Method',
            'result' => '<0.02',
          ),
          9 => 
          array (
            'test' => 'Iron (ppm)',
            'method' => 'Flame – AAS Method',
            'result' => '0.05',
          ),
        ),
        'row_extra_mm' => 5,
      ),
    ),
    9 => 
    array (
      'name' => 'results.metals_mdl',
      'label' => 'Method detection limit',
      'type' => 'multiline',
      'page' => 1,
      'x' => 15.0,
      'y' => 190.0,
      'w' => 185.0,
      'h' => 12.0,
      'font_size' => 8.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.metals_mdl',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 2,
        ),
        'static_text' => 'Method Detection Limit: Arsenic =6.0 x 10-5 ppm; Lead = 0.003 ppm; Cadmium = 0.002 ppm: Mercury = 2.0 x 10-4 ppm 
                                            Nickel = 0.02 ppm; Chromium = 0.01 ppm; Manganese = 0.01 ppm; Copper = 0.01 ppm                        
                                            Magnesium = 0.02 ppm; Iron = 0.02 ppm',
      ),
    ),
    10 => 
    array (
      'name' => 'fo28_reference',
      'label' => 'Reference',
      'type' => 'text',
      'page' => 1,
      'x' => 15.0,
      'y' => 204.0,
      'w' => 185.0,
      'h' => 5.0,
      'font_size' => 8.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'options' => 
      array (
        'prefer_static' => true,
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 12,
        ),
        'static_text' => 'Reference: SMEWW, 24th Edition 2023 – (Method Reference)',
      ),
    ),
    11 => 
    array (
      'name' => 'fo28_notes',
      'label' => 'Notes',
      'type' => 'multiline',
      'page' => 1,
      'x' => 15.0,
      'y' => 210.0,
      'w' => 185.0,
      'h' => 16.0,
      'font_size' => 8.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'options' => 
      array (
        'prefer_static' => true,
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 21,
        ),
        'static_text' => 'Notes:
1. This report shall not be reproduced, except in full, without the written approval of the laboratory.
2. The results and interpretations in this Test Report relate only to the samples received, coded and tested by the laboratory.',
      ),
    ),
    12 => 
    array (
      'name' => 'fo28_label_analyzed_by',
      'label' => 'Analyzed by label',
      'type' => 'text',
      'page' => 1,
      'x' => 15.0,
      'y' => 228.0,
      'w' => 80.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'options' => 
      array (
        'prefer_static' => true,
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 39,
        ),
        'static_text' => 'Analyzed by:',
      ),
    ),
    13 => 
    array (
      'name' => 'fo28_label_approved_by',
      'label' => 'Approved by label',
      'type' => 'text',
      'page' => 1,
      'x' => 115.0,
      'y' => 228.0,
      'w' => 80.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'options' => 
      array (
        'prefer_static' => true,
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 39,
        ),
        'static_text' => 'Approved by:',
      ),
    ),
    14 => 
    array (
      'name' => 'results.analyst_name',
      'label' => 'Analyzed by',
      'type' => 'signature',
      'page' => 1,
      'x' => 15.0,
      'y' => 233.0,
      'w' => 90.0,
      'h' => 5.0,
      'font_size' => 9.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 44,
        ),
      ),
    ),
    15 => 
    array (
      'name' => 'results.analyst_name_2',
      'label' => 'Approved by',
      'type' => 'signature',
      'page' => 1,
      'x' => 115.0,
      'y' => 233.0,
      'w' => 90.0,
      'h' => 5.0,
      'font_size' => 9.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_name_2',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 44,
        ),
      ),
    ),
    16 => 
    array (
      'name' => 'fo28_role_analyzed',
      'label' => 'Analyzed by title',
      'type' => 'text',
      'page' => 1,
      'x' => 15.0,
      'y' => 238.0,
      'w' => 90.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'options' => 
      array (
        'prefer_static' => true,
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 47,
        ),
        'static_text' => 'Laboratory Analyst',
      ),
    ),
    17 => 
    array (
      'name' => 'fo28_role_approved',
      'label' => 'Approved by title',
      'type' => 'text',
      'page' => 1,
      'x' => 115.0,
      'y' => 238.0,
      'w' => 90.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'options' => 
      array (
        'prefer_static' => true,
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 47,
        ),
        'static_text' => 'Laboratory Head',
      ),
    ),
    18 => 
    array (
      'name' => 'fo28_prc_label',
      'label' => 'PRC label (analyzed)',
      'type' => 'text',
      'page' => 1,
      'x' => 15.0,
      'y' => 243.0,
      'w' => 22.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'options' => 
      array (
        'prefer_static' => true,
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 50,
        ),
        'static_text' => 'PRC Lic. #',
      ),
    ),
    19 => 
    array (
      'name' => 'results.analyst_prc',
      'label' => 'Analyzed by PRC',
      'type' => 'text',
      'page' => 1,
      'x' => 30.0,
      'y' => 243.0,
      'w' => 40.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_prc',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 50,
        ),
      ),
    ),
    20 => 
    array (
      'name' => 'fo28_prc_label_2',
      'label' => 'PRC label (approved)',
      'type' => 'text',
      'page' => 1,
      'x' => 115.0,
      'y' => 243.0,
      'w' => 32.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'options' => 
      array (
        'prefer_static' => true,
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 50,
        ),
        'static_text' => 'PRC License No.',
      ),
    ),
    21 => 
    array (
      'name' => 'results.analyst_prc_2',
      'label' => 'Approved by PRC',
      'type' => 'text',
      'page' => 1,
      'x' => 137.624,
      'y' => 243.0,
      'w' => 40.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analyst_prc_2',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 50,
        ),
      ),
    ),
    22 => 
    array (
      'name' => 'fo28_form_meta',
      'label' => 'Form control block',
      'type' => 'multiline',
      'page' => 1,
      'x' => 15.0,
      'y' => 252.0,
      'w' => 70.0,
      'h' => 16.0,
      'font_size' => 8.0,
      'font_family' => 'helvetica',
      'font_color' => '#000000',
      'align' => 'L',
      'options' => 
      array (
        'prefer_static' => true,
        'anchor' => 
        array (
          'below' => 'metals_fo28_matrix',
          'offset_mm' => 62,
        ),
        'static_text' => 'NPPC-ADL
LSP 7.8 FO28
Rev.00/Issue1
Effectivity Date: 06/01/2024',
      ),
    ),
  ),
);
