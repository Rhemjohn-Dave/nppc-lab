<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_guests_to_login(): void
    {
        $this->get(route('home'))->assertRedirect('/login');
    }

    public function test_intake_kiosk_is_public(): void
    {
        $this->get(route('intake.index'))->assertOk();
    }
}
