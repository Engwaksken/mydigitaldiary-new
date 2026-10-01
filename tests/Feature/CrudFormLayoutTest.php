<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudFormLayoutTest extends TestCase
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

    public function test_long_crud_form_is_split_into_tabs(): void
    {
        $this->actingAs($this->subscriber())
            ->get(route('debts.index'))
            ->assertOk()
            ->assertSee('data-pm-form-tabs', false)
            ->assertSeeInOrder(['Details', 'Contact &amp; Notes', 'Reminders'], false)
            ->assertSee('data-pm-form-panel="2"', false);
    }

    public function test_short_crud_form_has_no_tabs(): void
    {
        $this->actingAs($this->subscriber())
            ->get(route('notes.index'))
            ->assertOk()
            ->assertDontSee('data-pm-form-tabs', false);
    }

    public function test_hints_are_placeholders_not_text_under_fields(): void
    {
        $response = $this->actingAs($this->subscriber())
            ->get(route('meetings.index'))
            ->assertOk();

        // The old helper line is gone and "(optional)" moved into the field.
        $response->assertDontSee('Leave blank if the end time is not fixed.');
        $response->assertDontSee('Repeat until (optional)');
        $response->assertSee('placeholder="name@example.com, colleague@example.com (optional)"', false);
        $response->assertSee('placeholder="1=Mon … 7=Sun, e.g. 1,3,5 (optional)"', false);
    }

    /**
     * Hand-built long forms outside the CRUD module use the same tab markup.
     */
    public function test_hand_built_long_forms_render_tabs(): void
    {
        $user = $this->subscriber();

        foreach ([
            'business-card.edit' => ['Profile', 'Contact', 'Social Links', 'Card Colors'],
            'spiritual-practices.index' => ['Practice', 'Reflection'],
            'social-media-planner.index' => ['Content', 'Media &amp; Link', 'Schedule &amp; Publish'],
            'diet-logs.index' => ['Body', 'Food &amp; Health', 'Sleep'],
            'sleep-logs.index' => ['Body', 'Sleep', 'Food &amp; Health'],
        ] as $route => $tabs) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertOk()
                ->assertSee('data-pm-form-tabs', false)
                ->assertSeeInOrder($tabs, false);
        }
    }

    public function test_select_starts_with_a_choose_placeholder(): void
    {
        $this->actingAs($this->subscriber())
            ->get(route('debts.index'))
            ->assertOk()
            ->assertSee('<option value="">Choose type</option>', false)
            ->assertSee('<option value="">Choose reminder channel (optional)</option>', false)
            ->assertDontSee('-- Select --');
    }
}
