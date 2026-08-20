<?php

namespace App\Console\Commands;

use App\Models\AnalysisType;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetOperationalDataCommand extends Command
{
    protected $signature = 'nppc:reset-operational-data {--force : Skip confirmation}';

    protected $description = 'Delete job orders and generated documents, keeping users, controlled forms, catalog, packages, and customers.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete all job orders, samples, results, and generated PDFs? Users, forms, and customers will be kept.')) {
            return self::SUCCESS;
        }

        $this->snapshotCustomers();
        $this->deleteOperationalRows();
        $this->ensureSecondAnalyst();

        $this->info('Operational data cleared. Users, controlled forms, catalog, packages, and customers were kept.');
        $this->line('Second analyst: analyst2@nppc.local / password');

        return self::SUCCESS;
    }

    private function snapshotCustomers(): void
    {
        if (! Schema::hasTable('customers') || ! Schema::hasTable('job_orders')) {
            return;
        }

        $saved = 0;

        JobOrder::query()
            ->orderBy('id')
            ->each(function (JobOrder $job) use (&$saved) {
                if (trim((string) $job->customer_name) === '') {
                    return;
                }

                Customer::rememberFromIntake([
                    'customer_name' => $job->customer_name,
                    'customer_email' => $job->customer_email,
                    'customer_contact' => $job->customer_contact,
                    'customer_address' => $job->customer_address,
                    'company_name' => $job->company_name,
                    'ownership_type' => $job->ownership_type,
                ]);
                $saved++;
            });

        $this->info("Customer records kept: {$saved} job(s) merged into ".Customer::query()->count().' unique customer(s).');
    }

    private function deleteOperationalRows(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'print_logs',
            'document_approvals',
            'document_audit_logs',
            'generated_documents',
            'notifications',
            'job_order_packages',
            'job_order_analyses',
            'samples',
            'job_orders',
            'jobs',
            'job_batches',
            'failed_jobs',
            'cache',
            'cache_locks',
            'document_number_counters',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if (Schema::hasTable('reference_counters')) {
            DB::table('reference_counters')->update(['last_number' => 0]);
        }

        Schema::enableForeignKeyConstraints();
    }

    private function ensureSecondAnalyst(): void
    {
        $analyst = User::query()->firstOrCreate(
            ['email' => 'analyst2@nppc.local'],
            [
                'name' => 'Lab Analyst 2',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
        $analyst->syncRoles(['analyst']);

        $ids = AnalysisType::query()->pluck('id');
        if ($ids->isNotEmpty()) {
            $analyst->analysisTypes()->sync($ids);
        }
    }
}
