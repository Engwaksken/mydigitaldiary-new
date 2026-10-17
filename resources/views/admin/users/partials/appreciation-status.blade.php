@if ($status['this_week'] ?? false)
    <span class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2.5 py-1 text-[10px] font-black uppercase text-teal-700">
        <i class="fa-regular fa-heart" aria-hidden="true"></i> Sent this week
    </span>
@elseif ($status['last_sent_at'] ?? null)
    <span class="text-xs font-semibold text-slate-600" title="Last appreciation email">{{ $status['last_sent_at']->copy()->timezone(config('app.timezone'))->format('d M Y') }}</span>
@else
    <span class="text-xs text-slate-400">—</span>
@endif
