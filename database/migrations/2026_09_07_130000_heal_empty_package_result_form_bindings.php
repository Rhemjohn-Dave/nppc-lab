<?php

use App\Services\ControlledFormService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ControlledFormService::healEmptyPackageResultBindings();
    }

    public function down(): void
    {
        // Irreversible data repair.
    }
};
