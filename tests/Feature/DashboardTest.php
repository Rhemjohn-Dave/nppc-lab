<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_without_lab_role_get_generic_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('role', 'generic')
                ->has('header.title')
                ->has('kpis')
                ->has('needsAttention')
                ->has('queue')
                ->has('activity')
                ->has('links')
                ->has('links.primary')
                ->has('header.greeting_name'));
    }

    public function test_admin_dashboard_payload(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('role', 'admin')
                ->where('header.title', 'Laboratory Administration')
                ->has('kpis', 4)
                ->where('kpis.0.key', 'active_forms')
                ->where('kpis.1.key', 'pending_revisions')
                ->where('kpis.2.key', 'draft_revisions')
                ->where('kpis.3.key', 'audit_events_7d')
                ->has('extras.workflow_strip')
                ->has('queue.rows')
                ->has('activity.items'));
    }

    public function test_receiving_dashboard_payload(): void
    {
        $this->seed();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $this->actingAs($receiving)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('role', 'receiving')
                ->where('header.title', 'Receiving Workspace')
                ->has('kpis', 4)
                ->where('kpis.0.key', 'needs_pricing')
                ->where('kpis.1.key', 'awaiting_head')
                ->where('kpis.2.key', 'ready_for_analysts')
                ->where('kpis.3.key', 'reviewed')
                ->where('kpis.0.href', '/receiving?status=draft_submitted')
                ->where('kpis.1.href', '/receiving?status=pending_jo_approval')
                ->where('kpis.2.href', '/receiving?status=jo_approved')
                ->where('kpis.3.href', '/receiving?status=reviewed'));
    }

    public function test_analyst_dashboard_payload(): void
    {
        $this->seed();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->actingAs($analyst)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('role', 'analyst')
                ->where('header.title', 'Analyst Workspace')
                ->has('kpis', 4)
                ->where('kpis.0.key', 'needs_action')
                ->where('kpis.1.key', 'in_progress')
                ->where('kpis.2.key', 'returned')
                ->where('kpis.3.key', 'completed_today')
                ->has('extras.job_groups'));
    }

    public function test_head_dashboard_payload(): void
    {
        $this->seed();
        $head = User::where('email', 'head@nppc.local')->firstOrFail();

        $this->actingAs($head)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('role', 'head')
                ->where('header.title', 'Head Analysis')
                ->has('kpis', 4)
                ->where('kpis.0.key', 'waiting_review')
                ->where('kpis.1.key', 'ready_to_sign')
                ->where('kpis.2.key', 'returned_in_lab')
                ->where('kpis.3.key', 'signed_today'));
    }
}
