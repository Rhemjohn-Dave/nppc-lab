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
                    'key' => 'results.test_requested',
                    'label' => 'Test requested',
                    'type' => 'text',
                    'hint' => 'Name(s) of tests on this result sheet (from the bound package / selected members).',
                ],
                [
                    'key' => 'results.test_methods_references',
                    'label' => 'Test Methods and References',
                    'type' => 'text',
                    'hint' => 'Multiline block under the results table. One line per selected test: name — method. Method from analyst result_method, else Procedures (analysis_types.method), else catalog. Waived/unchecked tests omitted.',
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
                    'key' => 'results.specimen',
                    'label' => 'Specimen',
                    'type' => 'text',
                    'hint' => 'From intake when Food Products / Proximate (or related food) tests are selected.',
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
                ['key' => 'job_orders.sampling_site', 'label' => 'Sampling Site', 'type' => 'text'],
                ['key' => 'job_orders.specimen', 'label' => 'Specimen', 'type' => 'text', 'hint' => 'Food / Proximate specimen from intake.'],
                ['key' => 'job_orders.payment_mode', 'label' => 'Payment Mode', 'type' => 'text'],
                ['key' => 'job_orders.payment_terms', 'label' => 'Payment Terms', 'type' => 'text'],
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
                ['key' => 'job_orders.jo_approved_at', 'label' => 'JO approved date (Head)', 'type' => 'date'],
                ['key' => 'job_orders.reviewed_at', 'label' => 'Results released date', 'type' => 'date'],
                ['key' => 'job_orders.received_by_name', 'label' => 'Received By', 'type' => 'signature'],
                ['key' => 'job_orders.reviewed_by_name', 'label' => 'Reviewed By', 'type' => 'signature'],
                ['key' => 'conforme_name', 'label' => 'Conforme name (customer)', 'type' => 'text'],
                ['key' => 'conforme_date', 'label' => 'Conforme date (submitted)', 'type' => 'date'],
                ['key' => 'received_date', 'label' => 'Received by date', 'type' => 'date'],
                ['key' => 'reviewed_date', 'label' => 'Reviewed by date (JO approval)', 'type' => 'date'],
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
                ['key' => 'job_orders.wastewater_source:sea', 'label' => 'Aqua source: Sea Water', 'type' => 'checkbox'],
                ['key' => 'job_orders.wastewater_source:brackish', 'label' => 'Aqua source: Brackish Water', 'type' => 'checkbox'],
                ['key' => 'job_orders.wastewater_source:river', 'label' => 'Aqua source: River Water', 'type' => 'checkbox'],
                ['key' => 'job_orders.field_data:sterile_bottle', 'label' => 'Potability: sterile bottle', 'type' => 'checkbox'],
                ['key' => 'job_orders.payment_mode:cash', 'label' => 'Payment: Cash', 'type' => 'checkbox'],
                ['key' => 'job_orders.payment_mode:billing_partial', 'label' => 'Payment: Billing/Partial', 'type' => 'checkbox'],
                ['key' => 'job_orders.payment_mode:check', 'label' => 'Payment: Check', 'type' => 'checkbox'],
                ['key' => 'job_orders.payment_terms:15_days', 'label' => 'Payment terms: 15 days', 'type' => 'checkbox'],
                ['key' => 'job_orders.payment_terms:30_days', 'label' => 'Payment terms: 30 days', 'type' => 'checkbox'],
            ],
        ],
        [
            'label' => 'Samples',
            'categories' => ['job_order', 'analysis_result'],
            'sources' => array_values(array_merge(
                [
                    ['key' => 'samples[]', 'label' => 'Samples table', 'type' => 'table', 'hint' => 'Repeating rows: sample_code, description, control_number, matrix, quantity, unit, remarks. Control numbers use A/B suffixes when the job has 2+ samples.'],
                    ['key' => 'samples.sample_code', 'label' => 'First sample code', 'type' => 'text'],
                    ['key' => 'samples.description', 'label' => 'First sample description', 'type' => 'text'],
                ],
                array_merge(
                    ...array_map(static function (int $i): array {
                        $column = $i <= 8 ? 'left' : 'right';

                        return [
                            [
                                'key' => "sample_code_{$i}",
                                'label' => "RFA sample line {$i} (code + description)",
                                'type' => 'text',
                                'hint' => "Dual-column Job Order sample grid ({$column}; lines 1–8 left, 9–16 right).",
                            ],
                            [
                                'key' => "control_number_{$i}",
                                'label' => "RFA control number line {$i}",
                                'type' => 'text',
                                'hint' => 'Same job reference; adds A/B/C… when the job has 2+ samples. Lines 1–8 left, 9–16 right.',
                            ],
                        ];
                    }, range(1, 16))
                ),
            )),
        ],
        [
            'label' => 'Analyses / tests',
            'categories' => ['job_order', 'analysis_result'],
            'sources' => array_values(array_merge(
                [
                    ['key' => 'analyses[]', 'label' => 'Analyses table', 'type' => 'table', 'hint' => 'Repeating rows: name, category, unit_price, total_cost, result_value, result_unit. Prefer bill_* line slots for dual-column RFA billing grids.'],
                    ['key' => 'billing_total', 'label' => 'Billing total (RFA, left)', 'type' => 'currency', 'hint' => 'Job total under the left Parameters column when ≤10 billing lines.'],
                    ['key' => 'billing_total_right', 'label' => 'Billing total (RFA, right)', 'type' => 'currency', 'hint' => 'Job total under the right Parameters column when billing lines spill past 10 (slots 11–20).'],
                    ['key' => 'analyses.selected:{code}', 'label' => 'Test selected (use checkbox_true_value = analysis code)', 'type' => 'checkbox'],
                    ['key' => 'results.issued_date', 'label' => 'Issued date', 'type' => 'date'],
                    ['key' => 'results.analyst_name', 'label' => 'Analyst name (slot 1)', 'type' => 'signature', 'hint' => 'Confirmed at export — name only. Map PRC separately via Analyst PRC. FO2: Reviewed by.'],
                    ['key' => 'results.analyst_name_2', 'label' => 'Analyst name (slot 2)', 'type' => 'signature', 'hint' => 'Second analyst when the form is configured for 2+ signatories. FO2: Noted By.'],
                    ['key' => 'results.analyst_name_3', 'label' => 'Analyst name (slot 3)', 'type' => 'signature', 'hint' => 'Third signatory (FO2 Certified Correct 1).'],
                    ['key' => 'results.analyst_name_4', 'label' => 'Analyst name (slot 4)', 'type' => 'signature', 'hint' => 'Fourth signatory (FO2 Certified Correct 2).'],
                    ['key' => 'results.analyst_prc', 'label' => 'Analyst PRC (slot 1)', 'type' => 'text', 'hint' => 'PRC ID only (not appended to the name field).'],
                    ['key' => 'results.analyst_prc_2', 'label' => 'Analyst PRC (slot 2)', 'type' => 'text'],
                    ['key' => 'results.analyst_prc_3', 'label' => 'Analyst PRC (slot 3)', 'type' => 'text'],
                    ['key' => 'results.analyst_prc_4', 'label' => 'Analyst PRC (slot 4)', 'type' => 'text'],
                ],
                array_merge(
                    ...array_map(static function (int $i): array {
                        $side = $i <= 10 ? 'left' : 'right';

                        return [
                            [
                                'key' => "bill_param_{$i}",
                                'label' => "RFA billing parameter line {$i} ({$side})",
                                'type' => 'text',
                                'hint' => 'Dual-column Parameters grid: lines 1–10 left, 11–20 right. Filled after Receiving prices the job.',
                            ],
                            [
                                'key' => "bill_price_{$i}",
                                'label' => "RFA billing price/test line {$i} ({$side})",
                                'type' => 'currency',
                                'hint' => 'Unit price for billing line '.$i.'.',
                            ],
                            [
                                'key' => "bill_total_{$i}",
                                'label' => "RFA billing total-cost line {$i} ({$side})",
                                'type' => 'currency',
                                'hint' => 'Line total (qty × price) for billing line '.$i.'.',
                            ],
                        ];
                    }, range(1, 20))
                ),
            )),
        ],
    ],
];
