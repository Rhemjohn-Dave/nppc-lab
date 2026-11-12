<?php

/**
 * Field blueprint for LSP-7.8-FO2B.
 * Captured from Form Designer ACTIVE revision 01
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
      'x' => 73.0,
      'y' => 42.5,
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
      'x' => 73.0,
      'y' => 46.4,
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
      'name' => 'results.sample_description',
      'label' => 'Sample Description',
      'type' => 'text',
      'page' => 1,
      'x' => 73.0,
      'y' => 50.3,
      'w' => 50.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.sample_description',
    ),
    3 => 
    array (
      'name' => 'job_orders.sampling_site',
      'label' => 'Sampling Site',
      'type' => 'text',
      'page' => 1,
      'x' => 73.0,
      'y' => 54.0,
      'w' => 50.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'job_orders.sampling_site',
    ),
    4 => 
    array (
      'name' => 'results.control_no',
      'label' => 'Control No',
      'type' => 'text',
      'page' => 1,
      'x' => 73.0,
      'y' => 57.4,
      'w' => 75.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.control_no',
    ),
    5 => 
    array (
      'name' => 'results.collected_by',
      'label' => 'Sample Collected by',
      'type' => 'text',
      'page' => 1,
      'x' => 73.0,
      'y' => 60.6,
      'w' => 50.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.collected_by',
    ),
    6 => 
    array (
      'name' => 'results.collection_datetime',
      'label' => 'Date/Time of Collection',
      'type' => 'text',
      'page' => 1,
      'x' => 161.747,
      'y' => 42.2,
      'w' => 42.0,
      'h' => 4.2,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.collection_datetime',
    ),
    7 => 
    array (
      'name' => 'results.receipt_at',
      'label' => 'Date/Time Submitted',
      'type' => 'text',
      'page' => 1,
      'x' => 167.221,
      'y' => 46.0,
      'w' => 42.0,
      'h' => 4.4,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.receipt_at',
    ),
    8 => 
    array (
      'name' => 'results.analysis_datetime',
      'label' => 'Date/Time Analyzed',
      'type' => 'text',
      'page' => 1,
      'x' => 167.221,
      'y' => 49.0,
      'w' => 42.0,
      'h' => 5.0,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.analysis_datetime',
    ),
    9 => 
    array (
      'name' => 'results.report_date',
      'label' => 'Date Reported',
      'type' => 'text',
      'page' => 1,
      'x' => 167.221,
      'y' => 53.2,
      'w' => 42.0,
      'h' => 4.5,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.report_date',
    ),
    10 => 
    array (
      'name' => 'results.ref_no',
      'label' => 'Reference No',
      'type' => 'text',
      'page' => 1,
      'x' => 167.221,
      'y' => 56.9,
      'w' => 42.0,
      'h' => 4.4,
      'font_size' => 9.0,
      'font_family' => 'calibri',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'results.ref_no',
    ),
    11 => 
    array (
      'name' => 'ww_fo2b_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 28.001,
      'y' => 78.945,
      'w' => 159.492,
      'h' => 106.055,
      'font_size' => 6.5,
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
            'width_pct' => 34,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 8,
          ),
          1 => 
          array (
            'key' => 'method',
            'label' => 'METHOD',
            'width_pct' => 48,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 8,
          ),
          2 => 
          array (
            'key' => 'result',
            'label' => 'RESULTS',
            'width_pct' => 18,
            'align' => 'C',
            'header_align' => 'C',
            'font_size' => 8,
          ),
        ),
        'row_height_mm' => 5,
        'header_row_height_mm' => 5,
        'header_row' => true,
        'border' => true,
        'preview_rows' => 6,
        'test_name_bold' => true,
        'header_bold' => true,
        'method_font_size' => 6,
        'header_font_size' => 8,
        'stretch_body' => false,
      ),
    ),
    12 => 
    array (
      'name' => 'fo2_lab_conditions',
      'label' => 'Lab conditions',
      'type' => 'multiline',
      'page' => 1,
      'x' => 12.0,
      'y' => 172.0,
      'w' => 110.0,
      'h' => 8.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_lab_conditions',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 2,
        ),
        'static_text' => 'Laboratory Conditions during the conduct of test: Temp =      °C ; Humidity =      %
NA – Not Applicable for specified water body .',
      ),
    ),
    13 => 
    array (
      'name' => 'job_orders.field_data',
      'label' => 'FIELD DATA',
      'type' => 'multiline',
      'page' => 1,
      'x' => 126.0,
      'y' => 172.0,
      'w' => 78.0,
      'h' => 28.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'job_orders.field_data',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 35,
        ),
        'static_text' => '**FIELD DATA:**
                       a) Temperature –
                       b) Dissolved Oxygen -
                       c) Time of Sampling -
                       d) Other field data will be available upon request
                            (Field Sampling Logbook)',
      ),
    ),
    14 => 
    array (
      'name' => 'fo2_references',
      'label' => 'References',
      'type' => 'multiline',
      'page' => 1,
      'x' => 12.0,
      'y' => 180.0,
      'w' => 192.0,
      'h' => 10.0,
      'font_size' => 7.5,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_references',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 9,
        ),
        'static_text' => 'References: SMEWW,24th Edition 2023 –(Method Reference) DENR–Environmental Management Bureau DAO No. 2016-08 and 2021-19
US – EPA',
      ),
    ),
    15 => 
    array (
      'name' => 'fo2_notes',
      'label' => 'Notes',
      'type' => 'multiline',
      'page' => 1,
      'x' => 12.221,
      'y' => 190.0,
      'w' => 192.0,
      'h' => 22.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_notes',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 18,
        ),
        'static_text' => 'Notes:
1. This report shall not be reproduced, except in full, without the written approval of the laboratory.
2. The results and interpretations in this Test Report relate only to the samples received, coded and tested by the laboratory.
3. Measurement uncertainty is available upon request.
4. *Not EMB- DENR Accredited.',
      ),
    ),
    16 => 
    array (
      'name' => 'fo2_label_reviewed',
      'label' => 'Reviewed by label',
      'type' => 'text',
      'page' => 1,
      'x' => 12.221,
      'y' => 247.0,
      'w' => 50.0,
      'h' => 4.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_label_reviewed',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 70,
          'above' => 'fo2_form_meta',
          'above_offset_mm' => 42,
        ),
        'static_text' => 'Reviewed by:',
      ),
    ),
    17 => 
    array (
      'name' => 'results.analyst_name',
      'label' => 'Reviewed by',
      'type' => 'text',
      'page' => 1,
      'x' => 12.0,
      'y' => 252.0,
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
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 77,
          'above' => 'fo2_form_meta',
          'above_offset_mm' => 35,
        ),
        'static_text' => 'MA. ANTONIELA C. LANACA',
      ),
    ),
    18 => 
    array (
      'name' => 'fo2_role_reviewed',
      'label' => 'Reviewed role',
      'type' => 'text',
      'page' => 1,
      'x' => 12.0,
      'y' => 258.0,
      'w' => 95.0,
      'h' => 4.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_role_reviewed',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 81,
          'above' => 'fo2_form_meta',
          'above_offset_mm' => 31,
        ),
        'static_text' => 'Laboratory Analyst',
      ),
    ),
    19 => 
    array (
      'name' => 'fo2_lic_reviewed',
      'label' => 'Reviewed license',
      'type' => 'text',
      'page' => 1,
      'x' => 12.0,
      'y' => 262.0,
      'w' => 95.0,
      'h' => 4.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_lic_reviewed',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 84,
          'above' => 'fo2_form_meta',
          'above_offset_mm' => 28,
        ),
        'static_text' => 'Registered Chemical Technician, License #00761',
      ),
    ),
    20 => 
    array (
      'name' => 'fo2_label_certified',
      'label' => 'Certified Correct label',
      'type' => 'text',
      'page' => 1,
      'x' => 116.0,
      'y' => 247.0,
      'w' => 55.0,
      'h' => 4.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_label_certified',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 70,
          'above' => 'fo2_form_meta',
          'above_offset_mm' => 42,
        ),
        'static_text' => 'Certified Correct:',
      ),
    ),
    21 => 
    array (
      'name' => 'results.analyst_name_2',
      'label' => 'Certified Correct',
      'type' => 'text',
      'page' => 1,
      'x' => 116.0,
      'y' => 252.0,
      'w' => 90.0,
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
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 77,
          'above' => 'fo2_form_meta',
          'above_offset_mm' => 35,
        ),
        'static_text' => 'WENDY A. TAYSON, RCh, MEnE',
      ),
    ),
    22 => 
    array (
      'name' => 'fo2_role_certified',
      'label' => 'Certified role',
      'type' => 'text',
      'page' => 1,
      'x' => 116.0,
      'y' => 258.0,
      'w' => 90.0,
      'h' => 4.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_role_certified',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 81,
          'above' => 'fo2_form_meta',
          'above_offset_mm' => 31,
        ),
        'static_text' => 'Laboratory Head',
      ),
    ),
    23 => 
    array (
      'name' => 'fo2_lic_certified',
      'label' => 'Certified license',
      'type' => 'text',
      'page' => 1,
      'x' => 116.0,
      'y' => 262.0,
      'w' => 90.0,
      'h' => 4.0,
      'font_size' => 10.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_lic_certified',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 84,
          'above' => 'fo2_form_meta',
          'above_offset_mm' => 28,
        ),
        'static_text' => 'Registered Chemist, License #15131',
      ),
    ),
    24 => 
    array (
      'name' => 'fo2_form_meta',
      'label' => 'Form control footer',
      'type' => 'multiline',
      'page' => 1,
      'x' => 11.747,
      'y' => 283.0,
      'w' => 192.0,
      'h' => 18.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_form_meta',
      'options' => 
      array (
        'static_text' => 'Test Report#: CHE WW –
NPPC-ADL
LSP 7.8_FO2
Rev. 17/Issue 18
Effectivity Date: 09/16/2026',
        'anchor' => 
        array (
          'below' => 'ww_fo2b_matrix',
          'offset_mm' => 110,
        ),
      ),
    ),
  ),
);
