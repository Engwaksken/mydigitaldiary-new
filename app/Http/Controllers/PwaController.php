<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Services\PwaIconService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Everything that makes this Laravel app installable as a PWA.
 *
 * The manifest is generated rather than shipped as a static file so the app's
 * own branding (site name, logo) is what shows up on the home screen and in
 * the install prompt. The service worker is generated for a more important
 * reason: its cache key and precache list are derived from the current build,
 * so shipping a new deploy busts the old cache without anyone remembering to
 * bump a version string.
 *
 * WHAT IS DELIBERATELY NOT CACHED: anything belonging to a signed-in user.
 * See the fetch handler in the service worker below — a private diary has no
 * business sitting in CacheStorage where the next person holding the phone can
 * read it.
 */
class PwaController extends Controller
{
    public function __construct(private readonly PwaIconService $icons)
    {
    }

    public function icon(string $version, string $variant): Response
    {
        return response($this->icons->png($version, $variant), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function favicon(): RedirectResponse
    {
        return redirect($this->icons->url('icon-192'))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    /**
     * Part of the cache key. Bump this ONLY when the service worker's own
     * logic changes; a new build already busts the cache because the key also
     * folds in a hash of the precache list.
     *
     * Public so the test that recomputes the cache key can read it instead of
     * copying the number, which would rot the first time it was bumped.
     */
    public const SCHEMA_VERSION = 2;

    /** Default brand teal, matching the fallback in layouts/app.blade.php. */
    private const THEME_COLOR = '#00897B';

    private const BACKGROUND_COLOR = '#00695C';

    /**
     * The app shell: the static files an installed app needs before it has a
     * network. Everything here is public, cache-busted by Laravel's asset
     * helper at the point of use, and contains no user data.
     */
    private const SHELL_ASSETS = [
        '/offline.html',
        '/css/app.css',
        '/css/responsive.css',
        '/css/modals.css',
        '/css/modal-responsive.css',
        '/css/professional-forms.css',
        '/css/time-12h.css',
        '/js/time-12h.js',
        '/js/pwa.js',
    ];

    /**
     * Home-screen shortcuts. Each entry is skipped when its route does not
     * exist, so removing a page can never leave a dead link in the manifest.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const SHORTCUTS = [
        ['dashboard', 'Dashboard'],
        ['daily-planner.index', 'Daily planner'],
        ['notes.index', 'Notes'],
        ['meetings.index', 'Meetings'],
    ];

    /**
     * GET /manifest.webmanifest
     */
    public function manifest(): JsonResponse
    {
        $name = $this->siteName();

        $manifest = [
            // `id` is the install identity: it must not change between deploys
            // or the browser treats an update as a different app.
            'id' => '/',
            'name' => $name,
            'short_name' => Str::length($name) > 12 ? Str::limit($name, 12, '') : $name,
            'description' => 'Your private diary, daily planner and wellbeing companion.',
            'lang' => str_replace('_', '-', app()->getLocale()),
            'dir' => 'ltr',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui', 'browser'],
            'orientation' => 'any',
            'theme_color' => self::THEME_COLOR,
            'background_color' => self::BACKGROUND_COLOR,
            'categories' => ['productivity', 'lifestyle', 'health', 'finance'],
            'prefer_related_applications' => false,
            'icons' => $this->icons->icons(),
            'shortcuts' => $this->shortcuts(),
        ];

        return response()
            ->json($manifest, 200, [
                'Content-Type' => 'application/manifest+json; charset=utf-8',
                // Revalidate branding so a previous install attempt cannot pin
                // the default logo after an administrator uploads a new one.
                'Cache-Control' => 'no-cache, must-revalidate',
            ]);
    }

    /**
     * GET /sw.js
     *
     * Served by PHP rather than dropped into public/ for two reasons: the
     * precache list stays in step with the Vite build, and the cache key
     * changes on every deploy without a human editing a version string.
     */
    public function serviceWorker(): Response
    {
        $precache = $this->precacheUrls();

        $version = substr(hash('sha256', self::SCHEMA_VERSION.'|'.implode('|', $precache)), 0, 12);

        $source = str_replace(
            ['{{VERSION}}', '{{PRECACHE}}', '{{OFFLINE_URL}}'],
            [$version, json_encode($precache, JSON_UNESCAPED_SLASHES), json_encode('/offline.html')],
            self::serviceWorkerSource()
        );

        return response($source, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            // A worker that is itself cached can never be replaced, so this one
            // is revalidated on every navigation.
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            // Lets the worker registered at /sw.js control the whole origin,
            // which is what the manifest's "/" scope asks for.
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /**
     * The shell plus the hashed Vite bundle, so an installed app opens with
     * its own JS and CSS available offline.
     *
     * @return list<string>
     */
    private function precacheUrls(): array
    {
        $urls = self::SHELL_ASSETS;
        foreach ($this->icons->icons() as $icon) {
            $urls[] = $icon['src'];
        }

        foreach ($this->viteAssets() as $asset) {
            $urls[] = '/build/'.$asset;
        }

        return array_values(array_unique($urls));
    }

    /**
     * File names from the Vite build manifest. Returns nothing while the dev
     * server is running (public/hot exists and there is nothing built yet),
     * which is correct: dev assets are served from memory and are not
     * precacheable anyway.
     *
     * @return list<string>
     */
    private function viteAssets(): array
    {
        if (file_exists(public_path('hot'))) {
            return [];
        }

        $manifestPath = public_path('build/manifest.json');

        if (! is_file($manifestPath)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            return [];
        }

        $files = [];

        foreach ($manifest as $entry) {
            if (is_array($entry) && isset($entry['file']) && is_string($entry['file'])) {
                $files[] = $entry['file'];
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function shortcuts(): array
    {
        $shortcuts = [];

        foreach (self::SHORTCUTS as [$routeName, $label]) {
            if (! Route::has($routeName)) {
                continue;
            }

            $shortcuts[] = [
                'name' => $label,
                'short_name' => $label,
                'url' => route($routeName),
                'icons' => [
                    [
                        'src' => $this->icons->url('icon-192'),
                        'sizes' => '192x192',
                        'type' => 'image/png',
                    ],
                ],
            ];
        }

        return $shortcuts;
    }

    /**
     * The install prompt shows this name, so it must not be an empty string
     * even when the settings row has never been filled in. SiteSetting is read
     * defensively because the browser fetches the manifest on every page view
     * and a database hiccup must not take the whole app's <head> down with it.
     */
    private function siteName(): string
    {
        try {
            $name = trim((string) SiteSetting::current()->site_name);
        } catch (Throwable) {
            $name = '';
        }

        if ($name === '') {
            $name = trim((string) config('app.name'));
        }

        return $name !== '' ? $name : 'My Digital Diary';
    }

    /**
     * Kept as a heredoc rather than a Blade view or a public/ file so it is
     * obvious that this is code with two placeholders in it, not a static
     * asset that someone might be tempted to hand-edit out of step with the
     * cache key.
     */
    private static function serviceWorkerSource(): string
    {
        return <<<'JS'
        /*
         * My Digital Diary service worker.
         *
         * THE ONE RULE: user data is never cached.
         *
         * This app is a private diary behind a session login. Its HTML, its
         * JSON and its files belong to whoever is signed in, and a diary entry
         * left in CacheStorage outlives the session that produced it — the
         * next person to open the installed app on that phone would read it.
         * So: no authenticated response is ever written to a cache, and the
         * offline experience is a self-contained page that says so.
         */
        const CACHE_VERSION = '{{VERSION}}';
        const SHELL_CACHE = 'pm-shell-' + CACHE_VERSION;
        const OFFLINE_URL = {{OFFLINE_URL}};
        const PRECACHE_URLS = {{PRECACHE}};

        /*
         * Requests the worker must pass straight through, untouched. These are
         * the API, the CSRF cookie endpoint, and anything a user uploaded:
         * none of it is shell, and none of it may be stored.
         */
        const NEVER_CACHE_PREFIXES = [
            '/api/',
            '/sanctum/',
            '/storage/',
            '/broadcasting/',
            '/vendor/livewire/',
        ];

        /* Only these are ever read from / written to the cache. */
        const SHELL_PREFIXES = ['/build/', '/css/', '/js/', '/icons/', '/pwa-icons/', '/fonts/', '/images/'];

        self.addEventListener('install', (event) => {
            event.waitUntil((async () => {
                const cache = await caches.open(SHELL_CACHE);

                /*
                 * One at a time, and failures swallowed. A single 404 must not
                 * abort the install and leave the app with no worker at all —
                 * a missing icon is not worth that.
                 */
                await Promise.all(PRECACHE_URLS.map(async (url) => {
                    try {
                        const response = await fetch(new Request(url, { cache: 'reload' }));

                        if (response && response.ok) {
                            await cache.put(url, response);
                        }
                    } catch (error) {
                        /* Offline during install: fall through to runtime caching. */
                    }
                }));
            })());
        });

        self.addEventListener('activate', (event) => {
            event.waitUntil((async () => {
                const names = await caches.keys();

                await Promise.all(
                    names
                        .filter((name) => name.startsWith('pm-shell-') && name !== SHELL_CACHE)
                        .map((name) => caches.delete(name))
                );

                await self.clients.claim();
            })());
        });

        /* The page asks for this when the user accepts an update. */
        self.addEventListener('message', (event) => {
            if (event.data && event.data.type === 'SKIP_WAITING') {
                self.skipWaiting();
            }
        });

        function isShellAsset(pathname) {
            return SHELL_PREFIXES.some((prefix) => pathname.startsWith(prefix));
        }

        /*
         * Cache keys drop the query string. layouts/app.blade.php versions
         * css/app.css with ?v=<filemtime>, so keying on the raw URL would store
         * a second copy of the same file on every deploy and never evict the
         * old one.
         */
        function cacheKey(url) {
            const normalised = new URL(url);

            normalised.search = '';
            normalised.hash = '';

            return normalised.toString();
        }

        async function staleWhileRevalidate(request) {
            const cache = await caches.open(SHELL_CACHE);
            const cached = await cache.match(request, { ignoreSearch: true });

            const network = fetch(request).then((response) => {
                // `basic` is same-origin and cacheable; opaque and error
                // responses must not be stored.
                if (response && response.ok && response.type === 'basic') {
                    cache.put(cacheKey(request.url), response.clone());
                }

                return response;
            }).catch(() => null);

            if (cached) {
                return cached;
            }

            return (await network) || Response.error();
        }

        self.addEventListener('fetch', (event) => {
            const request = event.request;
            const url = new URL(request.url);

            // Writes, and anything that is not our own origin, are the
            // browser's business.
            if (request.method !== 'GET' || url.origin !== self.location.origin) {
                return;
            }

            // A bearer token is how the Flutter app talks to the API. Never
            // intercepted, never stored.
            if (request.headers.has('authorization')) {
                return;
            }

            if (NEVER_CACHE_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
                return;
            }

            // The worker and the manifest must always come from the network or
            // an update can never be discovered.
            if (url.pathname === '/sw.js' || url.pathname === '/manifest.webmanifest') {
                return;
            }

            /*
             * Navigations are network-first, and the ONLY thing ever returned
             * from the cache is offline.html. This is what keeps signed-in HTML
             * out of CacheStorage: the network answer is passed straight
             * through without being stored, and a cached document would have
             * been another user's document.
             */
            if (request.mode === 'navigate') {
                event.respondWith((async () => {
                    try {
                        return await fetch(request);
                    } catch (error) {
                        const cache = await caches.open(SHELL_CACHE);

                        return (await cache.match(OFFLINE_URL)) || Response.error();
                    }
                })());

                return;
            }

            /*
             * Everything else that reaches this point is a shell asset.
             * Anything fetched as HTML by JavaScript (destination === '') is
             * deliberately not handled here, so it stays a network request.
             */
            if (isShellAsset(url.pathname)) {
                event.respondWith(staleWhileRevalidate(request));
            }
        });
        JS;
    }
}
