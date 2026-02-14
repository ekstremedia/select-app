<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    public function test_welcome_page_returns_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_welcome_page_contains_app_mount_point(): void
    {
        $response = $this->get('/');

        $response->assertSee('<div id="app"></div>', false);
    }

    public function test_welcome_page_loads_vite_assets(): void
    {
        $response = $this->get('/');

        $response->assertSee('app.js', false);
        $response->assertSee('app.css', false);
    }

    public function test_welcome_page_has_meta_description(): void
    {
        $response = $this->get('/');

        $response->assertSee('the classic acronym sentence game', false);
    }

    public function test_welcome_page_loads_instrument_sans_font(): void
    {
        $response = $this->get('/');

        $response->assertSee('fonts.bunny.net', false);
        $response->assertSee('instrument-sans', false);
    }

    public function test_welcome_page_has_correct_title(): void
    {
        $response = $this->get('/');

        $response->assertSee('<title>', false);
    }
}
