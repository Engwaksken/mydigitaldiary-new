<?php

namespace Tests\Feature\Api;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationUnreadCountApiTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, bool $read = false): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\Notifications\AnnouncementNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => 'Hi']),
            'read_at' => $read ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    }

    public function test_counts_only_the_users_unread_notifications(): void
    {
        $user = User::factory()->create();
        $first = $this->notify($user);
        $this->notify($user);
        $this->notify($user, read: true);
        $this->notify(User::factory()->create());
        Sanctum::actingAs($user);

        $this->getJson('/api/notifications/unread-count')->assertOk()->assertExactJson(['unread_count' => 2]);
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 2)->assertJsonCount(3, 'data');

        $this->postJson("/api/notifications/{$first}/read")->assertOk();
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('unread_count', 1);

        $this->postJson('/api/notifications/read-all')->assertOk();
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('unread_count', 0);
    }

    public function test_push_carries_the_unread_count_for_the_launcher_badge(): void
    {
        config(['services.firebase.project_id' => 'demo']);
        Cache::put('fcm_access_token', 'token', 60);
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'])]);

        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'device_id' => 'd1', 'fcm_token' => 't1', 'platform' => 'android']);
        $this->notify($user);
        $this->notify($user);
        $this->notify($user);

        app(FcmService::class)->sendToUser($user, 'Title', 'Body', ['type' => 'reminder']);

        Http::assertSent(function ($request) {
            $message = $request->data()['message'];

            return $message['android']['notification']['notification_count'] === 3
                && $message['apns']['payload']['aps']['badge'] === 3
                && $message['data']['unread_count'] === '3'
                && $message['data']['type'] === 'reminder';
        });
    }
}
