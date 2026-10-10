<?php

namespace App\Support\Mail;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * Admin-managed SMTP settings stored in `settings` (key mail.smtp).
 * When enabled they override the .env mailer at runtime; otherwise .env stays in effect.
 */
class MailSettings
{
    public const KEY = 'mail.smtp';

    public const ENCRYPTIONS = ['tls', 'ssl', 'none'];

    /**
     * @return array{
     *     enabled: bool,
     *     host: string,
     *     port: int,
     *     encryption: string,
     *     username: string,
     *     password: string,
     *     from_address: string,
     *     from_name: string,
     *     last_test_status: string|null,
     *     last_test_at: string|null,
     *     last_test_error: string|null
     * }
     */
    public static function get(): array
    {
        $raw = Setting::getValue(self::KEY, []);

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'host' => (string) ($raw['host'] ?? ''),
            'port' => (int) ($raw['port'] ?? 587),
            'encryption' => in_array($raw['encryption'] ?? null, self::ENCRYPTIONS, true)
                ? (string) $raw['encryption']
                : 'tls',
            'username' => (string) ($raw['username'] ?? ''),
            'password' => self::decrypt($raw['password'] ?? null),
            'from_address' => (string) ($raw['from_address'] ?? ''),
            'from_name' => (string) ($raw['from_name'] ?? ''),
            'last_test_status' => isset($raw['last_test_status']) ? (string) $raw['last_test_status'] : null,
            'last_test_at' => isset($raw['last_test_at']) ? (string) $raw['last_test_at'] : null,
            'last_test_error' => isset($raw['last_test_error']) ? (string) $raw['last_test_error'] : null,
        ];
    }

    /**
     * Settings safe to send to the browser (password never included).
     *
     * @return array<string, mixed>
     */
    public static function forDisplay(): array
    {
        $settings = self::get();
        $hasPassword = $settings['password'] !== '';
        unset($settings['password']);

        return [
            ...$settings,
            'has_password' => $hasPassword,
            'configured' => self::isConfigured(),
        ];
    }

    /**
     * @param  array{
     *     enabled: bool,
     *     host?: string|null,
     *     port?: int|null,
     *     encryption?: string|null,
     *     username?: string|null,
     *     password?: string|null,
     *     from_address?: string|null,
     *     from_name?: string|null
     * }  $data  Blank password keeps the stored one.
     */
    public static function save(array $data): void
    {
        $current = Setting::getValue(self::KEY, []);

        $password = filled($data['password'] ?? null)
            ? Crypt::encryptString((string) $data['password'])
            : ($current['password'] ?? null);

        Setting::putValue(self::KEY, [
            ...$current,
            'enabled' => (bool) $data['enabled'],
            'host' => trim((string) ($data['host'] ?? '')),
            'port' => (int) ($data['port'] ?? 587),
            'encryption' => (string) ($data['encryption'] ?? 'tls'),
            'username' => trim((string) ($data['username'] ?? '')),
            'password' => $password,
            'from_address' => trim((string) ($data['from_address'] ?? '')),
            'from_name' => trim((string) ($data['from_name'] ?? '')),
        ]);
    }

    public static function recordTest(bool $ok, ?string $error = null): void
    {
        $current = Setting::getValue(self::KEY, []);

        Setting::putValue(self::KEY, [
            ...$current,
            'last_test_status' => $ok ? 'ok' : 'failed',
            'last_test_at' => now()->toIso8601String(),
            'last_test_error' => $ok ? null : mb_substr((string) $error, 0, 500),
        ]);
    }

    public static function isConfigured(): bool
    {
        $settings = self::get();

        return $settings['enabled']
            && $settings['host'] !== ''
            && $settings['from_address'] !== '';
    }

    /**
     * Apply DB settings over the .env mail config. Safe before migrations run.
     */
    public static function apply(): void
    {
        // Settings are cached forever, so this is a cache hit on normal requests;
        // the try/catch covers fresh installs where settings/cache tables do not exist yet.
        try {
            if (! self::isConfigured()) {
                return;
            }

            $settings = self::get();
        } catch (\Throwable) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.timeout' => 20,
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => $settings['port'],
            'mail.mailers.smtp.scheme' => $settings['encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => $settings['username'] !== '' ? $settings['username'] : null,
            'mail.mailers.smtp.password' => $settings['password'] !== '' ? $settings['password'] : null,
            'mail.from.address' => $settings['from_address'],
            'mail.from.name' => $settings['from_name'] !== ''
                ? $settings['from_name']
                : config('app.name'),
        ]);

        Mail::purge('smtp');
    }

    private static function decrypt(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return '';
        }
    }
}
