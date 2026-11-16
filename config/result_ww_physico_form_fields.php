<?php

/**
 * Field blueprint for LSP-7.8-FO2.
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
      'x' => 60.808,
      'y' => 34.85,
      'w' => 60.0,
      'h' => 5.0,
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
      'x' => 60.808,
      'y' => 38.9,
      'w' => 60.0,
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
      'x' => 60.808,
      'y' => 42.8,
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
      'x' => 60.808,
      'y' => 46.5,
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
      'x' => 60.808,
      'y' => 49.9,
      'w' => 60.0,
      'h' => 4.0,
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
      'x' => 60.808,
      'y' => 53.6,
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
      'x' => 166.696,
      'y' => 34.35,
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
      'x' => 172.17,
      'y' => 38.15,
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
      'x' => 172.17,
      'y' => 41.65,
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
      'x' => 172.17,
      'y' => 45.85,
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
      'x' => 172.17,
      'y' => 49.55,
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
      'name' => 'ww_fo2_matrix',
      'label' => 'Dynamic test matrix',
      'type' => 'dynamic_test_matrix',
      'page' => 1,
      'x' => 31.062,
      'y' => 63.5,
      'w' => 159.492,
      'h' => 120.0,
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
        'preview_rows' => 19,
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
      'w' => 192.0,
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
          'below' => 'ww_fo2_matrix',
          'offset_mm' => 2,
        ),
        'static_text' => 'Laboratory Conditions during the conduct of test: Temp =      °C ; Humidity =      %
NA – Not Applicable for specified water body .',
      ),
    ),
    13 => 
    array (
      'name' => 'fo2_references',
      'label' => 'References',
      'type' => 'multiline',
      'page' => 1,
      'x' => 12.0,
      'y' => 181.0,
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
          'below' => 'ww_fo2_matrix',
          'offset_mm' => 10,
        ),
        'static_text' => 'References: SMEWW,24th Edition 2023 –(Method Reference) DENR–Environmental Management Bureau DAO No. 2016-08 and 2021-19
US – EPA',
      ),
    ),
    14 => 
    array (
      'name' => 'fo2_notes',
      'label' => 'Notes',
      'type' => 'multiline',
      'page' => 1,
      'x' => 11.95,
      'y' => 192.0,
      'w' => 191.95,
      'h' => 23.5,
      'font_size' => 7.5,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_notes',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'ww_fo2_matrix',
          'offset_mm' => 16,
        ),
        'static_text' => 'Notes:
        1. This report shall not be reproduced, except in full, without the written approval of the laboratory.
        2. The results and interpretations in this Test Report relate only to the samples received, coded and tested by the laboratory.
        3. Measurement uncertainty is available upon request.
        4. *Not PAB Accredited but EMB DENR accredited.
        5. **Not EMB –DENR but PAB accredited.
        6. ***Not PAB and EMB - DENR Accredited.',
      ),
    ),
    15 => 
    array (
      'name' => 'fo2_signatory_reviewed',
      'label' => 'Reviewed by',
      'type' => 'multiline',
      'page' => 1,
      'x' => 11.95,
      'y' => 204.0,
      'w' => 95.0,
      'h' => 24.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_signatory_reviewed',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'fo2_notes',
          'offset_mm' => 5,
          'above' => 'fo2_form_meta',
          'above_offset_mm' => 46,
        ),
        'signatory_block' => 
        array (
          'label' => 'Reviewed by:',
          'people' => 
          array (
            0 => 
            array (
              'name_key' => 'results.analyst_name',
              'prc_key' => 'results.analyst_prc',
              'fallback_name' => 'MA. ANTONIELA C. LANACA',
              'lines_after_name' => 
              array (
                0 => 'Approved Signatory (Chem)',
                1 => 'Laboratory Analyst',
                2 => 'Registered Chemical Technician, License #',
              ),
            ),
          ),
        ),
        'static_text' => 'Reviewed by:

**MA. ANTONIELA C. LANACA**
Approved Signatory (Chem)
Laboratory Analyst
Registered Chemical Technician, License #',
        'line_height_factor' => 0.755,
      ),
    ),
    16 => 
    array (
      'name' => 'fo2_signatory_certified',
      'label' => 'Certified Correct',
      'type' => 'multiline',
      'page' => 1,
      'x' => 113.796,
      'y' => 204.0,
      'w' => 88.0,
      'h' => 40.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_signatory_certified',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'fo2_notes',
          'offset_mm' => 5,
          'above' => 'fo2_form_meta',
          'above_offset_mm' => 46,
        ),
        'signatory_block' => 
        array (
          'label' => 'Certified Correct:',
          'people' => 
          array (
            0 => 
            array (
              'name_key' => 'results.analyst_name_3',
              'prc_key' => 'results.analyst_prc_3',
              'fallback_name' => 'FERRESA MAE O. CABEROY, RCh.',
              'lines_after_name' => 
              array (
                0 => 'Approved Signatory (Chem)',
                1 => 'Laboratory Analyst',
                2 => 'Registered Chemist, License #',
              ),
            ),
            1 => 
            array (
              'name_key' => 'results.analyst_name_4',
              'prc_key' => 'results.analyst_prc_4',
              'fallback_name' => 'JAYRAYNE ERIC G. BARNUEVO, RCh',
              'lines_after_name' => 
              array (
                0 => 'Approved Signatory (Chem)',
                1 => 'Laboratory Analyst',
                2 => 'Registered Chemist, License #',
              ),
            ),
          ),
        ),
        'static_text' => 'Certified Correct:

**FERRESA MAE O. CABEROY, RCh.**
Approved Signatory (Chem)
Laboratory Analyst
Registered Chemist, License #


**JAYRAYNE ERIC G. BARNUEVO, RCh**
Approved Signatory (Chem)
Laboratory Analyst
Registered Chemist, License #',
        'line_height_factor' => 0.755,
      ),
    ),
    17 => 
    array (
      'name' => 'fo2_signatory_noted',
      'label' => 'Noted By',
      'type' => 'multiline',
      'page' => 1,
      'x' => 12.0,
      'y' => 230.0,
      'w' => 95.0,
      'h' => 20.0,
      'font_size' => 8.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_signatory_noted',
      'options' => 
      array (
        'anchor' => 
        array (
          'below' => 'fo2_notes',
          'offset_mm' => 27,
          'above' => 'fo2_form_meta',
          'above_offset_mm' => 24,
        ),
        'signatory_block' => 
        array (
          'label' => 'Noted By:',
          'people' => 
          array (
            0 => 
            array (
              'name_key' => 'results.analyst_name_2',
              'prc_key' => 'results.analyst_prc_2',
              'fallback_name' => 'WENDY A. TAYSON, RCh, MEnE',
              'lines_after_name' => 
              array (
                0 => 'Laboratory Head',
                1 => 'Registered Chemist, License #',
              ),
            ),
          ),
        ),
        'static_text' => 'Noted By:

**WENDY A. TAYSON, RCh, MEnE**
Laboratory Head
Registered Chemist, License #',
        'line_height_factor' => 0.755,
      ),
    ),
    18 => 
    array (
      'name' => 'fo2_form_meta',
      'label' => 'Form control footer',
      'type' => 'multiline',
      'page' => 1,
      'x' => 11.95,
      'y' => 257.0,
      'w' => 192.0,
      'h' => 18.0,
      'font_size' => 7.0,
      'font_family' => 'times',
      'font_color' => '#000000',
      'align' => 'L',
      'data_source_key' => 'fo2_form_meta',
      'options' => 
      array (
        'static_text' => 'Test Report#: **CHE WW –**
NPPC-ADL
LSP 7.8  FO2
Rev. 17/Issue 18
Effectivity Date: 09/16/2026',
        'anchor' => 
        array (
          'below' => 'fo2_notes',
          'offset_mm' => 51,
        ),
      ),
    ),
  ),
);
