<?php

/**
 * Field blueprint for the official RFA / Job Order flat PDF (folio 8.5 × 13 in).
 *
 * Coordinates are millimetres from the top-left of page 1.
 * These are first-pass placeholders measured from the scan — recalibrate
 * after the official soft-copy PDF is uploaded.
 *
 * @return array{
 *     page: array{width: float, height: float, unit: string},
 *     fields: list<array{
 *         name: string,
 *         type: 'text'|'checkbox'|'multiline',
 *         page: int,
 *         x: float,
 *         y: float,
 *         w: float,
 *         h: float,
 *         font_size?: float,
 *         align?: string
 *     }>
 * }
 */
return (function () {
    $page = [
        'width' => 215.9,
        'height' => 330.2,
        'unit' => 'mm',
    ];

    $fields = [
        // Header
        ['name' => 'reference_no', 'type' => 'text', 'page' => 1, 'x' => 165.0, 'y' => 22.0, 'w' => 35.0, 'h' => 5.0, 'font_size' => 11],

        // Customer block
        ['name' => 'customer_name', 'type' => 'text', 'page' => 1, 'x' => 38.0, 'y' => 48.0, 'w' => 100.0, 'h' => 4.5, 'font_size' => 8],
        ['name' => 'address', 'type' => 'text', 'page' => 1, 'x' => 28.0, 'y' => 54.0, 'w' => 110.0, 'h' => 4.5, 'font_size' => 8],
        ['name' => 'contact_no', 'type' => 'text', 'page' => 1, 'x' => 38.0, 'y' => 60.0, 'w' => 50.0, 'h' => 4.5, 'font_size' => 8],
        ['name' => 'time_date_submitted', 'type' => 'text', 'page' => 1, 'x' => 145.0, 'y' => 48.0, 'w' => 55.0, 'h' => 4.5, 'font_size' => 8],
        ['name' => 'sampling_date', 'type' => 'text', 'page' => 1, 'x' => 145.0, 'y' => 54.0, 'w' => 55.0, 'h' => 4.5, 'font_size' => 8],
        ['name' => 'sampling_time', 'type' => 'text', 'page' => 1, 'x' => 145.0, 'y' => 60.0, 'w' => 55.0, 'h' => 4.5, 'font_size' => 8],
        ['name' => 'sample_collected_by', 'type' => 'text', 'page' => 1, 'x' => 50.0, 'y' => 66.0, 'w' => 90.0, 'h' => 4.5, 'font_size' => 8],

        // Ownership
        ['name' => 'ownership_private', 'type' => 'checkbox', 'page' => 1, 'x' => 48.0, 'y' => 72.5, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'ownership_commercial', 'type' => 'checkbox', 'page' => 1, 'x' => 72.0, 'y' => 72.5, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'ownership_public', 'type' => 'checkbox', 'page' => 1, 'x' => 105.0, 'y' => 72.5, 'w' => 3.5, 'h' => 3.5],

        // Classification
        ['name' => 'class_aqua', 'type' => 'checkbox', 'page' => 1, 'x' => 25.0, 'y' => 80.0, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'class_potability', 'type' => 'checkbox', 'page' => 1, 'x' => 48.0, 'y' => 80.0, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'class_wastewater', 'type' => 'checkbox', 'page' => 1, 'x' => 80.0, 'y' => 80.0, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'class_agriculture', 'type' => 'checkbox', 'page' => 1, 'x' => 120.0, 'y' => 80.0, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'class_academic', 'type' => 'checkbox', 'page' => 1, 'x' => 155.0, 'y' => 80.0, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'class_others', 'type' => 'checkbox', 'page' => 1, 'x' => 25.0, 'y' => 85.5, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'class_others_text', 'type' => 'text', 'page' => 1, 'x' => 48.0, 'y' => 85.0, 'w' => 60.0, 'h' => 4.0, 'font_size' => 7],

        // Field data
        ['name' => 'potability_sterile', 'type' => 'checkbox', 'page' => 1, 'x' => 55.0, 'y' => 128.0, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'sample_storage_temp', 'type' => 'text', 'page' => 1, 'x' => 145.0, 'y' => 128.0, 'w' => 40.0, 'h' => 4.0, 'font_size' => 7],
        ['name' => 'ww_local_district', 'type' => 'checkbox', 'page' => 1, 'x' => 55.0, 'y' => 135.0, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'ww_faucet', 'type' => 'checkbox', 'page' => 1, 'x' => 95.0, 'y' => 135.0, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'ww_tank', 'type' => 'checkbox', 'page' => 1, 'x' => 120.0, 'y' => 135.0, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'ww_deepwell', 'type' => 'checkbox', 'page' => 1, 'x' => 145.0, 'y' => 135.0, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'ww_others', 'type' => 'checkbox', 'page' => 1, 'x' => 175.0, 'y' => 135.0, 'w' => 3.5, 'h' => 3.5],
        ['name' => 'ww_others_text', 'type' => 'text', 'page' => 1, 'x' => 185.0, 'y' => 134.5, 'w' => 20.0, 'h' => 4.0, 'font_size' => 7],

        ['name' => 'other_tests', 'type' => 'text', 'page' => 1, 'x' => 40.0, 'y' => 218.0, 'w' => 160.0, 'h' => 4.5, 'font_size' => 7],
        ['name' => 'billing_total', 'type' => 'text', 'page' => 1, 'x' => 165.0, 'y' => 278.0, 'w' => 35.0, 'h' => 5.0, 'font_size' => 9, 'align' => 'R'],

        // Signature dates
        ['name' => 'conforme_date', 'type' => 'text', 'page' => 1, 'x' => 165.0, 'y' => 290.0, 'w' => 35.0, 'h' => 4.5, 'font_size' => 8],
        ['name' => 'received_date', 'type' => 'text', 'page' => 1, 'x' => 165.0, 'y' => 300.0, 'w' => 35.0, 'h' => 4.5, 'font_size' => 8],
        ['name' => 'reviewed_date', 'type' => 'text', 'page' => 1, 'x' => 165.0, 'y' => 310.0, 'w' => 35.0, 'h' => 4.5, 'font_size' => 8],
    ];

    for ($i = 1; $i <= 9; $i++) {
        $row = ($i - 1) % 7;
        $col = $i <= 7 ? 0 : 1;
        $baseY = 94.0 + ($row * 4.5);
        $codeX = $col === 0 ? 25.0 : 120.0;
        $ctrlX = $col === 0 ? 78.0 : 173.0;

        $fields[] = ['name' => "sample_code_{$i}", 'type' => 'text', 'page' => 1, 'x' => $codeX, 'y' => $baseY, 'w' => 50.0, 'h' => 4.0, 'font_size' => 7];
        $fields[] = ['name' => "control_number_{$i}", 'type' => 'text', 'page' => 1, 'x' => $ctrlX, 'y' => $baseY, 'w' => 30.0, 'h' => 4.0, 'font_size' => 7];
    }

    $checkboxGroups = [
        [['MB-01', 25.0, 148.0], ['MB-02', 25.0, 153.0], ['MB-03', 25.0, 158.0], ['MB-04', 25.0, 163.0], ['MB-05', 25.0, 168.0], ['MB-06', 25.0, 173.0], ['MB-07', 25.0, 178.0], ['MB-08', 25.0, 183.0], ['MB-09', 25.0, 188.0], ['MB-10', 25.0, 193.0]],
        [['PC-01', 110.0, 148.0], ['PC-02', 110.0, 152.5], ['PC-03', 110.0, 157.0], ['PC-04', 110.0, 161.5], ['PC-05', 110.0, 166.0], ['PC-06', 110.0, 170.5], ['PC-07', 110.0, 175.0], ['PC-08', 110.0, 179.5], ['PC-09', 110.0, 184.0], ['PC-10', 110.0, 188.5], ['PC-11', 110.0, 193.0], ['PC-12', 155.0, 148.0], ['PC-13', 155.0, 152.5], ['PC-14', 155.0, 157.0], ['PC-15', 155.0, 161.5], ['PC-16', 155.0, 166.0], ['PC-17', 155.0, 170.5], ['PC-18', 155.0, 175.0], ['PC-19', 155.0, 179.5], ['PC-20', 155.0, 184.0], ['PC-21', 155.0, 188.5], ['PC-22', 155.0, 193.0], ['PC-23', 155.0, 197.5], ['PC-24', 155.0, 202.0], ['PC-25', 155.0, 206.5], ['PC-26', 155.0, 211.0], ['PC-27', 175.0, 206.5], ['PC-28', 175.0, 211.0], ['PC-29', 175.0, 197.5], ['PC-30', 175.0, 202.0]],
        [['HM-01', 25.0, 205.0], ['HM-02', 40.0, 205.0], ['HM-03', 55.0, 205.0], ['HM-04', 70.0, 205.0], ['HM-05', 85.0, 205.0], ['HM-06', 100.0, 205.0], ['HM-07', 25.0, 210.0], ['HM-08', 40.0, 210.0], ['HM-09', 55.0, 210.0], ['HM-10', 70.0, 210.0], ['HM-11', 85.0, 210.0], ['HM-12', 100.0, 210.0], ['HM-13', 25.0, 214.5], ['HM-14', 40.0, 214.5], ['HM-15', 55.0, 214.5], ['HM-16', 70.0, 214.5]],
        [['LM-01', 145.0, 214.5], ['LM-02', 165.0, 214.5], ['LM-03', 185.0, 214.5]],
    ];

    foreach ($checkboxGroups as $group) {
        foreach ($group as $item) {
            $fields[] = [
                'name' => 'chk_'.strtolower(str_replace('-', '_', $item[0])),
                'type' => 'checkbox',
                'page' => 1,
                'x' => $item[1],
                'y' => $item[2],
                'w' => 3.5,
                'h' => 3.5,
            ];
        }
    }

    for ($i = 1; $i <= 14; $i++) {
        $row = ($i - 1) % 7;
        $col = $i <= 7 ? 0 : 1;
        $y = 235.0 + ($row * 5.5);
        $paramX = $col === 0 ? 25.0 : 115.0;
        $priceX = $col === 0 ? 70.0 : 160.0;
        $totalX = $col === 0 ? 90.0 : 180.0;

        $fields[] = ['name' => "bill_param_{$i}", 'type' => 'text', 'page' => 1, 'x' => $paramX, 'y' => $y, 'w' => 42.0, 'h' => 4.5, 'font_size' => 7];
        $fields[] = ['name' => "bill_price_{$i}", 'type' => 'text', 'page' => 1, 'x' => $priceX, 'y' => $y, 'w' => 18.0, 'h' => 4.5, 'font_size' => 7, 'align' => 'R'];
        $fields[] = ['name' => "bill_total_{$i}", 'type' => 'text', 'page' => 1, 'x' => $totalX, 'y' => $y, 'w' => 18.0, 'h' => 4.5, 'font_size' => 7, 'align' => 'R'];
    }

    return [
        'page' => $page,
        'fields' => $fields,
    ];
})();
