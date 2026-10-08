{{--
    Mobile bottom navigation (phones only, < 768px).

    Keeps the daily essentials under the thumb: Today, Planner, a quick-add
    "+" sheet, Goals and More (which opens the existing sidebar, so every
    module stays reachable). Hidden on tablets/desktop, where the sidebar
    is always visible.
--}}
@php
    $bnItems = [
        ['label' => 'Today', 'icon' => 'fa-house', 'route' => 'dashboard', 'pattern' => 'dashboard'],
        ['label' => 'Planner', 'icon' => 'fa-calendar-check', 'route' => 'daily-planner.index', 'pattern' => 'daily-planner.*'],
        ['label' => 'Goals', 'icon' => 'fa-bullseye', 'route' => 'personal-goals.index', 'pattern' => 'personal-goals.*'],
    ];
    $bnQuickAdd = collect([
        ['Task', 'fa-list-check', '#047857', 'daily-planner.index'],
        ['Expense', 'fa-receipt', '#e11d48', 'expenses.index'],
        ['Income', 'fa-arrow-trend-up', '#059669', 'incomes.index'],
        ['Note', 'fa-note-sticky', '#ca8a04', 'notes.index'],
        ['Reminder', 'fa-bell', '#b45309', 'reminders.index'],
        ['Goal', 'fa-bullseye', '#be123c', 'personal-goals.index'],
    ])->filter(fn ($item) => Route::has($item[3]))->values();
    $bnLink = function (array $item) {
        $active = request()->routeIs($item['pattern']);
        return [$active, $active ? 'aria-current=page' : ''];
    };
@endphp

<style>
    #pm-bottom-nav{display:none}
    @media (max-width: 767.98px){
        #pm-bottom-nav{
            display:grid;grid-template-columns:repeat(5,1fr);align-items:end;
            position:fixed;left:0;right:0;bottom:0;z-index:60;
            padding:6px 8px calc(6px + env(safe-area-inset-bottom));
            background:rgba(255,255,255,.96);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
            border-top:1px solid #e5e9f0;box-shadow:0 -6px 24px rgba(15,23,42,.06);
        }
        .pm-bn-link{display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 0;border-radius:12px;color:#64748b!important;font-size:10.5px;font-weight:600;text-decoration:none!important;background:none;border:0;cursor:pointer;-webkit-tap-highlight-color:transparent}
        .pm-bn-link i{font-size:18px;transition:transform .15s ease}
        .pm-bn-link:active i{transform:scale(.88)}
        .pm-bn-link.active{color:var(--brand-1,#00897B)!important}
        .pm-bn-link.active i{transform:translateY(-1px)}
        .pm-bn-add{justify-self:center;width:52px;height:52px;margin-top:-22px;border-radius:18px;border:0;color:#fff;font-size:20px;background:var(--brand-1,#00897B);box-shadow:0 10px 22px color-mix(in srgb,var(--brand-1,#00897B) 40%,transparent);display:grid;place-items:center;cursor:pointer;transition:transform .15s ease}
        .pm-bn-add[aria-expanded="true"]{transform:rotate(45deg)}

        /* Make room for the bar and lift the other floating buttons above it. */
        body.pm-has-bottom-nav #pm-app-shell{padding-bottom:calc(76px + env(safe-area-inset-bottom))}
        body.pm-has-bottom-nav #sidebar-toggle{display:none!important}
        body.pm-has-bottom-nav .pm-a11y-toggle,
        body.pm-has-bottom-nav #pm-support-toggle{bottom:calc(80px + env(safe-area-inset-bottom))!important}
        body.pm-has-bottom-nav .pm-a11y-panel,
        body.pm-has-bottom-nav #pm-support-panel{bottom:calc(140px + env(safe-area-inset-bottom))!important;max-height:calc(100dvh - 160px - env(safe-area-inset-bottom))!important}
        body.pm-has-bottom-nav #pm-pwa-banner{bottom:calc(68px + env(safe-area-inset-bottom))!important}
    }

    .pm-qa-backdrop{position:fixed;inset:0;z-index:2147482000;background:rgba(15,23,42,.45);display:flex;align-items:flex-end;justify-content:center;padding:0 10px calc(84px + env(safe-area-inset-bottom));animation:pmQaFade .18s ease}
    .pm-qa-backdrop[hidden]{display:none}
    .pm-qa-sheet{width:min(440px,100%);background:#fff;border-radius:22px;padding:16px;box-shadow:0 24px 60px rgba(15,23,42,.3);animation:pmQaUp .22s ease}
    .pm-qa-title{font-size:14px;font-weight:800;color:#0f172a;margin:0 4px 12px}
    .pm-qa-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
    .pm-qa-item{display:flex;flex-direction:column;align-items:center;gap:7px;padding:12px 4px;border-radius:16px;background:#f8fafc;color:#334155!important;font-size:12px;font-weight:600;text-decoration:none!important}
    .pm-qa-item span{width:42px;height:42px;border-radius:14px;display:grid;place-items:center;font-size:17px;color:var(--qa-c);background:color-mix(in srgb,var(--qa-c) 12%,#fff)}
    @keyframes pmQaFade{from{opacity:0}to{opacity:1}}
    @keyframes pmQaUp{from{transform:translateY(16px);opacity:0}to{transform:none;opacity:1}}
    @media (prefers-reduced-motion: reduce){.pm-qa-backdrop,.pm-qa-sheet{animation:none}}
</style>

<nav id="pm-bottom-nav" aria-label="Quick navigation">
    @foreach (array_slice($bnItems, 0, 2) as $item)
        @php [$active] = $bnLink($item); @endphp
        <a href="{{ route($item['route']) }}" class="pm-bn-link {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif>
            <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i>{{ $item['label'] }}
        </a>
    @endforeach

    <button type="button" class="pm-bn-add" id="pm-bn-add" aria-label="Quick add" aria-expanded="false" aria-controls="pm-quick-add">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
    </button>

    @php $item = $bnItems[2]; [$active] = $bnLink($item); @endphp
    <a href="{{ route($item['route']) }}" class="pm-bn-link {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif>
        <i class="fa-solid {{ $item['icon'] }}" aria-hidden="true"></i>{{ $item['label'] }}
    </a>

    <button type="button" class="pm-bn-link" id="pm-bn-more" aria-controls="sidebar" aria-expanded="false">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>More
    </button>
</nav>

<div class="pm-qa-backdrop" id="pm-quick-add" hidden>
    <div class="pm-qa-sheet" role="dialog" aria-modal="true" aria-labelledby="pm-qa-title">
        <div class="pm-qa-title" id="pm-qa-title">Add</div>
        <div class="pm-qa-grid">
            @foreach ($bnQuickAdd as $qa)
                <a href="{{ route($qa[3], ['new' => 1]) }}" class="pm-qa-item" style="--qa-c:{{ $qa[2] }}">
                    <span><i class="fa-solid {{ $qa[1] }}" aria-hidden="true"></i></span>{{ $qa[0] }}
                </a>
            @endforeach
        </div>
    </div>
</div>

<script id="pm-bottom-nav-runtime">
(function () {
    'use strict';
    document.body.classList.add('pm-has-bottom-nav');

    var add = document.getElementById('pm-bn-add');
    var sheet = document.getElementById('pm-quick-add');
    var more = document.getElementById('pm-bn-more');

    function setSheet(open) {
        if (!sheet || !add) { return; }
        sheet.hidden = !open;
        add.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) { var first = sheet.querySelector('a'); if (first) { first.focus(); } }
    }

    if (add) { add.addEventListener('click', function () { setSheet(sheet.hidden); }); }
    if (sheet) {
        sheet.addEventListener('click', function (event) { if (event.target === sheet) { setSheet(false); } });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sheet && !sheet.hidden) { setSheet(false); add.focus(); }
    });

    // "More" reuses the existing sidebar toggle, so every module stays one tap away.
    if (more) {
        more.addEventListener('click', function () {
            var toggle = document.getElementById('sidebar-toggle');
            var sidebar = document.getElementById('sidebar');
            if (!toggle || !sidebar) { return; }
            toggle.click();
            var open = !sidebar.classList.contains('hidden');
            more.setAttribute('aria-expanded', open ? 'true' : 'false');
            more.classList.toggle('active', open);
            if (open) { window.scrollTo({ top: 0, behavior: 'smooth' }); }
        });
    }
})();
</script>
