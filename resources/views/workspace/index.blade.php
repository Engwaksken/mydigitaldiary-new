@extends('layouts.app')

@section('title', 'Shared Workspace')

@section('content')
<div id="team-workspace" class="mx-auto max-w-6xl space-y-5 px-3 py-4 sm:px-5">
<style>
#team-workspace *{box-sizing:border-box}
#team-workspace .ws-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:1rem;box-shadow:0 8px 24px rgba(15,23,42,.04)}
#team-workspace .ws-tabs{display:flex;gap:.5rem;overflow-x:auto;white-space:nowrap;padding-bottom:.35rem}
#team-workspace .ws-tabs>*{flex:0 0 auto;white-space:nowrap}
#team-workspace .ws-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
#team-workspace .file-name{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;word-break:normal}
@media(max-width:767px){#team-workspace .ws-grid{grid-template-columns:1fr}#team-workspace input,#team-workspace select,#team-workspace button{min-width:0;max-width:100%}}
</style>

<div class="ws-card">
 <p class="text-xs font-black uppercase tracking-widest text-emerald-600">Shared Workspace</p>
 <div class="flex flex-wrap items-start justify-between gap-3">
  <div><h1 class="mt-1 text-2xl font-black">{{ $o->name ?? $o->organization_name ?? 'Team Workspace' }}</h1>
  <p class="mt-1 text-sm text-slate-500">Personal diary information remains private until a member deliberately shares it.</p></div>
  <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ ucfirst($role) }}</span>
 </div>
  <nav class="ws-tabs mt-4" aria-label="Workspace sections">
  <a href="#members" class="rounded-xl border px-3 py-2 text-sm font-bold">Members</a>
  <a href="#shared" class="rounded-xl border px-3 py-2 text-sm font-bold">Shared Items</a>
  <a href="#files" class="rounded-xl border px-3 py-2 text-sm font-bold">Files</a>
  <a href="#activity" class="rounded-xl border px-3 py-2 text-sm font-bold">Activity</a>
 </nav>
</div>

{{-- Passed as the `message` prop so components/alert.blade.php registers it in
     its per-request dedupe list: the shared layout flash (partials/
     flash-messages.blade.php) renders the same session('success'), and this
     page's alert renders first, so only one of the two reaches the browser. --}}
@if(session('success'))<x-alert type="success" :message="session('success')" />@endif

<div class="grid gap-3 sm:grid-cols-3">
 <div class="ws-card"><p class="text-xs text-slate-400">MEMBERS</p><p class="text-2xl font-black">{{ $members->count() }}</p></div>
 <div class="ws-card"><p class="text-xs text-slate-400">SHARED ITEMS</p><p class="text-2xl font-black">{{ $sharedItems->count() }}</p></div>
 <div class="ws-card"><p class="text-xs text-slate-400">TEAM FILES</p><p class="text-2xl font-black">{{ $files->count() }}</p></div>
</div>

<section id="members" class="ws-card">
 <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
  <div><h2 class="font-black">Members</h2><p class="text-xs text-slate-500">Invite and manage members through the existing organisation screen.</p></div>
  @if(in_array($role,['owner','admin','administrator','manager'],true))
   <a href="{{ route('organization.show') }}" class="btn-primary rounded-xl px-4 py-2 text-sm font-bold text-white">Manage Members</a>
  @endif
 </div>
 <div class="ws-grid">
 @forelse($members as $m)
  <article class="min-w-0 rounded-xl border p-3"><p class="truncate font-bold">{{ $m->name ?? 'Member' }}</p><p class="truncate text-xs text-slate-500">{{ $m->email ?? '' }}</p><p class="mt-2 text-xs font-bold">{{ ucfirst($m->role ?? 'member') }}</p></article>
 @empty <p class="text-sm text-slate-500">No members yet.</p> @endforelse
 </div>
</section>

<section id="shared" class="ws-card">
 <h2 class="font-black">Shared Items</h2><p class="mb-3 text-xs text-slate-500">Only deliberately shared records appear here.</p>
 <div class="space-y-2">
 @forelse($sharedItems as $item)
  <article class="min-w-0 rounded-xl border p-3"><p class="truncate font-bold">{{ $item->title ?: ($item->item_type ?: 'Shared item') }}</p><p class="truncate text-xs text-slate-500">Shared by {{ $item->sharedBy?->name ?? 'Member' }} · {{ ucfirst($item->permission) }}</p></article>
 @empty <p class="text-sm text-slate-500">Nothing has been shared yet.</p> @endforelse
 </div>
</section>

<section id="files" class="ws-card">
 <h2 class="font-black">Team Files</h2><p class="mb-3 text-xs text-slate-500">Private authenticated downloads with mobile-safe file cards.</p>
 <form method="POST" action="{{ route('workspace.files.store') }}" enctype="multipart/form-data" class="mb-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_150px_auto]">
  @csrf
  <input type="file" name="file" required class="min-w-0 rounded-xl border px-3 py-2 text-sm">
  <select name="permission" class="min-w-0 rounded-xl border px-3 py-2 text-sm"><option value="view">Can view</option><option value="edit">Can edit</option><option value="manage">Can manage</option></select>
  <button class="btn-primary rounded-xl px-4 py-2 text-sm font-bold text-white">Upload</button>
 </form>
 <div class="ws-grid">
 @forelse($files as $file)
  <article class="min-w-0 rounded-xl border p-3">
   <div class="flex min-w-0 items-center gap-3"><i class="fa-solid fa-file text-xl text-emerald-600"></i><div class="min-w-0"><p class="file-name font-bold" title="{{ $file->original_name }}">{{ $file->original_name }}</p><p class="truncate text-xs text-slate-500">{{ $file->human_size }} · {{ $file->uploader?->name ?? 'Member' }}</p></div></div>
   <div class="mt-3 flex flex-wrap gap-2"><a href="{{ route('workspace.files.download',$file) }}" class="rounded-lg border px-3 py-1.5 text-xs font-bold">Download</a>
   @if((int)$file->uploaded_by_user_id===(int)auth()->id() || in_array($role,['owner','admin','administrator','manager'],true))
    <form method="POST" action="{{ route('workspace.files.destroy',$file) }}">@csrf @method('DELETE')<button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-bold text-rose-600">Delete</button></form>
   @endif</div>
  </article>
 @empty <p class="text-sm text-slate-500">No team files yet.</p> @endforelse
 </div>
</section>

<section id="activity" class="ws-card">
 <h2 class="font-black">Recent Activity</h2>
 <div class="mt-3 space-y-2">
 @forelse($activity as $e)
  <div class="rounded-xl bg-slate-50 p-3"><p class="truncate text-sm font-bold">{{ str($e->event)->replace('_',' ')->title() }}</p><p class="truncate text-xs text-slate-500">{{ $e->actor?->name ?? 'System' }} · {{ optional($e->created_at)->diffForHumans() }}</p></div>
 @empty <p class="text-sm text-slate-500">No activity yet.</p> @endforelse
 </div>
</section>
</div>
@endsection
