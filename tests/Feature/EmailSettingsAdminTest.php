<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Mail\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::where('email', 'admin@nppc.local')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'lab@example.com',
            'password' => 'app-password-123',
            'from_address' => 'lab@example.com',
            'from_name' => 'NPPC Lab',
        ], $overrides);
    }

    public function test_admin_can_view_email_settings_page(): void
    {
        $this->seed();

        $this->actingAs($this->admin())
            ->get('/admin/email-settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/email-settings')
                ->has('settings')
                ->missing('settings.password'));
    }

    public function test_non_admin_cannot_access_email_settings(): void
    {
        $this->seed();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $this->actingAs($receiving)->get('/admin/email-settings')->assertForbidden();
        $this->actingAs($receiving)->put('/admin/email-settings', $this->payload())->assertForbidden();
    }

    public function test_admin_saves_smtp_settings_with_encrypted_password(): void
    {
        $this->seed();

        $this->actingAs($this->admin())
            ->put('/admin/email-settings', $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $raw = Setting::getValue(MailSettings::KEY, []);
        $this->assertNotSame('app-password-123', $raw['password']);
        $this->assertSame('app-password-123', MailSettings::get()['password']);
        $this->assertTrue(MailSettings::isConfigured());
    }

    public function test_blank_password_keeps_existing_password(): void
    {
        $this->seed();

        $this->actingAs($this->admin())->put('/admin/email-settings', $this->payload());
        $this->actingAs($this->admin())
            ->put('/admin/email-settings', $this->payload(['password' => '', 'host' => 'smtp2.example.com']))
            ->assertSessionHasNoErrors();

        $settings = MailSettings::get();
        $this->assertSame('smtp2.example.com', $settings['host']);
        $this->assertSame('app-password-123', $settings['password']);
    }

    public function test_enabled_settings_require_host_and_from_address(): void
    {
        $this->seed();

        $this->actingAs($this->admin())
            ->put('/admin/email-settings', $this->payload(['host' => '', 'from_address' => '']))
            ->assertSessionHasErrors(['host', 'from_address']);
    }

    public function test_apply_overrides_runtime_mail_config(): void
    {
        $this->seed();
        MailSettings::save($this->payload(['encryption' => 'ssl', 'port' => 465]));

        MailSettings::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('lab@example.com', config('mail.from.address'));
    }

    public function test_send_test_email_is_logged(): void
    {
        $this->seed();
        Mail::fake();
        MailSettings::save($this->payload());

        $this->actingAs($this->admin())
            ->post('/admin/email-settings/test', ['to' => 'me@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $log = EmailLog::query()->where('type', EmailLog::TYPE_TEST)->firstOrFail();
        $this->assertSame(EmailLog::STATUS_SENT, $log->status);
        $this->assertSame('ok', MailSettings::get()['last_test_status']);
    }
}
