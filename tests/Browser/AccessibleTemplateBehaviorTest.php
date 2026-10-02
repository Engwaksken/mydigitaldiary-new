<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibleTemplateBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_pwa_theme_color_accepts_hex_values_and_rejects_invalid_values(): void
    {
        $user = User::factory()->create([
            'theme_color' => '#abc',
            'theme_color_secondary' => '#112233',
        ]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('<meta name="theme-color" content="#abc">', false)
            ->assertSee('<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#', false);

        $user->forceFill(['theme_color' => 'red', 'theme_color_secondary' => 'not-a-color'])->save();

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('<meta name="theme-color" content="#00897B">', false)
            ->assertSee('<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#006B60">', false);
    }

    public function test_login_fields_render_nonempty_unique_ids_and_matching_labels(): void
    {
        $content = $this->get(route('login'))->assertOk()->getContent();

        preg_match_all('/<input\b[^>]*\bid="([^"]+)"[^>]*>/i', $content, $inputs);
        $ids = $inputs[1];
        $this->assertNotEmpty($ids);
        $this->assertSame(count($ids), count(array_unique($ids)), 'Input IDs must be unique on the page.');
        $this->assertStringNotContainsString('id=""', $content);

        foreach ($ids as $id) {
            $this->assertStringContainsString('for="'.$id.'"', $content, "No visible label points to input {$id}.");
        }
    }
}
