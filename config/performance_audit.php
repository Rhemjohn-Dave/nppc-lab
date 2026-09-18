<?php

/**
 * Performance budgets for nppc:performance-audit and SystemPerformanceAuditTest.
 *
 * Thresholds are tuned for seeded SQLite (:memory:) in CI. Production MySQL may differ;
 * use the artisan command on staging to compare relative regressions.
 *
 * Override any budget with env, e.g. PERFORMANCE_AUDIT_DASHBOARD_ADMIN_MAX_MS=800
 */
return [

    'duplicate_query_threshold' => (int) env('PERFORMANCE_AUDIT_DUPLICATE_QUERY_THRESHOLD', 4),

    'budgets' => [
        'dashboard.admin' => [
            'max_ms' => (int) env('PERFORMANCE_AUDIT_DASHBOARD_ADMIN_MAX_MS', 600),
            'max_queries' => (int) env('PERFORMANCE_AUDIT_DASHBOARD_ADMIN_MAX_QUERIES', 50),
            'max_payload_kb' => (int) env('PERFORMANCE_AUDIT_DASHBOARD_ADMIN_MAX_PAYLOAD_KB', 150),
        ],
        'dashboard.receiving' => [
            'max_ms' => (int) env('PERFORMANCE_AUDIT_DASHBOARD_RECEIVING_MAX_MS', 600),
            'max_queries' => (int) env('PERFORMANCE_AUDIT_DASHBOARD_RECEIVING_MAX_QUERIES', 45),
            'max_payload_kb' => (int) env('PERFORMANCE_AUDIT_DASHBOARD_RECEIVING_MAX_PAYLOAD_KB', 120),
        ],
        'dashboard.analyst' => [
            'max_ms' => (int) env('PERFORMANCE_AUDIT_DASHBOARD_ANALYST_MAX_MS', 600),
            'max_queries' => (int) env('PERFORMANCE_AUDIT_DASHBOARD_ANALYST_MAX_QUERIES', 45),
            'max_payload_kb' => (int) env('PERFORMANCE_AUDIT_DASHBOARD_ANALYST_MAX_PAYLOAD_KB', 120),
        ],
        'dashboard.head' => [
            'max_ms' => (int) env('PERFORMANCE_AUDIT_DASHBOARD_HEAD_MAX_MS', 600),
            'max_queries' => (int) env('PERFORMANCE_AUDIT_DASHBOARD_HEAD_MAX_QUERIES', 45),
            'max_payload_kb' => (int) env('PERFORMANCE_AUDIT_DASHBOARD_HEAD_MAX_PAYLOAD_KB', 120),
        ],
        'http.dashboard' => [
            'max_ms' => (int) env('PERFORMANCE_AUDIT_HTTP_DASHBOARD_MAX_MS', 900),
            'max_queries' => (int) env('PERFORMANCE_AUDIT_HTTP_DASHBOARD_MAX_QUERIES', 55),
        ],
        'http.receiving.index' => [
            'max_ms' => (int) env('PERFORMANCE_AUDIT_HTTP_RECEIVING_MAX_MS', 900),
            'max_queries' => (int) env('PERFORMANCE_AUDIT_HTTP_RECEIVING_MAX_QUERIES', 40),
        ],
        'http.analyst.index' => [
            'max_ms' => (int) env('PERFORMANCE_AUDIT_HTTP_ANALYST_MAX_MS', 900),
            'max_queries' => (int) env('PERFORMANCE_AUDIT_HTTP_ANALYST_MAX_QUERIES', 45),
        ],
        'http.head.index' => [
            'max_ms' => (int) env('PERFORMANCE_AUDIT_HTTP_HEAD_MAX_MS', 900),
            'max_queries' => (int) env('PERFORMANCE_AUDIT_HTTP_HEAD_MAX_QUERIES', 45),
        ],
        'service.field_value_resolver.sample' => [
            'max_ms' => (int) env('PERFORMANCE_AUDIT_RESOLVER_MAX_MS', 150),
            'max_queries' => (int) env('PERFORMANCE_AUDIT_RESOLVER_MAX_QUERIES', 25),
        ],
        'service.controlled_form.preview' => [
            'max_ms' => (int) env('PERFORMANCE_AUDIT_PDF_PREVIEW_MAX_MS', 4000),
            'max_queries' => (int) env('PERFORMANCE_AUDIT_PDF_PREVIEW_MAX_QUERIES', 35),
        ],
        'service.dynamic_matrix.preview' => [
            'max_ms' => (int) env('PERFORMANCE_AUDIT_MATRIX_PREVIEW_MAX_MS', 5000),
            'max_queries' => (int) env('PERFORMANCE_AUDIT_MATRIX_PREVIEW_MAX_QUERIES', 40),
        ],
    ],
];
