@extends('layouts.app')

@section('title', 'Announcements')

@section('content')
    <div class="flex items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shadow-sm shrink-0">
                <i class="fa-solid fa-bullhorn text-xl" aria-hidden="true"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Announcements</h1>
        </div>
        <a href="{{ route('admin.announcements.create') }}"
           class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
            <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> New Announcement
        </a>
    </div>

    <p class="text-sm text-slate-500 mb-6">
        Sent to every user's in-app notifications when published, plus push and email if chosen. Use the
        Preview tab to check the wording before sending. Scheduled times are in {{ config('app.timezone') }}.
    </p>

    <div id="pm-announcement-bulk-bar" class="flex items-center justify-between gap-3 bg-rose-50 border border-rose-200 rounded-lg px-4 py-3 mb-4">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" id="pm-announcement-select-all" onchange="pmToggleAllAnnouncements(this)" class="rounded border-slate-300 text-rose-600"> Select all on this page</label>
        <div class="flex items-center gap-3"><span id="pm-announcement-bulk-count" class="text-sm font-medium text-rose-700">0 selected</span><button type="button" onclick="pmSubmitAnnouncementBulkDelete()" class="bg-rose-600 hover:bg-rose-700 text-white px-3 py-2 rounded-lg text-sm font-medium"><i class="fa-solid fa-trash-can mr-1"></i>Delete selected</button></div>
    </div>

    <div class="space-y-4">
        @forelse ($announcements as $announcement)
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-5">
                <div class="mb-3"><input type="checkbox" value="{{ $announcement->id }}" onchange="pmUpdateAnnouncementBulkBar()" class="pm-announcement-checkbox rounded border-slate-300 text-rose-600" aria-label="Select {{ $announcement->title }}"></div>
                <div class="flex items-start justify-between gap-3 mb-2">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold text-slate-800 break-words"><a href="{{ route('admin.announcements.show', $announcement) }}" class="hover:underline">{{ $announcement->title }}</a></h2>
                            @php
                                $statusClass = match ($announcement->status) {
                                    'scheduled' => 'bg-sky-100 text-sky-700',
                                    'sending' => 'bg-amber-100 text-amber-700',
                                    'cancelled' => 'bg-slate-100 text-slate-500',
                                    'failed' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-emerald-100 text-emerald-700',
                                };
                            @endphp
                            @if ($announcement->isScheduled())
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $statusClass }}" data-scheduled-badge>
                                    <i class="fa-regular fa-clock mr-0.5" aria-hidden="true"></i>Scheduled for {{ \App\Services\SendTime::format($announcement->scheduled_for) }}
                                </span>
                            @else
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $statusClass }}">{{ $announcement->statusLabel() }}</span>
                            @endif
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $announcement->isNewFeature() ? 'bg-violet-100 text-violet-700' : 'bg-slate-100 text-slate-600' }}">{{ $announcement->typeLabel() }}</span>
                            @if ($announcement->feature())
                                <span class="text-xs text-slate-500"><i class="{{ $announcement->feature()['icon'] }} mr-1" aria-hidden="true"></i>{{ $announcement->feature()['label'] }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">
                            @if ($announcement->isScheduled())
                                Scheduled by {{ $announcement->creator->name ?? 'Unknown' }}
                            @elseif ($announcement->status === 'cancelled')
                                Cancelled · created by {{ $announcement->creator->name ?? 'Unknown' }}
                            @else
                                Sent {{ ($announcement->sent_at ?? $announcement->created_at)->diffForHumans() }} by {{ $announcement->creator->name ?? 'Unknown' }}
                            @endif
                            · {{ $announcement->audienceLabel() }} · {{ implode(', ', $announcement->channels()) }}
                            @if ($announcement->deliveries_count)
                                · <a href="{{ route('admin.announcements.show', $announcement) }}" class="text-[var(--brand-1)] hover:underline">{{ number_format($announcement->deliveries_count) }} reached</a>
                            @endif
                        </p>
                        @if ($announcement->isScheduled())
                            <div class="mt-2 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('admin.announcements.send-now', $announcement) }}"
                                      data-confirm="Send this announcement now instead of at {{ \App\Services\SendTime::format($announcement->scheduled_for) }}?" data-confirm-title="Send now?" data-confirm-text="Send now" data-confirm-danger="false">
                                    @csrf
                                    <button type="submit" class="btn-primary text-white px-3 py-1.5 rounded-lg text-xs font-semibold"><i class="fa-solid fa-paper-plane mr-1" aria-hidden="true"></i>Send now</button>
                                </form>
                                <form method="POST" action="{{ route('admin.announcements.cancel', $announcement) }}"
                                      data-confirm="Cancel this scheduled announcement? Nothing will be sent." data-confirm-title="Cancel announcement?" data-confirm-text="Cancel it">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                                </form>
                            </div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.announcements.destroy', $announcement->id) }}"
                          data-confirm="Delete this announcement record? This does not remove notifications already delivered to users." data-confirm-title="Delete announcement?" data-confirm-text="Delete">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-rose-500 hover:text-rose-700 text-sm">
                            <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>
                <p class="text-sm text-slate-600 whitespace-pre-line">{{ $announcement->body }}</p>
            </div>
        @empty
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-8 text-center text-slate-400">
                No announcements sent yet.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $announcements->links() }}
    </div>

<script>
function pmToggleAllAnnouncements(master){document.querySelectorAll('.pm-announcement-checkbox').forEach(function(cb){cb.checked=master.checked;});pmUpdateAnnouncementBulkBar();}
function pmUpdateAnnouncementBulkBar(){var all=Array.from(document.querySelectorAll('.pm-announcement-checkbox')),checked=all.filter(function(cb){return cb.checked;}),count=document.getElementById('pm-announcement-bulk-count'),master=document.getElementById('pm-announcement-select-all');if(count)count.textContent=checked.length+(checked.length===1?' selected':' selected');if(master){master.checked=all.length>0&&checked.length===all.length;master.indeterminate=checked.length>0&&checked.length<all.length;}}
function pmSubmitAnnouncementBulkDelete(){var checked=document.querySelectorAll('.pm-announcement-checkbox:checked');if(!checked.length){return;}pmConfirmAction({title:'Delete selected announcements?',message:'Delete '+checked.length+' selected announcement record(s)? This action cannot be undone.',confirmText:'Delete selected',onConfirm:function(){var form=document.createElement('form');form.method='POST';form.action=@json(route('admin.announcements.bulk-destroy'));form.innerHTML='<input type="hidden" name="_token" value="'+@json(csrf_token())+'"><input type="hidden" name="_method" value="DELETE">';checked.forEach(function(cb){var i=document.createElement('input');i.type='hidden';i.name='ids[]';i.value=cb.value;form.appendChild(i);});document.body.appendChild(form);form.submit();}});}
</script>
@endsection
