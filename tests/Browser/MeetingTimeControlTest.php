<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_meeting_time_control_offers_all_minute_values(): void
    {
        $user = $this->subscriber();
        $html = $this->actingAs($user)->get(route('meetings.index'))->assertOk()->getContent();

        preg_match('/<select id="field-start_at-minute"[^>]*>(.*?)<\/select>/s', $html, $matches);
        $this->assertNotEmpty($matches, 'Meeting start time should render its minute selector.');
        for ($minute = 0; $minute <= 59; $minute++) {
            $value = str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
            $this->assertStringContainsString('<option value="'.$value.'"', $matches[1]);
        }
    }

    public function test_meeting_time_error_is_associated_with_required_visible_controls(): void
    {
        $errors = new \Illuminate\Support\ViewErrorBag;
        $errors->put('default', (new \Illuminate\Support\MessageBag)->add('start_at', 'A start time is required.'));

        $this->actingAs($this->subscriber())
            ->withSession(['errors' => $errors])
            ->get(route('meetings.index'))
            ->assertOk()
            ->assertSee('id="field-start_at-date"', false)
            ->assertSee('id="field-start_at-hour"', false)
            ->assertSee('id="field-start_at-minute"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="field-start_at-error"', false)
            ->assertSee('id="field-start_at-error" role="alert"', false);
    }
}
