@extends('layouts.app')

@section('title', 'Team Chat')

@section('content')
<div class="team-chat-shell">
    <aside class="team-chat-sidebar">
        <div class="p-4 border-b border-slate-200">
            <div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">
                {{ $organization->name }}
            </div>
            <div class="mt-1 flex items-center justify-between gap-2">
                <h1 class="text-xl font-black text-slate-900">
                    Team Chat
                </h1>

                @if($canManage)
                    <button
                        type="button"
                        onclick="document.getElementById('new-chat-dialog').showModal()"
                        class="apple-btn rounded-xl px-3 py-2 text-xs font-bold"
                    >
                        <i class="fa-solid fa-plus"></i>
                    </button>
                @endif
            </div>
        </div>

        <div class="team-chat-list">
            @foreach($conversations as $item)
                <a
                    href="{{ route('team-chat.show', $item) }}"
                    class="team-chat-list-item {{ $item->id === $conversation->id ? 'is-active' : '' }}"
                >
                    <div class="team-chat-list-icon">
                        <i class="fa-solid {{ $item->type === 'channel' ? 'fa-hashtag' : 'fa-comments' }}"></i>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-black text-slate-900">
                            {{ $item->name ?: 'Conversation' }}
                        </div>
                        <div class="truncate text-xs text-slate-500">
                            {{ $item->description ?: ucfirst($item->type) }}
                        </div>
                    </div>

                    @if(($item->unread_count ?? 0) > 0)
                        <span class="team-chat-unread">
                            {{ min(99, $item->unread_count) }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </aside>

    <section class="team-chat-main">
        <header class="team-chat-header">
            <div>
                <h2 class="text-lg font-black text-slate-900">
                    {{ $conversation->name ?: 'Conversation' }}
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ $conversation->description ?: ucfirst($conversation->type) }}
                </p>
            </div>

            @if($canManage && !$conversation->is_general)
                <form
                    method="POST"
                    action="{{ route('team-chat.archive', $conversation) }}"
                >
                    @csrf
                    <button class="apple-btn rounded-xl px-3 py-2 text-xs font-bold">
                        Archive
                    </button>
                </form>
            @endif
        </header>

        @if(session('success'))
            <div class="mx-4 mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mx-4 mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="team-chat-messages" id="team-chat-messages">
            <div class="mb-3">
                {{ $messages->links() }}
            </div>

            @foreach($messages->reverse() as $message)
                <article
                    id="message-{{ $message->id }}"
                    class="team-chat-message {{ $message->is_announcement ? 'is-announcement' : '' }}"
                >
                    <div class="flex items-start gap-3">
                        <div class="team-chat-avatar">
                            {{ strtoupper(substr((string) ($message->user?->name ?? 'M'), 0, 1)) }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="text-sm font-black text-slate-900">
                                    {{ $message->user?->name ?? 'Member' }}
                                </span>
                                <span class="text-[11px] text-slate-400">
                                    {{ $message->created_at?->format('d M, g:i A') }}
                                </span>
                                @if($message->edited_at)
                                    <span class="text-[10px] text-slate-400">(edited)</span>
                                @endif
                                @if($message->is_announcement)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-800">
                                        Announcement
                                    </span>
                                @endif
                            </div>

                            @if($message->replyTo)
                                <div class="mt-2 rounded-lg border-l-2 border-teal-400 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                    <strong>{{ $message->replyTo->user?->name ?? 'Member' }}:</strong>
                                    {{ \Illuminate\Support\Str::limit($message->replyTo->body, 120) }}
                                </div>
                            @endif

                            @if($message->body)
                                <div class="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">
                                    {{ $message->body }}
                                </div>
                            @endif

                            @if($message->attachments->isNotEmpty())
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    @foreach($message->attachments as $attachment)
                                        <a
                                            href="{{ route('team-chat.attachments.download', $attachment) }}"
                                            class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2 hover:bg-slate-50"
                                        >
                                            <i class="fa-solid fa-paperclip text-teal-600"></i>
                                            <div class="min-w-0">
                                                <div class="truncate text-xs font-black text-slate-800">
                                                    {{ $attachment->original_name }}
                                                </div>
                                                <div class="text-[10px] text-slate-400">
                                                    {{ number_format($attachment->size_bytes / 1024, 1) }} KB
                                                </div>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-2 flex flex-wrap items-center gap-1">
                                @foreach(['like' => 'fa-thumbs-up', 'love' => 'fa-heart', 'celebrate' => 'fa-champagne-glasses', 'support' => 'fa-hands-clapping'] as $reaction => $icon)
                                    @php
                                        $count = $message->reactions->where('reaction', $reaction)->count();
                                    @endphp
                                    <form method="POST" action="{{ route('team-chat.messages.react', $message) }}">
                                        @csrf
                                        <input type="hidden" name="reaction" value="{{ $reaction }}">
                                        <button class="rounded-full border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50">
                                            <i class="fa-solid {{ $icon }} mr-1"></i>@if($count) {{ $count }} @endif
                                        </button>
                                    </form>
                                @endforeach

                                <button
                                    type="button"
                                    class="ml-1 text-xs font-bold text-slate-500 hover:text-teal-700"
                                    onclick="setReply({{ $message->id }}, @json($message->user?->name ?? 'Member'), @json(\Illuminate\Support\Str::limit($message->body, 90)))"
                                >
                                    Reply
                                </button>

                                @if($canManage || (int) $message->user_id === (int) auth()->id())
                                    <form
                                        method="POST"
                                        action="{{ route('team-chat.messages.destroy', $message) }}"
                                        onsubmit="return confirm('Delete this message?')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs font-bold text-rose-600">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <form
            method="POST"
            enctype="multipart/form-data"
            action="{{ route('team-chat.messages.store', $conversation) }}"
            class="team-chat-composer"
        >
            @csrf

            <div
                id="reply-preview"
                class="mb-2 hidden rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600"
            ></div>
            <input type="hidden" name="reply_to_id" id="reply_to_id">

            <textarea
                name="body"
                rows="3"
                class="pm-input w-full"
                placeholder="Message {{ $conversation->name ?: 'the team' }}…"
            ></textarea>

            <div class="mt-2 flex flex-wrap items-center gap-2">
                <label class="apple-btn cursor-pointer rounded-xl px-3 py-2 text-xs font-bold">
                    <i class="fa-solid fa-paperclip mr-1"></i>
                    Attach
                    <input
                        type="file"
                        name="attachments[]"
                        multiple
                        class="hidden"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,image/*,audio/*,video/*"
                    >
                </label>

                @if($canManage)
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-600">
                        <input type="checkbox" name="is_announcement" value="1">
                        Announcement
                    </label>

                    <label class="flex items-center gap-2 text-xs font-bold text-slate-600">
                        <input type="checkbox" name="notify_all" value="1">
                        Notify all
                    </label>
                @endif

                <div class="ml-auto">
                    <button
                        type="submit"
                        class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold text-white"
                    >
                        <i class="fa-solid fa-paper-plane mr-1"></i>
                        Send
                    </button>
                </div>
            </div>
        </form>
    </section>
</div>

@if($canManage)
<dialog id="new-chat-dialog" class="rounded-2xl p-0 shadow-2xl backdrop:bg-slate-900/55">
    <form
        method="POST"
        action="{{ route('team-chat.conversations.store') }}"
        class="w-[min(92vw,520px)] rounded-2xl bg-white"
    >
        @csrf

        <div class="border-b border-slate-100 px-5 py-4">
            <h3 class="text-lg font-black">New Conversation</h3>
        </div>

        <div class="space-y-4 px-5 py-4">
            <div>
                <label class="text-xs font-bold">Type</label>
                <select name="type" class="pm-input mt-1 w-full">
                    <option value="channel">Channel</option>
                    <option value="group">Group chat</option>
                    <option value="direct">Direct chat</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-bold">Name</label>
                <input name="name" class="pm-input mt-1 w-full" placeholder="e.g. Finance Team">
            </div>

            <div>
                <label class="text-xs font-bold">Description</label>
                <textarea name="description" rows="2" class="pm-input mt-1 w-full"></textarea>
            </div>

            <div>
                <label class="text-xs font-bold">Members for direct/group chat</label>
                <select
                    name="member_user_ids[]"
                    multiple
                    size="6"
                    class="pm-input mt-1 w-full"
                >
                    @foreach($members as $membership)
                        <option value="{{ $membership->user->id }}">
                            {{ $membership->user->name }} — {{ $membership->user->email }}
                        </option>
                    @endforeach
                </select>
            </div>

            <label class="flex items-center gap-2 text-xs font-bold">
                <input type="checkbox" name="is_announcement_only" value="1">
                Owners/Admins only can post
            </label>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4">
            <button type="button" onclick="this.closest('dialog').close()" class="apple-btn rounded-xl px-4 py-2 text-sm font-bold">
                Cancel
            </button>
            <button class="btn-primary rounded-xl px-4 py-2 text-sm font-bold text-white">
                Create
            </button>
        </div>
    </form>
</dialog>
@endif

<style>
.team-chat-shell{display:grid;grid-template-columns:300px minmax(0,1fr);height:calc(100dvh - 130px);min-height:620px;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;background:#fff}.team-chat-sidebar{display:grid;grid-template-rows:auto minmax(0,1fr);border-right:1px solid #e2e8f0;background:#f8fafc}.team-chat-list{overflow-y:auto;padding:8px}.team-chat-list-item{display:flex;align-items:center;gap:10px;padding:10px;border-radius:12px}.team-chat-list-item:hover,.team-chat-list-item.is-active{background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04)}.team-chat-list-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#ecfeff;color:#0f766e}.team-chat-unread{display:grid;place-items:center;min-width:22px;height:22px;border-radius:999px;background:#0f766e;color:#fff;font-size:10px;font-weight:900}.team-chat-main{display:grid;grid-template-rows:auto minmax(0,1fr) auto;min-width:0}.team-chat-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid #e2e8f0}.team-chat-messages{overflow-y:auto;padding:16px;background:#fff}.team-chat-message{padding:12px;border-radius:14px}.team-chat-message:hover{background:#f8fafc}.team-chat-message.is-announcement{border:1px solid #fde68a;background:#fffbeb}.team-chat-avatar{display:grid;place-items:center;width:36px;height:36px;flex:0 0 auto;border-radius:50%;background:#ccfbf1;color:#0f766e;font-size:13px;font-weight:900}.team-chat-composer{border-top:1px solid #e2e8f0;padding:12px 16px;background:#fff}@media(max-width:900px){.team-chat-shell{grid-template-columns:1fr;height:auto;min-height:0}.team-chat-sidebar{border-right:0;border-bottom:1px solid #e2e8f0}.team-chat-list{max-height:220px}.team-chat-main{min-height:70dvh}.team-chat-messages{min-height:45dvh;max-height:55dvh}}
</style>

<script>
function setReply(id, sender, body) {
    document.getElementById('reply_to_id').value = id;
    const box = document.getElementById('reply-preview');
    box.classList.remove('hidden');
    box.textContent = `Replying to ${sender}: ${body}`;
}
document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('team-chat-messages');
    if (box) box.scrollTop = box.scrollHeight;
});
</script>
@endsection
