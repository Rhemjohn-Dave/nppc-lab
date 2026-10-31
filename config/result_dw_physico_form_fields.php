<?php

/**
 * Field blueprint for LSP-7.8-FO37.
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
      'x' => 40.0,
      'y' => 33.3,
      'w' => 74.0,
      'h' => 4.2,
      'font_size' => 10.0,
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
      'x' => 38.0,
      'y' => 37.3,
      'w' => 76.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.address',
    ),
    2 => 
    array (
      'name' => 'results.ref_no',
      'label' => 'Reference No',
      'type' => 'text',
      'page' => 1,
      'x' => 46.0,
      'y' => 41.3,
      'w' => 68.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.ref_no',
    ),
    3 => 
    array (
      'name' => 'results.control_no',
      'label' => 'Control No',
      'type' => 'text',
      'page' => 1,
      'x' => 43.0,
      'y' => 45.3,
      'w' => 71.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.control_no',
    ),
    4 => 
    array (
      'name' => 'results.sample_code',
      'label' => 'Sample Code',
      'type' => 'text',
      'page' => 1,
      'x' => 46.0,
      'y' => 49.6,
      'w' => 68.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sample_code',
    ),
    5 => 
    array (
      'name' => 'results.collection_datetime',
      'label' => 'Date/Time of Collection',
      'type' => 'text',
      'page' => 1,
      'x' => 159.0,
      'y' => 33.3,
      'w' => 42.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.collection_datetime',
    ),
    6 => 
    array (
      'name' => 'results.receipt_at',
      'label' => 'Date/Time Submitted',
      'type' => 'text',
      'page' => 1,
      'x' => 155.0,
      'y' => 37.3,
      'w' => 46.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.receipt_at',
    ),
    7 => 
    array (
      'name' => 'results.analysis_datetime',
      'label' => 'Date/Time Analyzed',
      'type' => 'text',
      'page' => 1,
      'x' => 154.0,
      'y' => 41.3,
      'w' => 47.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analysis_datetime',
    ),
    8 => 
    array (
      'name' => 'results.report_date',
      'label' => 'Date Reported',
      'type' => 'text',
      'page' => 1,
      'x' => 145.0,
      'y' => 45.3,
      'w' => 56.0,
      'h' => 4.2,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.report_date',
    ),
    9 => 
    array (
      'name' => 'dw_physico_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 20.024,
      'y' => 60.051,
      'w' => 176.976,
      'h' => 113.684,
      'font_size' => 7.0,
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
            'label' => 'Tests Performed',
            'width_pct' => 23,
            'align' => 'L',
            'header_align' => 'C',
            'font_size' => 9,
          ),
          1 => 
          array (
            'key' => 'method',
            'label' => 'Method',
            'width_pct' => 33,
            'align' => 'L',
            'header_align' => 'C',
            'font_size' => 9,
          ),
          2 => 
          array (
            'key' => 'result',
            'label' => 'Results',
            'width_pct' => 10,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 9,
          ),
          3 => 
          array (
            'key' => 'acceptable_values',
            'label' => 'Acceptable Values',
            'width_pct' => 23,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 9,
          ),
          4 => 
          array (
            'key' => 'remarks',
            'label' => 'Remarks',
            'width_pct' => 11,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 9,
          ),
        ),
        'row_height_mm' => 0,
        'header_row_height_mm' => 5,
        'header_row' => true,
        'border' => true,
        'preview_rows' => 13,
        'stretch_body' => false,
        'test_name_bold' => true,
        'header_bold' => true,
        'method_font_size' => 9,
        'header_font_size' => 8,
        'preview_data' => 
        array (
          0 => 
          array (
            'test' => 'Color (Apparent Color)',
            'test_method' => 'Colorimetric Method (2120 B)',
            'result' => NULL,
            'method' => 'Colorimetric Method',
          ),
          1 => 
          array (
            'test' => 'Total Dissolved Solids (mg/L)',
            'test_method' => 'Total Dissolved Solids Dried at 180°C (2540D)',
            'acceptable_values' => '600 for product that has not undergone reverse osmosis / distillation process',
            'result' => NULL,
            'row_span_group' => 'preview-dw-03',
            'row_span_role' => 'start',
          ),
          2 => 
          array (
            'test' => 'Total Dissolved Solids (mg/L)',
            'test_method' => 'Total Dissolved Solids Dried at 180°C (2540D)',
            'acceptable_values' => '<10 for product that undergone reverse osmosis/distillation process',
            'result' => NULL,
            'row_span_group' => 'preview-dw-03',
            'row_span_role' => 'continue',
          ),
          3 => 
          array (
            'test' => 'Turbidity (NTU)',
            'test_method' => 'In-House Laboratory Method (Turbidimetry)',
            'result' => NULL,
          ),
          4 => 
          array (
            'test' => 'pH',
            'test_method' => 'Electrometric Method (4500-H+ B)',
            'acceptable_values' => '6.5-8.5',
            'result' => NULL,
            'row_span_group' => 'preview-dw-02',
            'row_span_role' => 'start',
          ),
          5 => 
          array (
            'test' => 'pH',
            'test_method' => 'Electrometric Method (4500-H+ B)',
            'acceptable_values' => '5-7 for product water that undergone reverse osmosis/distillation process',
            'result' => NULL,
            'row_span_group' => 'preview-dw-02',
            'row_span_role' => 'continue',
          ),
          6 => 
          array (
            'test' => 'Residual Chlorine (mg/L)',
            'test_method' => 'DPD Colorimetric Method',
            'acceptable_values' => '<1.0 mg/L for RO water',
            'result' => NULL,
            'row_span_group' => 'preview-dw-05',
            'row_span_role' => 'start',
            'row_variant' => 'finish',
          ),
          7 => 
          array (
            'test' => 'Residual Chlorine (mg/L)',
            'test_method' => 'DPD Colorimetric Method',
            'acceptable_values' => '0.3 - 1.5 mg/L for chlorinated drinking water',
            'result' => NULL,
            'row_span_group' => 'preview-dw-05',
            'row_span_role' => 'continue',
            'row_variant' => 'chlorinated',
          ),
          8 => 
          array (
            'test' => 'Residual Chlorine (mg/L)',
            'test_method' => 'DPD Colorimetric Method',
            'acceptable_values' => 'Not applicable to Raw Water',
            'result' => '-',
            'row_span_group' => 'preview-dw-05',
            'row_span_role' => 'continue',
            'row_variant' => 'raw',
          ),
          9 => 
          array (
            'test' => 'Nitrate (mg/L)',
            'test_method' => 'Nitrate Electrode Method (4500-NO3-D)',
            'result' => NULL,
          ),
          10 => 
          array (
            'test' => 'Arsenic (mg/L)',
            'test_method' => 'Electrothermal-AAS Method (3113B)
Nitric Acid Digestion (3030E)',
            'result' => NULL,
          ),
          11 => 
          array (
            'test' => 'Lead (mg/L)',
            'test_method' => 'Electrothermal-AAS Method (3113B)
Nitric Acid Digestion (3030E)',
            'result' => NULL,
          ),
          12 => 
          array (
            'test' => 'Cadmium (mg/L)',
            'test_method' => 'Electrothermal-AAS Method (3113B)
Nitric Acid Digestion (3030E)',
            'result' => NULL,
          ),
        ),
      ),
    ),
    10 => 
    array (
      'name' => 'fo37_lab_conditions',
      'label' => 'Lab test conditions',
      'type' => 'multiline',
      'page' => 1,
      'x' => 12.242,
      'y' => 180.0,
      'w' => 192.0,
      'h' => 8.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_lab_conditions',
      'options' => 
      array (
        'cover' => true,
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 2,
        ),
        'static_text' => 'Laboratory Test Conditions during the conduct of the test:   Temp:          Humidity:
Method Detection Limit: Arsenic=6x10-5 mg/L, Lead=0.003 mg/L, Cadmium=0.002 mg/L',
      ),
    ),
    11 => 
    array (
      'name' => 'fo37_references',
      'label' => 'References',
      'type' => 'multiline',
      'page' => 1,
      'x' => 12.0,
      'y' => 189.0,
      'w' => 192.0,
      'h' => 8.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_references',
      'options' => 
      array (
        'cover' => true,
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 10.5,
        ),
        'static_text' => 'References: Standard Methods for the Examination of Water and Wastewater 24th Edition 2023
                                    Philippine National Standards for Drinking Water 2017 (AO 2017-0010)',
      ),
    ),
    12 => 
    array (
      'name' => 'fo37_note_left',
      'label' => 'NOTE (left)',
      'type' => 'multiline',
      'page' => 1,
      'x' => 12.0,
      'y' => 198.0,
      'w' => 95.0,
      'h' => 20.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_note_left',
      'options' => 
      array (
        'cover' => true,
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 19,
        ),
        'static_text' => 'NOTE:
1. This report shall not be reproduced, except in full, without the written
    approval of the Laboratory Head.
2. The results contained in this report relate only to the items received,
    coded and tested by the laboratory.
3. Measurement uncertainty is available upon request.',
      ),
    ),
    13 => 
    array (
      'name' => 'fo37_note_right',
      'label' => 'NOTE (right)',
      'type' => 'multiline',
      'page' => 1,
      'x' => 108.0,
      'y' => 198.0,
      'w' => 96.0,
      'h' => 20.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_note_right',
      'options' => 
      array (
        'cover' => true,
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 19,
        ),
        'static_text' => '4. Statements of conformity (e.g. Pass/Fail) to specifications are made in this
    report without taking measurement uncertainty into account except when
    requested by the customer. Where statements of uncertainty are made in this
    report, the following rules are applied: PASS- results within limit
                             FAIL- results exceed limit',
      ),
    ),
    14 => 
    array (
      'name' => 'fo37_label_analyzed_by',
      'label' => 'Analyzed by label',
      'type' => 'text',
      'page' => 1,
      'x' => 12.0,
      'y' => 220.0,
      'w' => 40.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_label_analyzed_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 40,
        ),
        'static_text' => 'Analyzed by:',
      ),
    ),
    15 => 
    array (
      'name' => 'fo37_label_certified',
      'label' => 'Certified Correct label',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 220.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_label_certified',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 40,
        ),
        'static_text' => 'Certified Correct:',
      ),
    ),
    16 => 
    array (
      'name' => 'results.analyst_name',
      'label' => 'Analyzed by name',
      'type' => 'text',
      'page' => 1,
      'x' => 12.0,
      'y' => 227.0,
      'w' => 90.0,
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
          'below' => 'dw_physico_matrix',
          'offset_mm' => 47,
        ),
        'static_text' => 'MA. ANTONIELA C. LANACA',
      ),
    ),
    17 => 
    array (
      'name' => 'results.analyst_name_2',
      'label' => 'Certified Correct name',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 227.0,
      'w' => 80.0,
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
          'below' => 'dw_physico_matrix',
          'offset_mm' => 47,
        ),
        'static_text' => 'JAYRAYNE ERIC G. BARNUEVO, RCh',
      ),
    ),
    18 => 
    array (
      'name' => 'fo37_role_analyzed',
      'label' => 'Analyzed by role',
      'type' => 'text',
      'page' => 1,
      'x' => 12.0,
      'y' => 233.0,
      'w' => 90.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_role_analyzed',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 54,
        ),
        'static_text' => 'Laboratory Analyst',
      ),
    ),
    19 => 
    array (
      'name' => 'fo37_role_certified',
      'label' => 'Certified Correct role',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 233.0,
      'w' => 80.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_role_certified',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 54,
        ),
        'static_text' => 'Laboratory Analyst',
      ),
    ),
    20 => 
    array (
      'name' => 'fo37_lic_prefix_1',
      'label' => 'Analyzed by license prefix',
      'type' => 'text',
      'page' => 1,
      'x' => 12.0,
      'y' => 238.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_lic_prefix_1',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 51,
        ),
        'static_text' => 'Registered Chemical Technician, License #',
      ),
    ),
    21 => 
    array (
      'name' => 'results.analyst_prc',
      'label' => 'Analyzed by PRC',
      'type' => 'text',
      'page' => 1,
      'x' => 61.0,
      'y' => 238.0,
      'w' => 20.0,
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
          'below' => 'dw_physico_matrix',
          'offset_mm' => 51,
        ),
        'static_text' => '00761',
      ),
    ),
    22 => 
    array (
      'name' => 'fo37_lic_prefix_2',
      'label' => 'Certified Correct license prefix',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 238.0,
      'w' => 35.801,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_lic_prefix_2',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 51,
        ),
        'static_text' => 'Registered Chemist, License #',
      ),
    ),
    23 => 
    array (
      'name' => 'results.analyst_prc_2',
      'label' => 'Certified Correct PRC',
      'type' => 'text',
      'page' => 1,
      'x' => 161.301,
      'y' => 238.0,
      'w' => 22.0,
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
          'below' => 'dw_physico_matrix',
          'offset_mm' => 51,
        ),
        'static_text' => '16126',
      ),
    ),
    24 => 
    array (
      'name' => 'fo37_label_noted_by',
      'label' => 'Noted by label',
      'type' => 'text',
      'page' => 1,
      'x' => 125.964,
      'y' => 248.0,
      'w' => 40.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_label_noted_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 62,
        ),
        'static_text' => 'Noted by:',
      ),
    ),
    25 => 
    array (
      'name' => 'fo37_noted_by_name',
      'label' => 'Noted by name',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 255.0,
      'w' => 80.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_noted_by_name',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 67,
        ),
        'static_text' => 'WENDY A. TAYSON, RCh, MEnE',
      ),
    ),
    26 => 
    array (
      'name' => 'fo37_role_noted',
      'label' => 'Noted by role',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 261.0,
      'w' => 80.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_role_noted',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 71,
        ),
        'static_text' => 'Laboratory Head',
      ),
    ),
    27 => 
    array (
      'name' => 'fo37_lic_noted',
      'label' => 'Noted by license',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 266.0,
      'w' => 80.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_lic_noted',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 74,
        ),
        'static_text' => 'Registered Chemist, License #15131',
      ),
    ),
    28 => 
    array (
      'name' => 'fo37_form_meta',
      'label' => 'Form control footer',
      'type' => 'multiline',
      'page' => 1,
      'x' => 11.95,
      'y' => 268.0,
      'w' => 192.0,
      'h' => 17.672,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo37_form_meta',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_physico_matrix',
          'offset_mm' => 79,
        ),
        'static_text' => 'Test Report #: CHE DW – 
Laboratory DOH Accreditation # DOH052581
NPPC-ADL
LSP 7.8 FO37
Rev. 06 / Issue 07
Effectivity Date:  09/01/2026',
      ),
    ),
  ),
);
