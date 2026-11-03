<?php

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
      'x' => 42.0,
      'y' => 33.0,
      'w' => 78.0,
      'h' => 4.2,
      'font_size' => 9.0,
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
      'x' => 40.0,
      'y' => 37.0,
      'w' => 80.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
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
      'x' => 48.0,
      'y' => 41.0,
      'w' => 72.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
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
      'x' => 45.0,
      'y' => 45.0,
      'w' => 75.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
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
      'x' => 48.0,
      'y' => 49.2,
      'w' => 72.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
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
      'x' => 165.464,
      'y' => 32.8,
      'w' => 42.0,
      'h' => 4.2,
      'font_size' => 8.0,
      'font_family' => 'calibri',
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
      'x' => 155.5,
      'y' => 37.0,
      'w' => 47.0,
      'h' => 4.2,
      'font_size' => 8.0,
      'font_family' => 'calibri',
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
      'x' => 155.0,
      'y' => 41.0,
      'w' => 48.0,
      'h' => 4.2,
      'font_size' => 8.0,
      'font_family' => 'calibri',
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
      'x' => 148.0,
      'y' => 45.0,
      'w' => 54.0,
      'h' => 4.2,
      'font_size' => 8.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.report_date',
    ),
    9 => 
    array (
      'name' => 'dw_old_fo3_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 13.983,
      'y' => 53.858,
      'w' => 188.517,
      'h' => 180.142,
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
            'label' => 'Tests Performed',
            'width_pct' => 23,
            'align' => 'L',
            'header_align' => 'C',
            'font_size' => 8,
          ),
          1 => 
          array (
            'key' => 'method',
            'label' => 'Method',
            'width_pct' => 30,
            'align' => 'L',
            'header_align' => 'C',
            'font_size' => 8,
          ),
          2 => 
          array (
            'key' => 'result',
            'label' => 'Results',
            'width_pct' => 9,
            'align' => 'C',
            'header_align' => 'C',
          ),
          3 => 
          array (
            'key' => 'acceptable_values',
            'label' => 'Acceptable Values',
            'width_pct' => 28,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 7.5,
          ),
          4 => 
          array (
            'key' => 'remarks',
            'label' => 'Remarks',
            'width_pct' => 10,
            'align' => 'C',
            'header_align' => 'C',
          ),
        ),
        'row_height_mm' => 0,
        'header_row_height_mm' => 0,
        'header_row' => true,
        'border' => true,
        'preview_rows' => 19,
        'test_name_bold' => true,
        'header_bold' => true,
        'method_font_size' => 5,
        'header_font_size' => 9,
        'preview_data' => 
        array (
          0 => 
          array (
            'test' => 'Color (Apparent Color)',
            'test_method' => 'Colorimetric Method (2120 B)',
            'test_detail' => NULL,
            'method' => 'Colorimetric Method (2120 B)',
            'acceptable_values' => '10 color units',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'sample_1' => NULL,
            'sample_2' => NULL,
          ),
          1 => 
          array (
            'test' => 'Total Dissolved Solids (mg/L)',
            'test_method' => 'Total Dissolved Solids Dried at 180°C
(2540D)',
            'test_detail' => NULL,
            'method' => 'Total Dissolved Solids Dried at 180°C
(2540D)',
            'acceptable_values' => '600 for product that has not undergone reverse osmosis / distillation process',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'row_span_group' => 'preview-dw-03',
            'row_span_role' => 'start',
            'sample_1' => NULL,
            'sample_2' => NULL,
            'row_variant' => 'raw',
          ),
          2 => 
          array (
            'test' => 'Total Dissolved Solids (mg/L)',
            'test_method' => 'Total Dissolved Solids Dried at 180°C
(2540D)',
            'test_detail' => NULL,
            'method' => 'Total Dissolved Solids Dried at 180°C
(2540D)',
            'acceptable_values' => '<10 for product that undergone reverse osmosis/distillation process',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'row_span_group' => 'preview-dw-03',
            'row_span_role' => 'continue',
            'sample_1' => NULL,
            'sample_2' => NULL,
            'row_variant' => 'finish',
          ),
          3 => 
          array (
            'test' => 'Electrical Conductivity (µS/cm)',
            'test_method' => 'EC Meter (2510 B)',
            'test_detail' => NULL,
            'method' => 'EC Meter (2510 B)',
            'acceptable_values' => NULL,
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'sample_1' => NULL,
            'sample_2' => NULL,
          ),
          4 => 
          array (
            'test' => 'Turbidity (NTU)',
            'test_method' => 'In-House Laboratory Method (Turbidimetry)',
            'test_detail' => NULL,
            'method' => 'In-House Laboratory Method (Turbidimetry)',
            'acceptable_values' => '5 NTU',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'sample_1' => NULL,
            'sample_2' => NULL,
          ),
          5 => 
          array (
            'test' => 'pH',
            'test_method' => 'Electrometric Method (4500-H+ B)',
            'test_detail' => NULL,
            'method' => 'Electrometric Method (4500-H+ B)',
            'acceptable_values' => '6.5-8.5',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'row_span_group' => 'preview-dw-02',
            'row_span_role' => 'start',
            'sample_1' => NULL,
            'sample_2' => NULL,
            'row_variant' => 'raw',
          ),
          6 => 
          array (
            'test' => 'pH',
            'test_method' => 'Electrometric Method (4500-H+ B)',
            'test_detail' => NULL,
            'method' => 'Electrometric Method (4500-H+ B)',
            'acceptable_values' => '5-7 for product water that undergone reverse osmosis/distillation process',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'row_span_group' => 'preview-dw-02',
            'row_span_role' => 'continue',
            'sample_1' => NULL,
            'sample_2' => NULL,
            'row_variant' => 'finish',
          ),
          7 => 
          array (
            'test' => 'Chloride (mg/L)',
            'test_method' => 'Argentometric Method
4500-Cl- B',
            'test_detail' => NULL,
            'method' => 'Argentometric Method 4500-Cl- B',
            'acceptable_values' => '250 mg/L',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'sample_1' => NULL,
            'sample_2' => NULL,
          ),
          8 => 
          array (
            'test' => 'Residual Chlorine (mg/L)',
            'test_method' => 'DPD Colorimetric Method',
            'test_detail' => NULL,
            'method' => 'DPD Colorimetric Method',
            'acceptable_values' => '<1.0 mg/L for RO water',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'row_span_group' => 'preview-dw-05',
            'row_span_role' => 'start',
            'sample_1' => NULL,
            'sample_2' => NULL,
            'row_variant' => 'finish',
          ),
          9 => 
          array (
            'test' => 'Residual Chlorine (mg/L)',
            'test_method' => 'DPD Colorimetric Method',
            'test_detail' => NULL,
            'method' => 'DPD Colorimetric Method',
            'acceptable_values' => '0.3 - 1.5 mg/L for chlorinated drinking water',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'row_span_group' => 'preview-dw-05',
            'row_span_role' => 'continue',
            'sample_1' => NULL,
            'sample_2' => NULL,
            'row_variant' => 'chlorinated',
          ),
          10 => 
          array (
            'test' => 'Residual Chlorine (mg/L)',
            'test_method' => 'DPD Colorimetric Method',
            'test_detail' => NULL,
            'method' => 'DPD Colorimetric Method',
            'acceptable_values' => 'Not applicable to Raw Water',
            'result' => '-',
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'row_span_group' => 'preview-dw-05',
            'row_span_role' => 'continue',
            'sample_1' => '-',
            'sample_2' => NULL,
            'row_variant' => 'raw',
          ),
          11 => 
          array (
            'test' => 'Total Hardness (mg/L)',
            'test_method' => 'Titrimetric Method (2340 C)',
            'test_detail' => NULL,
            'method' => 'Titrimetric Method (2340 C)',
            'acceptable_values' => '300 mg/L as CaCO3',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'sample_1' => NULL,
            'sample_2' => NULL,
          ),
          12 => 
          array (
            'test' => 'Calcium Hardness (mg/L)',
            'test_method' => 'Titrimetric Method (2340 C)',
            'test_detail' => NULL,
            'method' => 'Titrimetric Method (2340 C)',
            'acceptable_values' => NULL,
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'sample_1' => NULL,
            'sample_2' => NULL,
          ),
          13 => 
          array (
            'test' => 'Magnesium Hardness (mg/L)',
            'test_method' => 'Titrimetric Method (2340 C)',
            'test_detail' => NULL,
            'method' => 'Titrimetric Method (2340 C)',
            'acceptable_values' => NULL,
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'sample_1' => NULL,
            'sample_2' => NULL,
          ),
          14 => 
          array (
            'test' => 'Iron (mg/L)',
            'test_method' => 'Phenanthroline Method
3500 - Fe B',
            'test_detail' => NULL,
            'method' => 'Phenanthroline Method
3500 - Fe B',
            'acceptable_values' => '1.0 mg/L',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'sample_1' => NULL,
            'sample_2' => NULL,
          ),
          15 => 
          array (
            'test' => 'Nitrate (mg/L)',
            'test_method' => 'Nitrate Electrode Method (4500-NO3-D)',
            'test_detail' => NULL,
            'method' => 'Nitrate Electrode Method (4500-NO3-D)',
            'acceptable_values' => '50 mg/L',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'sample_1' => NULL,
            'sample_2' => NULL,
          ),
          16 => 
          array (
            'test' => 'Nitrite (mg/L)',
            'test_method' => 'Diazotization Method',
            'test_detail' => NULL,
            'method' => 'Diazotization Method',
            'acceptable_values' => '3 mg/L',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'sample_1' => NULL,
            'sample_2' => NULL,
          ),
          17 => 
          array (
            'test' => 'Sulfate (mg/L)',
            'test_method' => 'Turbidimetric Method
4500 - SO4-2 E',
            'test_detail' => NULL,
            'method' => 'Turbidimetric Method
4500 - SO4-2 E',
            'acceptable_values' => '250 mg/L',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'sample_1' => NULL,
            'sample_2' => NULL,
          ),
          18 => 
          array (
            'test' => 'Odor',
            'test_method' => 'Threshold Odor Test
(Sensory Evaluation Technique, 2150 B)',
            'test_detail' => NULL,
            'method' => 'Threshold Odor Test
(Sensory Evaluation Technique, 2150 B)',
            'acceptable_values' => 'No objectionable odor',
            'result' => NULL,
            'remarks' => NULL,
            'control_no' => NULL,
            'sample_description' => NULL,
            'sample_1' => NULL,
            'sample_2' => NULL,
          ),
        ),
        'stretch_body' => false,
        'row_heights_mm' => 
        array (
          'total dissolved solids (mg/l)' => 5,
          'ph' => 3,
          'chloride (mg/l)' => 3,
          'electrical conductivity (µs/cm)' => 3,
        ),
      ),
    ),
    10 => 
    array (
      'name' => 'fo3_lab_conditions',
      'label' => 'Lab test conditions',
      'type' => 'multiline',
      'page' => 1,
      'x' => 12.242,
      'y' => 180.0,
      'w' => 192.0,
      'h' => 5.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_lab_conditions',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 2,
        ),
        'static_text' => 'Laboratory Test Conditions during the conduct of the test:   Temp:          Humidity:',
      ),
    ),
    11 => 
    array (
      'name' => 'fo3_references',
      'label' => 'References',
      'type' => 'multiline',
      'page' => 1,
      'x' => 12.0,
      'y' => 185.0,
      'w' => 192.0,
      'h' => 8.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_references',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 6.5,
        ),
        'static_text' => 'References: Standard Methods for the Examination of Water and Wastewater 24th Edition 2023
                                    Philippine National Standards for Drinking Water 2017 (AO 2017-0010)',
      ),
    ),
    12 => 
    array (
      'name' => 'fo3_note_left',
      'label' => 'NOTE (left)',
      'type' => 'multiline',
      'page' => 1,
      'x' => 12.0,
      'y' => 194.0,
      'w' => 95.0,
      'h' => 20.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_note_left',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 15,
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
      'name' => 'fo3_note_right',
      'label' => 'NOTE (right)',
      'type' => 'multiline',
      'page' => 1,
      'x' => 108.0,
      'y' => 194.0,
      'w' => 96.0,
      'h' => 20.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_note_right',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 15,
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
      'name' => 'fo3_label_analyzed_by',
      'label' => 'Analyzed by label',
      'type' => 'text',
      'page' => 1,
      'x' => 12.0,
      'y' => 216.0,
      'w' => 40.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_label_analyzed_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 36,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 42.0,
        ),
        'static_text' => 'Analyzed by:',
      ),
    ),
    15 => 
    array (
      'name' => 'fo3_label_certified',
      'label' => 'Certified Correct label',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 216.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_label_certified',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 36,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 42.0,
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
      'y' => 223.0,
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
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 43,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 35.0,
        ),
      ),
    ),
    17 => 
    array (
      'name' => 'results.analyst_name_2',
      'label' => 'Certified Correct name',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 223.0,
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
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 43,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 35.0,
        ),
      ),
    ),
    18 => 
    array (
      'name' => 'fo3_role_analyzed',
      'label' => 'Analyzed by role',
      'type' => 'text',
      'page' => 1,
      'x' => 12.0,
      'y' => 229.0,
      'w' => 90.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_role_analyzed',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 50,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 28.0,
        ),
        'static_text' => 'Laboratory Analyst',
      ),
    ),
    19 => 
    array (
      'name' => 'fo3_role_certified',
      'label' => 'Certified Correct role',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 229.0,
      'w' => 80.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_role_certified',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 50,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 28.0,
        ),
        'static_text' => 'Laboratory Analyst',
      ),
    ),
    20 => 
    array (
      'name' => 'fo3_lic_prefix_1',
      'label' => 'Analyzed by license prefix',
      'type' => 'text',
      'page' => 1,
      'x' => 12.0,
      'y' => 234.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_lic_prefix_1',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 47,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 31.0,
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
      'y' => 234.0,
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
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 47,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 31.0,
        ),
      ),
    ),
    22 => 
    array (
      'name' => 'fo3_lic_prefix_2',
      'label' => 'Certified Correct license prefix',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 234.0,
      'w' => 35.801,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_lic_prefix_2',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 47,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 31.0,
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
      'y' => 234.0,
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
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 47,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 31.0,
        ),
      ),
    ),
    24 => 
    array (
      'name' => 'fo3_label_noted_by',
      'label' => 'Noted by label',
      'type' => 'text',
      'page' => 1,
      'x' => 125.964,
      'y' => 244.0,
      'w' => 40.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_label_noted_by',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 58,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 20.0,
        ),
        'static_text' => 'Noted by:',
      ),
    ),
    25 => 
    array (
      'name' => 'fo3_noted_by_name',
      'label' => 'Noted by name',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 251.0,
      'w' => 80.0,
      'h' => 5.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_noted_by_name',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 63,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 15.0,
        ),
        'static_text' => 'WENDY A. TAYSON, RCh, MEnE',
      ),
    ),
    26 => 
    array (
      'name' => 'fo3_role_noted',
      'label' => 'Noted by role',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 257.0,
      'w' => 80.0,
      'h' => 4.0,
      'font_size' => 9.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_role_noted',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 67,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 11.0,
        ),
        'static_text' => 'Laboratory Head',
      ),
    ),
    27 => 
    array (
      'name' => 'fo3_lic_noted',
      'label' => 'Noted by license',
      'type' => 'text',
      'page' => 1,
      'x' => 126.0,
      'y' => 262.0,
      'w' => 80.0,
      'h' => 4.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_lic_noted',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 70,
          'above' => 'fo3_form_meta',
          'above_offset_mm' => 8.0,
        ),
        'static_text' => 'Registered Chemist, License #15131',
      ),
    ),
    28 => 
    array (
      'name' => 'fo3_form_meta',
      'label' => 'Form control footer',
      'type' => 'multiline',
      'page' => 1,
      'x' => 11.95,
      'y' => 250.0,
      'w' => 192.0,
      'h' => 17.672,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo3_form_meta',
      'options' => 
      array (
        'static_text' => 'Test Report #: CHE DW –
Laboratory DOH Accreditation # # DOH052581
NPPC-ADL
LSP 7.8 FO3
Rev. 10 / Issue 11 Effectivity Date:  09/01/2026',
        'anchor' => 
        array (
          'below' => 'dw_old_fo3_matrix',
          'offset_mm' => 75,
        ),
      ),
    ),
  ),
);
