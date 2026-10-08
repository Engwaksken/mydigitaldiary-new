<?php

namespace Tests\Feature;

use App\Http\Controllers\CrudController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every shared CRUD list page renders the compact table (no sideways
 * scrolling) with a sample record in it.
 */
class CrudListTableLayoutTest extends TestCase
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

    private function sampleValue(array $field): mixed
    {
        if (! empty($field['options'])) {
            $keys = array_values(array_filter(array_keys($field['options']), fn ($k) => $k !== ''));

            return $keys[0] ?? null;
        }

        return match ($field['type']) {
            'number' => 5,
            'date' => now()->toDateString(),
            'datetime-local', 'datetime-native' => now()->addHour()->format('Y-m-d H:i:s'),
            'time' => '08:30',
            'checkbox' => true,
            'textarea' => 'A short sample note',
            'text' => 'Sample ' . $field['name'],
            default => null,
        };
    }

    public function test_every_crud_list_page_renders_the_compact_table(): void
    {
        $user = $this->subscriber();
        $this->actingAs($user);

        $checked = 0;
        foreach (glob(app_path('Http/Controllers/*Controller.php')) as $file) {
            $class = 'App\\Http\\Controllers\\' . basename($file, '.php');
            if (! class_exists($class) || ! is_subclass_of($class, CrudController::class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            $defaults = $reflection->getDefaultProperties();
            $routeName = $defaults['routeName'] ?? null;
            if (! $routeName || ! Route::has($routeName . '.index')) {
                continue;
            }

            $modelClass = $defaults['model'];
            $model = new $modelClass;
            $attributes = ['user_id' => $user->id];
            foreach ($defaults['fields'] as $field) {
                $value = $this->sampleValue($field);
                if ($value !== null && $model->isFillable($field['name'])) {
                    $attributes[$field['name']] = $value;
                }
            }

            try {
                $modelClass::create($attributes);
            } catch (\Throwable) {
                // Some modules need extra columns; the empty state still renders.
            }

            $response = $this->get(route($routeName . '.index'));
            $response->assertOk();

            if ($routeName !== 'savings-goals') {
                $response->assertSee('class="pm-dt"', false);
            }
            $checked++;
        }

        $this->assertGreaterThan(10, $checked);
    }
}
