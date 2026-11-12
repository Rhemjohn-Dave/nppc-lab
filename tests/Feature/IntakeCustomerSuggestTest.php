<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntakeCustomerSuggestTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggest_returns_matching_customers(): void
    {
        $this->seed();

        Customer::query()->create([
            'customer_name' => 'Juan Dela Cruz',
            'customer_email' => 'juan.a@example.com',
            'customer_contact' => '09171110001',
            'company_name' => 'Farm A',
        ]);
        Customer::query()->create([
            'customer_name' => 'Juan Dela Cruz',
            'customer_email' => 'juan.b@example.com',
            'customer_contact' => '09172220002',
            'company_name' => 'Farm B',
        ]);
        Customer::query()->create([
            'customer_name' => 'Maria Santos',
            'customer_email' => 'maria@example.com',
            'customer_contact' => '09173330003',
        ]);

        $this->getJson('/intake/customers/suggest?q=Juan')
            ->assertOk()
            ->assertJsonCount(2, 'customers')
            ->assertJsonFragment(['customer_email' => 'juan.a@example.com'])
            ->assertJsonFragment(['customer_email' => 'juan.b@example.com']);
    }

    public function test_lookup_with_customer_id_prefills_chosen_customer(): void
    {
        $this->seed();

        $chosen = Customer::query()->create([
            'customer_name' => 'Juan Dela Cruz',
            'customer_email' => 'juan.chosen@example.com',
            'customer_contact' => '09171110001',
            'customer_address' => 'Bacolod City',
            'company_name' => 'Farm Chosen',
            'ownership_type' => 'Private',
        ]);
        Customer::query()->create([
            'customer_name' => 'Juan Dela Cruz',
            'customer_email' => 'juan.other@example.com',
            'customer_contact' => '09172220002',
        ]);

        $this->from('/intake')
            ->post('/intake/lookup', [
                'query' => 'Juan',
                'customer_id' => $chosen->id,
            ])
            ->assertRedirect(route('intake.create', [
                'customer_id' => $chosen->id,
            ]));

        $this->get(route('intake.create', ['customer_id' => $chosen->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('intake/wizard')
                ->where('prefill.customer_name', 'Juan Dela Cruz')
                ->where('prefill.customer_email', 'juan.chosen@example.com')
                ->where('prefill.customer_contact', '09171110001')
                ->where('prefill.customer_address', 'Bacolod City')
                ->where('prefill.company_name', 'Farm Chosen')
                ->where('prefill.ownership_type', 'Private'));
    }

    public function test_ambiguous_lookup_requires_suggestion_pick(): void
    {
        $this->seed();

        Customer::query()->create([
            'customer_name' => 'Juan Dela Cruz',
            'customer_email' => 'juan.a@example.com',
            'customer_contact' => '09171110001',
        ]);
        Customer::query()->create([
            'customer_name' => 'Juan Dela Cruz',
            'customer_email' => 'juan.b@example.com',
            'customer_contact' => '09172220002',
        ]);

        $this->from('/intake')
            ->post('/intake/lookup', ['query' => 'Juan'])
            ->assertRedirect('/intake')
            ->assertSessionHasErrors('query');
    }

    public function test_unique_lookup_still_redirects_without_customer_id(): void
    {
        $this->seed();

        Customer::query()->create([
            'customer_name' => 'Only One Match',
            'customer_email' => 'only@example.com',
            'customer_contact' => '09175550005',
        ]);

        $only = Customer::query()->where('customer_email', 'only@example.com')->firstOrFail();

        $response = $this->from('/intake')
            ->post('/intake/lookup', ['query' => 'Only One Match']);

        $response->assertRedirect(route('intake.create', [
            'customer_id' => $only->id,
        ]));

        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('customer_name=', $location);
        $this->assertStringNotContainsString('customer_email=', $location);
        $this->assertStringNotContainsString('customer_contact=', $location);
    }
}
