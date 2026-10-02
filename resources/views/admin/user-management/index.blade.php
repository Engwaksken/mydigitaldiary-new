@extends('layouts.app')
@section('title', 'User Access Management')
@section('content')
<div id="admin-users" class="mx-auto max-w-7xl space-y-4 px-3 py-4 sm:px-5">
<style>
#admin-users .table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
#admin-users table{min-width:960px;width:100%}
#admin-users th,#admin-users td{white-space:nowrap;word-break:normal;padding:.75rem;text-align:left}
#admin-users .mobile-users{display:none}
@media(max-width:767px){#admin-users .desktop-users{display:none}#admin-users .mobile-users{display:grid;gap:.75rem}#admin-users input,#admin-users select,#admin-users button{min-width:0;max-width:100%}}
</style>
<div class="rounded-2xl border bg-white p-4">
 <p class="text-xs font-black uppercase tracking-widest text-slate-400">Administration</p><h1 class="text-2xl font-black">User Access Management</h1>
 <form class="mt-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_220px_auto]"><input name="q" value="{{ $q }}" placeholder="Search name or email" class="min-w-0 rounded-xl border px-3 py-2"><select name="status" class="rounded-xl border px-3 py-2"><option value="">All statuses</option><option value="active" @selected($status==='active')>Active</option><option value="suspended" @selected($status==='suspended')>Suspended</option></select><button class="btn-primary rounded-xl px-4 py-2 font-bold text-white">Filter</button></form>
</div>
@if(session('success'))<x-alert type="success" :message="session('success')" :dismissible="false" :autoDismiss="false" />@endif
<div class="desktop-users rounded-2xl border bg-white overflow-hidden"><div class="table-wrap"><table><thead><tr><th>User</th><th>Role</th><th>Subscription</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@foreach($users as $u)<tr><td><b>{{ $u->name }}</b><br><span class="text-xs text-slate-500">{{ $u->email }}</span></td><td>{{ ucfirst(str_replace('_',' ',$u->system_role??'user')) }}</td><td>{{ $u->subscriptionPlan?->name ?? '—' }}</td><td>{{ ucfirst($u->account_status??'active') }}</td><td>
<form method="POST" action="{{ route('admin.user-management.role',$u) }}" class="inline-flex gap-1">@csrf @method('PUT')<select name="system_role" class="rounded-lg border px-2 py-1 text-xs">@foreach(['user'=>'User','admin'=>'Admin','super_admin'=>'Super Admin'] as $v=>$l)<option value="{{ $v }}" @selected(($u->system_role??'user')===$v)>{{ $l }}</option>@endforeach</select><button class="rounded-lg border px-2 py-1 text-xs font-bold">Role</button></form>
@if(($u->account_status??'active')==='suspended')<form method="POST" action="{{ route('admin.user-management.reactivate',$u) }}" class="inline">@csrf<button class="rounded-lg bg-emerald-600 px-2 py-1 text-xs font-bold text-white">Reactivate</button></form>@else<form method="POST" action="{{ route('admin.user-management.suspend',$u) }}" class="inline">@csrf<button class="rounded-lg bg-amber-500 px-2 py-1 text-xs font-bold text-white">Suspend</button></form>@endif
@if((int)auth()->id()!==(int)$u->id)<form method="POST" action="{{ route('admin.user-management.destroy',$u) }}" class="inline" data-confirm="Delete this user?" data-confirm-title="Delete user" data-confirm-text="Delete">@csrf @method('DELETE')<input type="hidden" name="confirmation" value="DELETE"><button class="rounded-lg bg-rose-600 px-2 py-1 text-xs font-bold text-white">Delete</button></form>@endif
</td></tr>@endforeach
</tbody></table></div></div>
<div class="mobile-users">@foreach($users as $u)<article class="rounded-2xl border bg-white p-4"><p class="truncate font-black">{{ $u->name }}</p><p class="truncate text-xs text-slate-500">{{ $u->email }}</p><div class="mt-3 grid grid-cols-2 gap-2 text-xs"><div class="rounded-xl bg-slate-50 p-2">Role<br><b>{{ ucfirst(str_replace('_',' ',$u->system_role??'user')) }}</b></div><div class="rounded-xl bg-slate-50 p-2">Status<br><b>{{ ucfirst($u->account_status??'active') }}</b></div><div class="col-span-2 rounded-xl bg-slate-50 p-2">Plan<br><b>{{ $u->subscriptionPlan?->name ?? 'No plan' }}</b></div></div></article>@endforeach</div>
<div>{{ $users->links() }}</div>
</div>
@endsection
