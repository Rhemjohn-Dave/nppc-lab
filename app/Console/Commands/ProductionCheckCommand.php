<?php

namespace App\Console\Commands;

use App\Services\DocxToPdfConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionCheckCommand extends Command
{
    protected $signature = 'nppc:production-check';

    protected $description = 'Verify go-live configuration for the NPPC Lab LMS.';

    public function handle(DocxToPdfConverter $docx): int
    {
        $failures = 0;

        $failures += $this->requireValue('APP_KEY', (string) config('app.key') !== '');
        $failures += $this->requireValue('APP_ENV=production', config('app.env') === 'production');
        $failures += $this->requireValue('APP_DEBUG=false', config('app.debug') === false);
        $failures += $this->requireValue(
            'APP_URL uses https',
            str_starts_with((string) config('app.url'), 'https://'),
        );
        $failures += $this->requireValue(
            'SESSION_SECURE_COOKIE=true',
            (bool) config('session.secure') === true,
        );
        $failures += $this->requireValue(
            'MAIL_MAILER is not log',
            config('mail.default') !== 'log',
        );
        $failures += $this->requireValue(
            'QUEUE_CONNECTION is not sync',
            config('queue.default') !== 'sync',
        );
        $failures += $this->requireValue(
            'storage/app/private is writable',
            is_dir(storage_path('app/private')) && is_writable(storage_path('app/private')),
        );
        $failures += $this->requireValue(
            'storage/logs is writable',
            is_dir(storage_path('logs')) && is_writable(storage_path('logs')),
        );

        try {
            DB::connection()->getPdo();
            $failures += $this->requireValue(
                'users table exists',
                Schema::hasTable('users'),
            );
        } catch (\Throwable $e) {
            $this->error('Database connection failed: '.$e->getMessage());
            $failures++;
        }

        if ($docx->isAvailable()) {
            $this->info('LibreOffice is available for DOCX uploads.');
        } else {
            $this->warn('LibreOffice is not installed. Upload PDFs for controlled forms (DOCX conversion will be rejected).');
        }

        if ($failures === 0) {
            $this->info('Production check passed. Change seed account passwords before staff log in.');

            return self::SUCCESS;
        }

        $this->error("Production check failed ({$failures} item(s)). See docs/DEPLOY.md.");

        return self::FAILURE;
    }

    private function requireValue(string $label, bool $ok): int
    {
        if ($ok) {
            $this->info("OK  {$label}");

            return 0;
        }

        $this->error("FAIL  {$label}");

        return 1;
    }
}
