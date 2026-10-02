<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class MeetingTimeControlTest extends TestCase
{
    use RefreshDatabase;

    private function subscriber(): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'subscription_status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ])->save();

        return $user;
    }

    public function test_create_form_renders_named_datetime_controls_with_all_minute_values(): void
    {
        $html = $this->actingAs($this->subscriber())
            ->get(route('meetings.index'))->assertOk()->getContent();

        $this->assertStringContainsString('name="start_at"', $html);
        $this->assertStringContainsString('name="end_at"', $html);
        foreach (['start_at', 'end_at'] as $field) {
            preg_match('/<select id="field-' . $field . '-minute"[^>]*>(.*?)<\/select>/s', $html, $matches);
            $this->assertNotEmpty($matches, "Expected {$field} minute selector.");
            for ($minute = 0; $minute <= 59; $minute++) {
                $value = str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
                $this->assertStringContainsString('<option value="' . $value . '"', $matches[1]);
            }
        }
    }

    public function test_edit_form_preserves_arbitrary_minute_and_period(): void
    {
        $user = $this->subscriber();
        $meeting = Meeting::factory()->create([
            'user_id' => $user->id,
            'start_at' => '2026-10-15 21:37:00',
            'end_at' => '2026-10-15 22:37:00',
        ]);

        $html = $this->actingAs($user)->get(route('meetings.index'))->assertOk()->getContent();
        $this->assertStringContainsString('"start_at":"2026-10-15T21:37:00', $html);
        $this->assertStringContainsString('"end_at":"2026-10-15T22:37:00', $html);
        $this->assertStringContainsString('<option value="37" selected', $html);
        $this->assertStringContainsString('<option value="PM" selected', $html);
    }

    public function test_required_start_time_error_is_associated_with_visible_controls(): void
    {
        $errors = new ViewErrorBag;
        $errors->put('default', (new MessageBag)->add('start_at', 'A start time is required.'));

        $this->actingAs($this->subscriber())->withSession(['errors' => $errors])
            ->get(route('meetings.index'))->assertOk()
            ->assertSee('aria-required="true"', false)
            ->assertSee('aria-describedby="field-start_at-error"', false)
            ->assertSee('id="field-start_at-error" role="alert"', false);
    }
}
