@extends('layouts.app')
@section('title','Backup, Trash & Usage')
@section('content')
@php($pct=(int)($usage['progress']??0))
<div class="apple-page space-y-6">
  <div class="apple-hero">
    <div><div class="apple-eyebrow">Your data</div><h1>Backup, Trash & Usage</h1><p>See how much of My Digital Diary you use, download a private backup, and recover recently deleted records.</p></div>
    <a href="{{ route('account-data.backup') }}" class="apple-btn apple-btn-primary"><i class="fa-solid fa-cloud-arrow-down"></i> Download backup</a>
  </div>

  @if(session('status'))<div class="apple-alert">{{ session('status') }}</div>@endif

  <div class="apple-grid-3">
    <section class="apple-surface apple-dark-card">
      <div class="apple-icon-chip"><i class="fa-solid fa-chart-simple"></i></div>
      <div class="apple-muted">Usage progress</div><div class="apple-big">{{ $pct }}%</div>
      <div class="apple-progress"><span style="width:{{ $pct }}%"></span></div>
      <div class="apple-caption">{{ $usage['used_modules']??0 }} of {{ $usage['total_modules']??0 }} tracked areas in use</div>
    </section>
    <section class="apple-surface"><div class="apple-icon-chip light"><i class="fa-solid fa-clock-rotate-left"></i></div><h3>Activity history</h3><p>Review what you have been doing today, this week, this month, or across a custom date range.</p><a class="apple-link" href="{{ route('activity') }}">Open activity log →</a></section>
    <section class="apple-surface"><div class="apple-icon-chip light"><i class="fa-solid fa-trash-arrow-up"></i></div><h3>30-day recovery</h3><p>Deleted supported records stay recoverable here for 30 days before they disappear from your recovery list.</p><div class="apple-big small">{{ $trash->total() }}</div><div class="apple-caption">recoverable item(s)</div></section>
  </div>

  <section class="apple-surface">
    <div class="apple-section-head"><div><div class="apple-eyebrow">Recycle Bin</div><h2>Recently deleted</h2></div></div>
    @if($trash->isEmpty())
      <div class="apple-empty"><i class="fa-regular fa-circle-check"></i><strong>Your recycle bin is empty.</strong><span>Deleted supported records will appear here.</span></div>
    @else
      <div class="pm-dt-wrap">
        <table class="pm-dt">
          <caption class="sr-only">Recently deleted items you can restore or delete permanently.</caption>
          <thead><tr><th scope="col">Item</th><th scope="col">Expires</th><th scope="col" class="pm-dt-actions"><span class="sr-only">Actions</span></th></tr></thead>
          <tbody>
          @foreach($trash as $item)
            <tr>
              <td class="pm-dt-main">
                <span class="pm-dt-title" title="{{ $item->label ?: 'Untitled item' }}">{{ $item->label ?: 'Untitled item' }}</span>
                <span class="pm-dt-sub"><span>{{ class_basename($item->model_type) }}</span>@if($item->deleted_at)<span>Deleted {{ $item->deleted_at->format('d M Y H:i') }}</span>@endif</span>
              </td>
              <td class="pm-dt-aux" data-label="Expires">{{ optional($item->expires_at)->diffForHumans() }}</td>
              <td class="pm-dt-actions">
                <div class="inline-flex items-center gap-1">
                  <form method="POST" action="{{ route('account-data.restore',$item) }}">@csrf<button class="apple-btn apple-btn-small"><i class="fa-solid fa-rotate-left"></i> Restore</button></form>
                  <form method="POST" action="{{ route('account-data.destroy',$item) }}" data-confirm="Permanently delete this item? This action cannot be undone." data-confirm-title="Delete permanently?" data-confirm-text="Delete">@csrf @method('DELETE')<button class="pm-dt-icon-btn text-rose-500" title="Delete permanently" aria-label="Delete {{ $item->label ?: 'item' }} permanently"><i class="fa-solid fa-trash text-xs" aria-hidden="true"></i></button></form>
                </div>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>{{ $trash->links() }}
    @endif
  </section>
</div>
@endsection
