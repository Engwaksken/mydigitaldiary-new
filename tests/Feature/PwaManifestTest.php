<?php

namespace Tests\Feature;

use App\Http\Controllers\PwaController;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The PWA is only installable if three separate things hold at once: the
 * manifest is valid, the layouts link it, and the service worker does not put
 * a private diary into CacheStorage. Each is easy to break silently — a
 * renamed icon, a layout edited without the include, a "helpful" new cache
 * rule — so each is pinned here.
 */
class PwaManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_public_and_describes_an_installable_app(): void
    {
        SiteSetting::current()->forceFill(['site_name' => 'Diary Demo'])->save();

        // No actingAs(): a browser fetches this from the login page, so a
        // manifest behind auth could never install the app.
        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $this->assertStringStartsWith('application/manifest+json', $response->headers->get('Content-Type'));

        $manifest = json_decode($response->getContent(), true);

        $this->assertIsArray($manifest, 'the manifest must be valid JSON');
        $this->assertSame('Diary Demo', $manifest['name']);
        $this->assertLessThanOrEqual(12, mb_strlen($manifest['short_name']));
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);

        // A change to `id` or `start_url` makes the browser treat the next
        // deploy as a different app and re-prompt for installation.
        $this->assertSame('/', $manifest['id']);
    }

    public function test_manifest_ships_every_icon_size_and_purpose_a_launcher_needs(): void
    {
        $icons = collect(json_decode($this->get('/manifest.webmanifest')->getContent(), true)['icons']);

        $purposes = $icons->pluck('purpose')->filter()->unique()->values();

        $this->assertContains('any', $purposes, 'Chrome needs a plain "any" icon');
        $this->assertContains('maskable', $purposes, 'Android crops a maskable icon to the launcher shape');
        $this->assertContains('192x192', $icons->pluck('sizes')->all());
        $this->assertContains('512x512', $icons->pluck('sizes')->all());

        foreach ($icons as $icon) {
            $this->assertFileExists(
                public_path(ltrim($icon['src'], '/')),
                "the manifest points at a missing icon: {$icon['src']}"
            );
        }
    }

    public function test_uploaded_logo_is_used_for_every_install_icon_and_shortcut(): void
    {
        Storage::fake('public');
        $this->uploadLogo();
        $response = $this->get('/manifest.webmanifest')->assertOk();
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $manifest = $response->json();

        foreach ($manifest['icons'] as $icon) {
            $this->assertStringStartsWith('/pwa-icons/', $icon['src']);
            $imageResponse = $this->get($icon['src'])->assertOk()->assertHeader('Content-Type', 'image/png');
            $image = imagecreatefromstring($imageResponse->getContent());
            [$size] = explode('x', $icon['sizes']);
            $this->assertSame((int) $size, imagesx($image));
            $this->assertSame((int) $size, imagesy($image));
            $this->assertSame(0xff0000, imagecolorat($image, (int) ($size / 2), (int) ($size / 2)) & 0xffffff);
            $this->assertSame(0xffffff, imagecolorat($image, 0, 0) & 0xffffff);
            // Our source is twice as wide as it is tall. Letterboxing must
            // preserve those proportions, including the maskable safe zone.
            $this->assertSame(0xffffff, imagecolorat($image, (int) ($size / 2), (int) ($size * 0.2)) & 0xffffff);
            imagedestroy($image);
        }

        foreach ($manifest['shortcuts'] as $shortcut) {
            $this->assertSame($manifest['icons'][0]['src'], $shortcut['icons'][0]['src']);
        }
    }

    public function test_login_page_uses_uploaded_logo_for_ios_and_install_banner(): void
    {
        Storage::fake('public');
        $this->uploadLogo();
        $icons = $this->get('/manifest.webmanifest')->json('icons');

        $this->get(route('login'))->assertOk()
            ->assertSee('href="'.$icons[3]['src'].'"', false)
            ->assertSee('src="'.$icons[0]['src'].'"', false);
    }

    public function test_replacing_logo_at_same_path_changes_icons_and_worker_cache_but_not_app_identity(): void
    {
        Storage::fake('public');
        $this->uploadLogo();
        $before = $this->get('/manifest.webmanifest')->json();
        $workerBefore = $this->get('/sw.js')->getContent();
        $this->assertStringContainsString($before['icons'][0]['src'], $workerBefore);

        $this->uploadLogo(0, 0, 255);
        $after = $this->get('/manifest.webmanifest')->json();
        $workerAfter = $this->get('/sw.js')->getContent();

        $this->assertNotSame($before['icons'][0]['src'], $after['icons'][0]['src']);
        $this->assertNotSame($this->extractCacheVersion($workerBefore), $this->extractCacheVersion($workerAfter));
        $this->assertStringContainsString($after['icons'][0]['src'], $workerAfter);
        $this->assertSame($before['id'], $after['id']);
        $this->assertSame($before['start_url'], $after['start_url']);
        $this->get($after['icons'][0]['src'])->assertOk();
        $this->get($before['icons'][0]['src'])->assertNotFound();
    }

    public function test_missing_uploaded_logo_falls_back_to_existing_install_icons(): void
    {
        Storage::fake('public');
        SiteSetting::current()->forceFill(['logo_path' => 'branding/missing.png'])->save();
        $this->get('/manifest.webmanifest')->assertOk()->assertJsonPath('icons.0.src', '/icons/icon-192.png');
    }

    public function test_icon_endpoint_rejects_unknown_variant_and_version(): void
    {
        Storage::fake('public');
        $this->uploadLogo();
        $this->get('/pwa-icons/0000000000000000/icon-192.png')->assertNotFound();
        $this->get('/pwa-icons/0000000000000000/invalid.png')->assertNotFound();
    }

    public function test_browser_favicon_uses_logo_instead_of_separately_uploaded_favicon(): void
    {
        Storage::fake('public');
        $this->uploadLogo();
        SiteSetting::current()->forceFill(['favicon_path' => 'branding/old-default.png'])->save();
        $url = $this->get('/manifest.webmanifest')->json('icons.0.src');

        foreach ([route('login'), route('user-guide')] as $page) {
            $html = $this->get($page)->assertOk()->getContent();
            $this->assertStringContainsString('rel="icon" type="image/png" sizes="192x192" href="'.$url.'"', $html);
            $this->assertStringNotContainsString('old-default.png', $html);
        }

        $html = $this->actingAs(User::factory()->create())->get('/profile')->assertOk()->getContent();
        $this->assertStringContainsString('rel="icon" type="image/png" sizes="192x192" href="'.$url.'"', $html);
        $this->assertSame(1, substr_count($html, 'rel="icon"'));
    }

    public function test_root_favicon_url_redirects_to_current_logo_and_revalidates(): void
    {
        Storage::fake('public');
        $this->uploadLogo();
        $url = $this->get('/manifest.webmanifest')->json('icons.0.src');
        $response = $this->get('/favicon.ico')->assertRedirect($url);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertFileDoesNotExist(public_path('favicon.ico'), 'A static favicon would bypass the Laravel branding route.');

        $this->uploadLogo(0, 0, 255);
        $newUrl = $this->get('/manifest.webmanifest')->json('icons.0.src');
        $this->assertNotSame($url, $newUrl);
        $this->get('/favicon.ico')->assertRedirect($newUrl);
    }

    public function test_transparent_logo_is_composited_onto_an_opaque_install_icon(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(100, 100);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 30, 30, 70, 70, imagecolorallocatealpha($image, 255, 0, 0, 0));
        ob_start();
        imagepng($image);
        Storage::disk('public')->put('branding/transparent.png', ob_get_clean());
        imagedestroy($image);
        SiteSetting::current()->forceFill(['logo_path' => 'branding/transparent.png'])->save();

        $url = $this->get('/manifest.webmanifest')->json('icons.3.src');
        $image = imagecreatefromstring($this->get($url)->assertOk()->getContent());
        $this->assertSame(0xffffff, imagecolorat($image, 20, 20) & 0xffffff);
        $this->assertSame(0xff0000, imagecolorat($image, 90, 90) & 0xffffff);
        $this->assertSame(0, (imagecolorat($image, 20, 20) >> 24) & 0x7f);
        imagedestroy($image);
    }

    private function uploadLogo(int $red = 255, int $green = 0, int $blue = 0): void
    {
        $image = imagecreatetruecolor(120, 60);
        imagefill($image, 0, 0, imagecolorallocate($image, $red, $green, $blue));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        Storage::disk('public')->put('branding/pwa-test.png', $png);
        SiteSetting::current()->forceFill(['logo_path' => 'branding/pwa-test.png'])->save();
    }

    public function test_manifest_shortcuts_resolve_to_real_routes(): void
    {
        $shortcuts = collect(json_decode($this->get('/manifest.webmanifest')->getContent(), true)['shortcuts'])
            ->keyBy('name');

        $expected = [
            'Dashboard' => route('dashboard'),
            'Daily planner' => route('daily-planner.index'),
            'Notes' => route('notes.index'),
            'Meetings' => route('meetings.index'),
        ];

        $this->assertTrue($shortcuts->has('Dashboard'), 'the dashboard is the one shortcut worth guaranteeing');

        foreach ($shortcuts as $name => $shortcut) {
            $this->assertArrayHasKey($name, $expected, "unexpected shortcut: {$name}");

            // route(), never a hand-written path: a shortcut that 404s from the
            // home screen is worse than no shortcut.
            $this->assertSame(
                $expected[$name],
                $shortcut['url'],
                "the {$name} shortcut must point at its real route"
            );
        }
    }

    public function test_service_worker_is_public_revalidated_and_never_caches_a_whole_cache(): void
    {
        $response = $this->get('/sw.js');

        $response->assertOk();
        $this->assertStringStartsWith('application/javascript', $response->headers->get('Content-Type'));

        // A worker that is itself cached can never be replaced, so an update
        // would never reach anyone.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        // Required for a worker served from /sw.js to control the "/" scope
        // that the manifest asks for.
        $this->assertSame('/', $response->headers->get('Service-Worker-Allowed'));

        $source = $response->getContent();

        // Unreplaced placeholders would make the worker throw on its very
        // first line and leave the app with no offline shell at all.
        $this->assertStringNotContainsString('{{', $source);
        $this->assertMatchesRegularExpression(
            "/const CACHE_VERSION = '[0-9a-f]{12}';/",
            $source,
            'the cache key must be a resolved build hash, not a template'
        );
    }

    public function test_service_worker_precaches_the_shell_and_keys_its_cache_to_it(): void
    {
        $source = $this->get('/sw.js')->getContent();

        $this->assertMatchesRegularExpression(
            '/const PRECACHE_URLS = \[.*?\/offline\.html.*?\];/s',
            $source,
            'the offline page must be precached or the first offline visit has nothing to show'
        );

        // The cache key is a hash of that list, which is what makes a new deploy
        // invalidate the old cache without anyone bumping a version string.
        $this->assertSame(
            $this->expectedCacheVersion($source),
            $this->extractCacheVersion($source),
            'the cache key must be derived from the precache list'
        );
    }

    /**
     * The privacy rule, pinned. A diary behind a session login must never sit
     * in CacheStorage, where it would outlive the session that produced it and
     * be readable by the next person holding the phone.
     */
    public function test_service_worker_passes_api_and_authenticated_requests_straight_through(): void
    {
        $source = $this->get('/sw.js')->getContent();

        foreach (['/api/', '/sanctum/', '/storage/'] as $prefix) {
            $this->assertStringContainsString(
                "'{$prefix}'",
                $source,
                "the worker must never cache {$prefix} — it is the user's own data"
            );
        }

        $this->assertStringContainsString(
            "request.headers.has('authorization')",
            $source,
            'a bearer token means the Flutter client, never the shell'
        );

        // Navigations are network-first and their response is never stored.
        $this->assertStringContainsString("request.mode === 'navigate'", $source);
        $this->assertStringContainsString('return await fetch(request);', $source);
    }

    public function test_offline_page_is_self_contained_so_it_renders_with_no_network(): void
    {
        $path = public_path('offline.html');

        $this->assertFileExists($path);

        $html = (string) file_get_contents($path);

        // Tailwind's CDN, a webfont and the icon font are all unreachable when
        // this page is served, so none of them may be referenced here.
        $this->assertStringNotContainsString('cdn.tailwindcss.com', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com', $html);
        $this->assertStringContainsString('<style>', $html);
    }

    public function test_the_login_layout_links_the_manifest(): void
    {
        // The install prompt has to be reachable before anyone has an account,
        // which means the guest layout must carry the manifest link.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee(route('pwa.manifest'), false)
            ->assertSee('apple-mobile-web-app-capable', false);
    }

    public function test_the_app_layout_links_the_manifest_and_loads_the_install_prompt(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee(route('pwa.manifest'), false)
            ->assertSee('pm-pwa-root', false)
            ->assertSee('js/pwa.js', false)
            ->assertSee('beforeinstallprompt', false);
    }

    public function test_profile_settings_offers_the_install_option_permanently(): void
    {
        $user = User::factory()->create();

        // The floating banner is the first thing anyone dismisses; the settings
        // row is the one that is still there next week.
        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Install this app', false)
            ->assertSee('pm-pwa-settings-install', false)
            ->assertSee('Add to Home Screen', false);
    }

    private function extractCacheVersion(string $source): string
    {
        preg_match("/const CACHE_VERSION = '([^']+)';/", $source, $matches);

        return $matches[1] ?? '';
    }

    /**
     * Recomputes the worker's cache key from the precache list it was actually
     * shipped with, so the two can never drift apart silently.
     */
    private function expectedCacheVersion(string $source): string
    {
        preg_match('/const PRECACHE_URLS = (\[.*?\]);/s', $source, $matches);

        $urls = json_decode($matches[1] ?? '[]', true) ?: [];

        return substr(
            hash('sha256', PwaController::SCHEMA_VERSION.'|'.implode('|', $urls)),
            0,
            12
        );
    }
}
