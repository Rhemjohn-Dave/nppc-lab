<?php

/**
 * Standard named AcroForm fields for official analysis result PDFs.
 *
 * Admins place these names in their fillable PDF. The app fills them by name
 * and does not depend on coordinates.
 *
 * Test slots follow the admin-defined order: test_1_* is the first selected
 * analysis type, test_2_* the second, and so on.
 *
 * @return array{
 *     shared: list<string>,
 *     sample_count: int,
 *     sample_fields: list<string>,
 *     test_fields: list<string>,
 *     required_test_fields: list<string>
 * }
 */
return [
    'shared' => [
        'reference_no',
        'customer_name',
        'company_name',
        'address',
        'contact_no',
        'classification',
        'sampling_date',
        'sampling_time',
        'sample_collected_by',
        'storage_temp',
        'field_data',
        'issued_date',
        'analyst_name',
        'reviewed_date',
    ],
    'sample_count' => 9,
    'sample_fields' => [
        'code',
        'description',
        'matrix',
    ],
    'test_fields' => [
        'name',
        'code',
        'result',
        'unit',
        'remarks',
        'analyst',
        'completed_at',
    ],
    'required_test_fields' => [
        'result',
    ],
];
