<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAppDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_register_and_forgot_password_offer_the_app(): void
    {
        foreach (['login', 'register', 'password.request'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee('id="pm-auth-app"', false)
                ->assertSee('id="pm-auth-app-install"', false)
                ->assertSee('js/pwa.js', false);
        }
    }

    public function test_store_buttons_only_show_once_their_links_are_set(): void
    {
        $this->get(route('login'))
            ->assertDontSee('Get it on Google Play')
            ->assertDontSee('Download on the App Store');

        config([
            'services.mobile_app.android_url' => 'https://play.google.com/store/apps/details?id=org.example.diary',
            'services.mobile_app.ios_url' => 'https://apps.apple.com/app/id123456789',
        ]);

        $this->get(route('login'))
            ->assertSee('Get it on Google Play')
            ->assertSee('href="https://play.google.com/store/apps/details?id=org.example.diary"', false)
            ->assertSee('Download on the App Store');
    }
}
