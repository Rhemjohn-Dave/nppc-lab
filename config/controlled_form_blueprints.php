<?php

/**
 * Maps controlled form codes to field-blueprint config keys.
 * Import Blueprint / fresh install uses these configs to seed overlay boxes.
 */
return [
    \App\Models\ControlledForm::RFA_FORM_CODE => 'rfa_form_fields',
    \App\Models\ControlledForm::RFA_AQUA_FORM_CODE => 'rfa_aqua_form_fields',
    'LSP-7.8-FO4' => 'result_fo4_form_fields',
    'LSP-7.8-FO5' => 'result_fo5_form_fields',
    'LSP-7.8-FO2' => 'result_ww_physico_form_fields',
    'LSP-7.8-FO37' => 'result_dw_physico_form_fields',
    'LSP-7.8-FO3' => 'result_dw_old_fo3_form_fields',
    'LSP-7.8-FO26' => 'result_fo26_form_fields',
    'LSP-7.8-FO27' => 'result_fo27_form_fields',
    'LSP-7.8-F016-PROX' => 'result_proximate_form_fields',
    'LSP-7.8-F016-MILK' => 'result_milk_form_fields',
    'LSP-7.8-F016-WA' => 'result_water_activity_form_fields',
    'LSP-7.8-F016-CAP' => 'result_chloramphenicol_form_fields',
    'LSP-7.8-F016-NO2' => 'result_nitrite_form_fields',
];
