<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\Customer;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingCustomerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_and_admin_can_correct_the_directory_without_changing_the_job(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PX-01')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Coastal Farms',
            'customer_email' => 'coastal@example.com',
            'customer_contact' => '09170001111',
            'company_name' => 'Coastal Farms Inc',
            'samples' => [
                ['description' => 'Leaves', 'matrix' => 'Solid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $customer = Customer::query()->where('customer_email', 'coastal@example.com')->firstOrFail();

        $this->actingAs($receiving)
            ->get('/customers?q=Coastal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('customers/index')
                ->where('customers.data.0.customer_name', 'Coastal Farms'));

        $this->actingAs($receiving)
            ->from('/customers?q=Coastal')
            ->patch("/customers/{$customer->id}", [
                'customer_name' => 'Coastal Growers',
                'company_name' => 'Coastal Growers Inc',
                'customer_email' => 'coastal@example.com',
                'customer_contact' => '09170001111',
                'customer_address' => 'Bacolod',
                'ownership_type' => 'Commercial',
            ])
            ->assertRedirect('/customers?q=Coastal')
            ->assertSessionDoesntHaveErrors();

        $customer->refresh();
        $job->refresh();
        $this->assertSame('Coastal Growers', $customer->customer_name);
        $this->assertSame('Coastal Growers Inc', $customer->company_name);
        $this->assertSame('Bacolod', $customer->customer_address);
        $this->assertSame('Commercial', $customer->ownership_type);
        $this->assertSame('Coastal Farms', $job->customer_name);
        $this->assertSame('Coastal Farms Inc', $job->company_name);

        $this->getJson('/intake/customers/suggest?q=Coastal%20Growers')
            ->assertOk()
            ->assertJsonFragment(['customer_name' => 'Coastal Growers']);

        $this->actingAs($admin)
            ->patch("/customers/{$customer->id}", [
                'customer_name' => 'Coastal Admin',
                'customer_email' => 'coastal@example.com',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('Coastal Admin', $customer->fresh()->customer_name);
        $this->assertSame('Coastal Growers Inc', $customer->fresh()->company_name);
        $this->assertSame('Coastal Farms', $job->fresh()->customer_name);

        $this->actingAs($analyst)
            ->patch("/customers/{$customer->id}", [
                'customer_name' => 'Should not save',
            ])
            ->assertForbidden();

        $this->assertSame('Coastal Admin', $customer->fresh()->customer_name);
    }
}
