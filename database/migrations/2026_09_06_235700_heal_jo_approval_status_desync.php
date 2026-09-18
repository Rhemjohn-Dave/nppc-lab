<?php

use App\Services\JobOrderService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        JobOrderService::healJoApprovalStatusDesync();
    }

    public function down(): void
    {
        // Irreversible data repair.
    }
};
