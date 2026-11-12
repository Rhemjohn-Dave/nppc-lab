<?php

use Illuminate\Database\Migrations\Migration;

/**
 * No-op. Kept so environments that already ran this batch stay consistent.
 *
 * Coastal FO2B plot deploy is only:
 *   database/migrations/2026_11_13_011000_deploy_coastal_fo2b_local_plot.php
 *
 * Do not re-import other FO forms from blueprints on the server.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty — FO2B plot is applied by 011000 only.
    }

    public function down(): void
    {
        //
    }
};
