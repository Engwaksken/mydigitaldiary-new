@props([
    // Each stat: ['label' => ..., 'value' => ..., 'icon' => 'fa-solid fa-…',
    //   'color' => 'sky', 'href' => url (optional; also 'route'/'url'/'link'),
    //   'title' => tooltip (optional)].
    'stats' => [],
    'label' => null,          // aria-label for the strip
    'accent' => 'slate',      // fallback icon colour
    'icon' => 'fa-solid fa-chart-simple', // fallback icon
])

{{-- Compact summary strip (styles: public/css/data-table.css .pm-summary*).
     2 columns on phones; up to 5 per row on larger screens. --}}
@if (!empty($stats))
    @php
        $pmStatCount = count($stats);
        $pmStatCols = $pmStatCount <= 5 ? $pmStatCount : ($pmStatCount % 3 === 0 ? 3 : 4);
        // Literal class strings so Tailwind's content scan always generates
        // them (colours are passed in as plain names like "sky").
        $pmIconTones = [
            'slate' => 'bg-slate-50 text-slate-600', 'gray' => 'bg-gray-50 text-gray-600',
            'red' => 'bg-red-50 text-red-600', 'rose' => 'bg-rose-50 text-rose-600',
            'pink' => 'bg-pink-50 text-pink-600', 'fuchsia' => 'bg-fuchsia-50 text-fuchsia-600',
            'purple' => 'bg-purple-50 text-purple-600', 'violet' => 'bg-violet-50 text-violet-600',
            'indigo' => 'bg-indigo-50 text-indigo-600', 'blue' => 'bg-blue-50 text-blue-600',
            'sky' => 'bg-sky-50 text-sky-600', 'cyan' => 'bg-cyan-50 text-cyan-600',
            'teal' => 'bg-teal-50 text-teal-600', 'emerald' => 'bg-emerald-50 text-emerald-600',
            'green' => 'bg-green-50 text-green-600', 'lime' => 'bg-lime-50 text-lime-600',
            'yellow' => 'bg-yellow-50 text-yellow-600', 'amber' => 'bg-amber-50 text-amber-600',
            'orange' => 'bg-orange-50 text-orange-600',
        ];
    @endphp
    <div {{ $attributes->merge(['class' => 'pm-summary']) }} style="--pm-summary-cols: {{ $pmStatCols }}"@if ($label) aria-label="{{ $label }}"@endif>
        @foreach ($stats as $stat)
            @php
                $statColor = $stat['color'] ?? $accent;
                $statIcon = $stat['icon'] ?? $icon;
                $statRoute = $stat['href'] ?? ($stat['route'] ?? ($stat['url'] ?? ($stat['link'] ?? null)));
                $statTitle = $stat['title'] ?? null;
            @endphp
            @if ($statRoute)
                <a href="{{ $statRoute }}" class="pm-summary-cell"@if ($statTitle) title="{{ $statTitle }}"@endif>
            @else
                <div class="pm-summary-cell"@if ($statTitle) title="{{ $statTitle }}"@endif>
            @endif
                    <span class="pm-summary-icon {{ $pmIconTones[$statColor] ?? 'bg-' . $statColor . '-50 text-' . $statColor . '-600' }}">
                        <i class="{{ $statIcon }}" aria-hidden="true"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="pm-summary-label" title="{{ $stat['label'] }}">{{ $stat['label'] }}</span>
                        <span class="pm-summary-value" title="{{ $stat['value'] }}">{{ $stat['value'] }}</span>
                    </span>
            @if ($statRoute)
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>
@endif
