<?php

/**
 * Whitelisted data sources for controlled-form field mapping.
 * Keys are the only values the UI may submit. Resolvers never run raw SQL.
 *
 * @return array{
 *     groups: list<array{label: string, categories: list<string>, sources: list<array{
 *         key: string,
 *         label: string,
 *         type: string,
 *         hint?: string
 *     }>>
 * }
 */
return [
    'groups' => [
        [
            'label' => 'Result sheet header',
            'categories' => ['analysis_result'],
            'sources' => [
                ['key' => 'results.customer', 'label' => 'Customer', 'type' => 'text'],
                ['key' => 'results.address', 'label' => 'Address', 'type' => 'text'],
                ['key' => 'results.ref_no', 'label' => 'Ref. No.', 'type' => 'text', 'hint' => 'Job order / LSO reference number.'],
                ['key' => 'results.control_no', 'label' => 'Control No. (RFA)', 'type' => 'text', 'hint' => 'Same control number printed on the Request for Analysis sample table.'],
                ['key' => 'results.collected_by', 'label' => 'Sample Collected by', 'type' => 'text'],
                [
                    'key' => 'results.water_supply',
                    'label' => 'Water Supply',
                    'type' => 'text',
                    'hint' => 'Job order sample source (Local water district, Tank, Faucet, Deepwell, or Others specified).',
                ],
                [
                    'key' => 'results.sampling_point',
                    'label' => 'Sampling Point',
                    'type' => 'text',
                    'hint' => 'Same as job order sample source (e.g. Faucet).',
                ],
                [
                    'key' => 'results.classification',
                    'label' => 'Sample Classification',
                    'type' => 'text',
                    'hint' => 'Intake classification, including Others specified text.',
                ],
                [
                    'key' => 'results.collection_datetime',
                    'label' => 'Date/Time of Collection',
                    'type' => 'date',
                    'hint' => 'RFA Date & Time of Sampling.',
                ],
                [
                    'key' => 'results.sample_received_at',
                    'label' => 'Date & Time Sample Received',
                    'type' => 'date',
                    'hint' => 'Kiosk submit date and time. Printed as July 29, 2026 (3:00PM).',
                ],
                [
                    'key' => 'results.receipt_at',
                    'label' => 'Receipt',
                    'type' => 'date',
                    'hint' => 'When Receiving marks the job as received. Not the kiosk submit time.',
                ],
                [
                    'key' => 'results.sample_description',
                    'label' => 'Sample Description',
                    'type' => 'text',
                    'hint' => 'Prints “Water in sterile bottle” when that Field Data (Potability) option is selected on the RFA. Not the RFA Sample Code/Description.',
                ],
                [
                    'key' => 'results.sample_code',
                    'label' => 'Sample Code (RFA)',
                    'type' => 'text',
                    'hint' => 'RFA Sample Code only. If blank, falls back to the sample description. Does not join code and description.',
                ],
                [
                    'key' => 'results.sampling_datetime',
                    'label' => 'Date & Time of Sampling',
                    'type' => 'date',
                    'hint' => 'From intake sampling date and time. Printed as July 29, 2026 (3:00PM).',
                ],
                [
                    'key' => 'results.examination_datetime',
                    'label' => 'Examination',
                    'type' => 'date',
                    'hint' => 'When the analyst encodes/completes the first result on this sheet.',
                ],
                [
                    'key' => 'results.analysis_datetime',
                    'label' => 'Date & Time of Analysis',
                    'type' => 'date',
                    'hint' => 'When the analyst completed the first result on this sheet.',
                ],
                [
                    'key' => 'results.report_date',
                    'label' => 'Report',
                    'type' => 'date',
                    'hint' => 'Head signature date after the designated analyst sends the job. Blank until signed.',
                ],
                [
                    'key' => 'results.release_date',
                    'label' => 'Release',
                    'type' => 'date',
                    'hint' => 'Head signature date. Same as Report. Blank until signed.',
                ],
            ],
        ],
        [
            'label' => 'Job Order',
            'categories' => ['job_order', 'analysis_result', 'other'],
            'sources' => [
                ['key' => 'job_orders.reference_no', 'label' => 'Reference Number', 'type' => 'text'],
                ['key' => 'job_orders.customer_name', 'label' => 'Customer Name', 'type' => 'text'],
                ['key' => 'job_orders.customer_address', 'label' => 'Customer Address', 'type' => 'text'],
                ['key' => 'job_orders.customer_contact', 'label' => 'Contact Number', 'type' => 'text'],
                ['key' => 'job_orders.customer_email', 'label' => 'Customer Email', 'type' => 'text'],
                ['key' => 'job_orders.company_name', 'label' => 'Company Name', 'type' => 'text'],
                ['key' => 'job_orders.sampling_date', 'label' => 'Sampling Date', 'type' => 'date'],
                ['key' => 'job_orders.sampling_time', 'label' => 'Sampling Time', 'type' => 'text'],
                ['key' => 'job_orders.sample_collected_by', 'label' => 'Sample Collected By', 'type' => 'text'],
                ['key' => 'job_orders.classification', 'label' => 'Sample Classification', 'type' => 'text'],
                ['key' => 'job_orders.ownership_type', 'label' => 'Ownership Type', 'type' => 'text'],
                ['key' => 'job_orders.field_data', 'label' => 'Field Data', 'type' => 'multiline'],
                ['key' => 'job_orders.sample_storage_temp', 'label' => 'Sample Storage Temperature', 'type' => 'text'],
                ['key' => 'job_orders.wastewater_source', 'label' => 'Water Supply / Sample Source', 'type' => 'text'],
                ['key' => 'job_orders.sampling_point', 'label' => 'Sampling Point', 'type' => 'text'],
                ['key' => 'job_orders.other_tests', 'label' => 'Other Tests', 'type' => 'text'],
                ['key' => 'job_orders.total_cost', 'label' => 'Total Cost', 'type' => 'currency'],
                ['key' => 'job_orders.created_at', 'label' => 'Kiosk submitted date/time', 'type' => 'date'],
                ['key' => 'job_orders.received_at', 'label' => 'Lab received date (Receiving desk)', 'type' => 'date'],
                ['key' => 'job_orders.reviewed_at', 'label' => 'Reviewed Date', 'type' => 'date'],
                ['key' => 'job_orders.received_by_name', 'label' => 'Received By', 'type' => 'signature'],
                ['key' => 'job_orders.reviewed_by_name', 'label' => 'Reviewed By', 'type' => 'signature'],
            ],
        ],
        [
            'label' => 'Classification checkboxes',
            'categories' => ['job_order'],
            'sources' => [
                ['key' => 'job_orders.classification:aqua', 'label' => 'Classification: Aqua', 'type' => 'checkbox'],
                ['key' => 'job_orders.classification:potability', 'label' => 'Classification: Potability', 'type' => 'checkbox'],
                ['key' => 'job_orders.classification:wastewater', 'label' => 'Classification: Wastewater', 'type' => 'checkbox'],
                ['key' => 'job_orders.classification:agriculture', 'label' => 'Classification: Agriculture', 'type' => 'checkbox'],
                ['key' => 'job_orders.classification:academic', 'label' => 'Classification: Academic', 'type' => 'checkbox'],
                ['key' => 'job_orders.classification:other', 'label' => 'Classification: Others', 'type' => 'checkbox'],
                ['key' => 'job_orders.ownership_type:private', 'label' => 'Ownership: Private', 'type' => 'checkbox'],
                ['key' => 'job_orders.ownership_type:commercial', 'label' => 'Ownership: Commercial', 'type' => 'checkbox'],
                ['key' => 'job_orders.ownership_type:public', 'label' => 'Ownership: Public', 'type' => 'checkbox'],
                ['key' => 'job_orders.wastewater_source:district', 'label' => 'Source: Local district', 'type' => 'checkbox'],
                ['key' => 'job_orders.wastewater_source:faucet', 'label' => 'Source: Faucet', 'type' => 'checkbox'],
                ['key' => 'job_orders.wastewater_source:tank', 'label' => 'Source: Tank', 'type' => 'checkbox'],
                ['key' => 'job_orders.wastewater_source:deepwell', 'label' => 'Source: Deep well', 'type' => 'checkbox'],
                ['key' => 'job_orders.field_data:sterile_bottle', 'label' => 'Potability: sterile bottle', 'type' => 'checkbox'],
            ],
        ],
        [
            'label' => 'Samples',
            'categories' => ['job_order', 'analysis_result'],
            'sources' => [
                ['key' => 'samples[]', 'label' => 'Samples table', 'type' => 'table', 'hint' => 'Repeating rows: sample_code, description, matrix, quantity, unit, remarks'],
                ['key' => 'samples.sample_code', 'label' => 'First sample code', 'type' => 'text'],
                ['key' => 'samples.description', 'label' => 'First sample description', 'type' => 'text'],
            ],
        ],
        [
            'label' => 'Analyses / tests',
            'categories' => ['job_order', 'analysis_result'],
            'sources' => [
                ['key' => 'analyses[]', 'label' => 'Analyses table', 'type' => 'table', 'hint' => 'Repeating rows: name, category, unit_price, total_cost, result_value, result_unit'],
                ['key' => 'analyses.selected:{code}', 'label' => 'Test selected (use checkbox_true_value = analysis code)', 'type' => 'checkbox'],
                ['key' => 'results.issued_date', 'label' => 'Issued date', 'type' => 'date'],
                ['key' => 'results.analyst_name', 'label' => 'Analyst name', 'type' => 'signature'],
            ],
        ],
    ],
];
