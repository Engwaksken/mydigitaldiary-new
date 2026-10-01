@extends('layouts.app')
@section('title','Support')
@section('content')
<div class="max-w-3xl mx-auto space-y-4">
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div><h1 class="text-2xl font-bold text-slate-800">Support Chat</h1><p class="text-sm text-slate-500">{{ $conversation->assignee ? 'You are chatting with '.$conversation->assignee->name : 'AI support is answering while a support person is not assigned.' }}</p></div>
    <span class="w-fit px-3 py-1 rounded-full text-xs font-semibold {{ $conversation->assignee ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $conversation->assignee ? 'Human support' : 'AI support' }}</span>
  </div>

  <div class="pm-card-bg border border-slate-200 rounded-2xl p-4 min-h-[360px] overflow-y-auto space-y-3" id="support-thread">
    @forelse($messages as $message)
      @php $mine=$message->sender_type==='user'; @endphp
      <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}"><div class="max-w-[88%] sm:max-w-[82%] rounded-2xl px-4 py-3 text-sm {{ $mine ? 'bg-[var(--brand-1)] text-white' : 'bg-slate-100 text-slate-800' }}"><div class="text-[11px] mb-1 opacity-70">{{ $mine ? 'You' : ($message->sender_type==='ai' ? 'My Digital Diary AI' : ($message->user?->name ?? 'Support')) }}</div><div class="whitespace-pre-wrap break-words">{{ $message->sender_type === 'ai' ? \App\Services\SupportAiService::plainText($message->message) : $message->message }}</div></div></div>
    @empty
      <div class="text-center text-slate-400 py-16"><i class="fa-solid fa-headset text-3xl mb-3"></i><p>Ask a question about My Digital Diary.</p></div>
    @endforelse
  </div>
  <div>{{ $messages->links() }}</div>

  <form method="POST" action="{{ route('support.send') }}" class="flex flex-col gap-2 sm:flex-row">@csrf
    <textarea name="message" rows="2" required maxlength="4000" class="pm-input flex-1" placeholder="Type your support question..."></textarea>
    <button class="btn-primary min-h-[44px] px-5 rounded-xl text-white font-medium"><i class="fa-solid fa-paper-plane mr-1"></i>Send</button>
  </form>

  <div class="pm-card-bg rounded-xl border border-slate-200 p-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div><h2 class="text-sm font-semibold text-slate-800">End this support request</h2><p class="text-xs text-slate-500">{{ $supportPerson ? 'Please rate your support person before ending the chat.' : 'You can end this AI support request at any time.' }}</p></div>
      <button type="button" onclick="document.getElementById('end-support-form').classList.toggle('hidden')" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">End chat</button>
    </div>
    <form id="end-support-form" method="POST" action="{{ route('support.end') }}" class="hidden mt-4 space-y-3">@csrf
      @if($supportPerson)
        <div><label class="mb-1 block text-sm font-medium text-slate-700">Rate {{ $supportPerson->name }} <span class="text-rose-500">*</span></label><select name="rating" required class="pm-input"><option value="">Select rating</option><option value="5">5 - Excellent</option><option value="4">4 - Very good</option><option value="3">3 - Good</option><option value="2">2 - Fair</option><option value="1">1 - Poor</option></select></div>
      @endif
      <div><label class="mb-1 block text-sm font-medium text-slate-700">Comment</label><textarea name="comment" rows="2" maxlength="1000" class="pm-input" placeholder="Optional — share feedback about the support you received..."></textarea></div>
      <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Submit & end chat</button>
    </form>
  </div>

  <p class="text-xs text-slate-400">For security, never send passwords, OTP codes, card PINs or API keys in support chat.</p>
</div>
@endsection
