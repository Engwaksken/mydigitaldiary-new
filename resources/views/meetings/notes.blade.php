@extends('layouts.app')

@section('title', 'Meeting Notes')

@section('content')

@php
    $tz = auth()->user()?->timezone ?: 'Africa/Kampala';

    /*
     * Extract attendee emails from the Meeting attendees field.
     * Supports:
     * - comma/semicolon/new-line separated strings
     * - arrays of emails
     * - arrays/objects containing email/value fields
     */
    $attendeeEmails = collect();

    $rawAttendees = $meeting->attendees ?? null;

    if (
        is_array($rawAttendees)
        || $rawAttendees instanceof \Illuminate\Support\Collection
    ) {
        $attendeeEmails = collect($rawAttendees)
            ->map(function ($entry) {
                if (is_string($entry)) {
                    return trim($entry);
                }

                if (is_array($entry)) {
                    return trim(
                        (string) (
                            $entry['email']
                            ?? $entry['value']
                            ?? ''
                        )
                    );
                }

                if (is_object($entry)) {
                    return trim(
                        (string) (
                            $entry->email
                            ?? $entry->value
                            ?? ''
                        )
                    );
                }

                return '';
            });
    } elseif (filled($rawAttendees)) {
        $attendeeEmails = collect(
            preg_split(
                '/[,;\n]+/',
                (string) $rawAttendees
            )
        );
    }

    $attendeeEmails = $attendeeEmails
        ->map(fn ($email) => trim((string) $email))
        ->filter(
            fn ($email) => filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        )
        ->unique()
        ->values();

    $attendeeEmailString = $attendeeEmails->implode(', ');
@endphp


<div
    id="meeting-notes-page"
    class="space-y-4"
>

    {{-- =========================================================
         HEADER
    ========================================================== --}}
    <div class="flex flex-wrap items-start justify-between gap-3">

        <div class="min-w-0">
            <div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">
                Meeting Workspace
            </div>

            <h1 class="mt-1 text-xl font-black text-slate-900">
                {{ $meeting->title }}
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                @if($meeting->start_at)
                    {{
                        optional($meeting->start_at)
                            ->timezone($tz)
                            ->format('d M Y, g:i A')
                    }}
                @endif

                @if($meeting->location)
                    <span>
                        · {{ $meeting->location }}
                    </span>
                @endif
            </p>
        </div>


        <div class="flex flex-wrap items-center gap-2">

            @if($meeting->diary_join_url)
                <a
                    href="{{ $meeting->diary_join_url }}"
                    class="btn-primary inline-flex items-center rounded-xl px-4 py-2.5 text-sm font-bold text-white"
                >
                    <i class="fa-solid fa-video mr-1"></i>
                    Join meeting
                </a>
            @endif


            <a
                href="{{ route('meetings.index') }}"
                class="apple-btn inline-flex items-center rounded-xl px-4 py-2.5 text-sm font-bold"
            >
                <i class="fa-solid fa-arrow-left mr-1"></i>
                Meetings
            </a>

        </div>
    </div>


    {{-- =========================================================
         ALERTS
    ========================================================== --}}
    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif


    @if($errors->any())
        <x-alert type="error" :dismissible="false" :auto-dismiss="false">
            <div class="mb-1 font-bold">
                Please check the following:
            </div>

            <ul class="list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>
                        {{ $error }}
                    </li>
                @endforeach
            </ul>
        </x-alert>
    @endif


    {{-- =========================================================
         WORKSPACE
    ========================================================== --}}
    <section class="apple-surface overflow-hidden rounded-2xl">

        {{-- Tabs --}}
        <div
            class="meeting-tabs"
            role="tablist"
            aria-label="Meeting workspace"
        >

            <button
                type="button"
                class="meeting-tab is-active"
                data-meeting-tab="notes"
                role="tab"
            >
                <i class="fa-solid fa-note-sticky"></i>
                Notes
            </button>


            <button
                type="button"
                class="meeting-tab"
                data-meeting-tab="record-meeting"
                role="tab"
            >
                <i class="fa-solid fa-record-vinyl"></i>
                Record Meeting
            </button>


            <button
                type="button"
                class="meeting-tab"
                data-meeting-tab="transcripts-summary"
                role="tab"
            >
                <i class="fa-solid fa-file-waveform"></i>
                Transcript &amp; Summary
            </button>

        </div>


        <div class="p-4 sm:p-5">

            {{-- =================================================
                 NOTES PANEL
            ================================================== --}}
            <div
                class="meeting-panel"
                data-meeting-panel="notes"
            >

                <form
                    method="POST"
                    action="{{ route('meetings.notes.update', $meeting) }}"
                >
                    @csrf
                    @method('PUT')


                    <label
                        for="meeting-notes"
                        class="text-sm font-black text-slate-800"
                    >
                        Meeting Notes / Minutes
                    </label>


                    <textarea
                        id="meeting-notes"
                        name="notes"
                        rows="16"
                        class="pm-input mt-2 w-full"
                        placeholder="Write discussion points, decisions, actions and follow-up notes..."
                    >{{ old('notes', $meeting->notes) }}</textarea>


                    <div class="mt-4 flex flex-wrap justify-between gap-2">

                        <div class="flex flex-wrap gap-2">

                            <a
                                href="{{ route('meetings.notes.pdf', $meeting) }}"
                                class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold"
                            >
                                <i class="fa-solid fa-file-pdf mr-1"></i>
                                Download PDF
                            </a>


                            <button
                                type="button"
                                data-open-email-notes
                                class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold"
                            >
                                <i class="fa-solid fa-envelope mr-1"></i>
                                Email Notes
                            </button>

                        </div>


                        <button
                            type="submit"
                            class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold text-white"
                        >
                            <i class="fa-solid fa-floppy-disk mr-1"></i>
                            Save Notes
                        </button>

                    </div>

                </form>

            </div>


            {{-- =================================================
                 RECORD MEETING PANEL
            ================================================== --}}
            <div
                id="record-meeting"
                class="meeting-panel"
                data-meeting-panel="record-meeting"
                hidden
            >

                <div class="grid gap-4 lg:grid-cols-2">


                    {{-- Browser recorder --}}
                    <div class="meeting-work-card">

                        <div class="flex flex-wrap items-start justify-between gap-3">

                            <div>
                                <h2 class="font-black text-slate-900">
                                    <i class="fa-solid fa-record-vinyl mr-1 text-rose-600"></i>
                                    Record in Browser
                                </h2>

                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    Record the meeting/tab audio together with your microphone or headset.
                                </p>
                            </div>


                            <div
                                id="recording-badge"
                                class="meeting-recording-badge"
                            >
                                <span class="meeting-recording-dot"></span>

                                <span id="recording-badge-label">
                                    Not recording
                                </span>
                            </div>

                        </div>


                        <div
                            id="recording-message"
                            class="hidden meeting-recording-message"
                            role="status"
                            aria-live="polite"
                        ></div>


                        {{-- Sources --}}
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">

                            <div
                                id="microphone-source-card"
                                class="meeting-audio-source"
                            >
                                <div class="meeting-audio-source-icon">
                                    <i class="fa-solid fa-microphone"></i>
                                </div>

                                <div>
                                    <div class="text-xs font-black text-slate-800">
                                        Microphone
                                    </div>

                                    <div
                                        id="microphone-status"
                                        class="text-xs text-slate-500"
                                    >
                                        Waiting
                                    </div>
                                </div>
                            </div>


                            <div
                                id="meeting-audio-source-card"
                                class="meeting-audio-source"
                            >
                                <div class="meeting-audio-source-icon">
                                    <i class="fa-solid fa-volume-high"></i>
                                </div>

                                <div>
                                    <div class="text-xs font-black text-slate-800">
                                        Meeting Audio
                                    </div>

                                    <div
                                        id="meeting-audio-status"
                                        class="text-xs text-slate-500"
                                    >
                                        Waiting
                                    </div>
                                </div>
                            </div>

                        </div>


                        <div class="meeting-recording-help">
                            <i class="fa-solid fa-circle-info mt-0.5"></i>

                            <div>
                                <strong>
                                    When Chrome or Edge asks what to share:
                                </strong>

                                <p class="mt-1">
                                    Select the browser tab where the meeting is running and enable
                                    <strong>Share tab audio</strong> or <strong>Share audio</strong>.
                                </p>

                                <p class="mt-1">
                                    Your wired, Bluetooth or USB headset microphone is captured separately.
                                </p>
                            </div>
                        </div>


                        <label class="meeting-consent">
                            <input
                                type="checkbox"
                                id="record-consent"
                                class="mt-0.5"
                            >

                            <span>
                                I confirm participants have been informed and consent to this recording where required.
                            </span>
                        </label>


                        <div class="mt-4 flex flex-wrap items-center gap-2">

                            <button
                                type="button"
                                id="start-recording"
                                class="meeting-control meeting-control-start"
                            >
                                <i class="fa-solid fa-circle"></i>
                                Start Recording
                            </button>


                            <button
                                type="button"
                                id="pause-recording"
                                class="meeting-control meeting-control-pause hidden"
                            >
                                <i class="fa-solid fa-pause"></i>
                                Pause
                            </button>


                            <button
                                type="button"
                                id="resume-recording"
                                class="meeting-control meeting-control-resume hidden"
                            >
                                <i class="fa-solid fa-play"></i>
                                Resume
                            </button>


                            <button
                                type="button"
                                id="stop-recording"
                                class="meeting-control meeting-control-stop hidden"
                            >
                                <i class="fa-solid fa-stop"></i>
                                Stop &amp; Save
                            </button>


                            <div
                                id="recording-timer"
                                class="meeting-timer"
                            >
                                00:00:00
                            </div>

                        </div>


                        <div
                            id="recording-status"
                            class="mt-3 text-xs font-bold text-slate-500"
                        >
                            Ready to record.
                        </div>

                    </div>


                    {{-- Existing upload --}}
                    <div class="meeting-work-card">

                        <h2 class="font-black text-slate-900">
                            <i class="fa-solid fa-cloud-arrow-up mr-1 text-blue-600"></i>
                            Upload Existing Recording
                        </h2>


                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Upload audio or video captured on another device.
                        </p>


                        <form
                            method="POST"
                            action="{{ route('meetings.recordings.upload', $meeting) }}"
                            enctype="multipart/form-data"
                            class="mt-4 space-y-3"
                        >
                            @csrf


                            <input
                                type="file"
                                name="audio"
                                accept="audio/*,video/*,.webm,.weba,.ogg,.oga,.opus,.m4a,.mp3,.wav,.mp4,.mov"
                                required
                                class="pm-input w-full"
                            >


                            <button
                                type="submit"
                                class="btn-primary rounded-xl px-4 py-2.5 text-sm font-bold text-white"
                            >
                                <i class="fa-solid fa-upload mr-1"></i>
                                Upload Recording
                            </button>

                        </form>

                    </div>

                </div>

            </div>


            {{-- =================================================
                 TRANSCRIPTS & SUMMARY PANEL
            ================================================== --}}
            <div
                id="transcripts-summary"
                class="meeting-panel"
                data-meeting-panel="transcripts-summary"
                hidden
            >

                <div class="space-y-4">

                    @forelse($recordings as $recording)

                        <article class="meeting-work-card">

                            <div class="flex flex-wrap items-start justify-between gap-3">

                                <div>

                                    <h3 class="font-black text-slate-900">
                                        Recording #{{ $recording->id }}
                                    </h3>


                                    <div class="mt-1 flex flex-wrap items-center gap-2">

                                        <span class="meeting-status-pill">
                                            {{ ucfirst($recording->status) }}
                                        </span>


                                        <span class="text-xs text-slate-500">
                                            <i class="fa-regular fa-clock mr-1"></i>
                                            {{ $recording->formattedDuration() }}
                                        </span>

                                    </div>

                                </div>


                                <button
                                    type="button"
                                    class="meeting-delete-button"
                                    data-recording-delete
                                    data-action="{{ route('meeting-recordings.destroy', $recording) }}"
                                    data-label="Recording #{{ $recording->id }}"
                                >
                                    <i class="fa-solid fa-trash-can"></i>
                                    Delete
                                </button>

                            </div>


                            @if($recording->audio_path)

                                <div class="mt-4 rounded-xl bg-slate-50 p-3">

                                    <audio
                                        controls
                                        preload="metadata"
                                        class="w-full"
                                    >
                                        <source
                                            src="{{ route('meeting-recordings.audio.stream', $recording) }}"
                                        >
                                    </audio>

                                </div>

                                {{-- Audio Editor / Waveform --}}
                                <div class="mt-4">
                                    <button
                                        type="button"
                                        class="meeting-edit-audio-button"
                                        data-recording-id="{{ $recording->id }}"
                                        data-audio-url="{{ route('meeting-recordings.audio.stream', $recording) }}"
                                        data-duration="{{ $recording->duration_seconds }}"
                                    >
                                        <i class="fa-solid fa-waveform-lines mr-1"></i>
                                        Edit Audio (Cut/Trim)
                                    </button>
                                </div>

                            @endif


                            {{-- Actions --}}
                            <div class="mt-4 flex flex-wrap items-center gap-2">

                                @if($recording->audio_path)

                                    <a
                                        href="{{ route('meeting-recordings.audio', $recording) }}"
                                        class="apple-btn rounded-xl px-3 py-2 text-xs font-bold"
                                    >
                                        <i class="fa-solid fa-download mr-1"></i>
                                        Audio
                                    </a>


<form
                                        method="POST"
                                        action="{{ route('meeting-recordings.process', $recording) }}"
                                        class="flex flex-wrap items-center gap-2 transcribe-form"
                                        data-recording-id="{{ $recording->id }}"
                                        data-check-capacity-url="{{ route('meeting-recordings.check-capacity', $recording) }}"
                                    >
                                        @csrf


                                        <select
                                            name="transcription_language"
                                            class="pm-input meeting-language-select"
                                        >
                                            <option value="auto">
                                                Auto detect
                                            </option>

                                            <option value="en-GB">
                                                UK English
                                            </option>

                                            <option value="lg">
                                                Luganda
                                            </option>

                                            <option value="sw">
                                                Kiswahili
                                            </option>
                                        </select>


                                        <button
                                            type="submit"
                                            class="btn-primary rounded-xl px-3 py-2 text-xs font-bold text-white transcribe-submit-btn"
                                        >
                                            <i class="fa-solid fa-wand-magic-sparkles mr-1"></i>
                                            Transcribe & Summarise
                                        </button>

                                    </form>

                                @endif


                                @if($recording->transcript)

                                    <a
                                        href="{{ route('meeting-recordings.transcript.download', $recording) }}"
                                        class="apple-btn rounded-xl px-3 py-2 text-xs font-bold"
                                    >
                                        <i class="fa-solid fa-file-arrow-down mr-1"></i>
                                        Transcript
                                    </a>

                                @endif


                                @if($recording->summary)

                                    <a
                                        href="{{ route('meeting-recordings.summary.download', $recording) }}"
                                        class="apple-btn rounded-xl px-3 py-2 text-xs font-bold"
                                    >
                                        <i class="fa-solid fa-file-arrow-down mr-1"></i>
                                        Summary
                                    </a>

                                @endif


                                @if(
                                    $recording->transcript
                                    || $recording->summary
                                )

                                    <button
                                        type="button"
                                        class="meeting-share-button"
                                        data-meeting-share
                                        data-action="{{ route('meeting-recordings.email-summary', $recording) }}"
                                        data-attendees="{{ $attendeeEmailString }}"
                                        data-has-transcript="{{ $recording->transcript ? '1' : '0' }}"
                                        data-has-summary="{{ $recording->summary ? '1' : '0' }}"
                                    >
                                        <i class="fa-solid fa-paper-plane"></i>
                                        Share with Attendees
                                    </button>

                                @endif

                            </div>


                            {{-- Transcript --}}
                            @if($recording->transcript)

                                <div class="meeting-result-section">

                                    <h4 class="text-sm font-black text-slate-900">
                                        <i class="fa-solid fa-file-lines mr-1 text-blue-600"></i>
                                        Transcript
                                    </h4>


                                    <p class="mt-1 text-xs text-slate-500">
                                        Review and correct the transcript before sharing it with attendees.
                                    </p>


                                    <form
                                        method="POST"
                                        action="{{ route('meeting-recordings.transcript.update', $recording) }}"
                                        class="mt-2"
                                    >
                                        @csrf
                                        @method('PUT')


                                        <textarea
                                            name="transcript"
                                            rows="8"
                                            class="pm-input w-full"
                                        >{{ $recording->transcript }}</textarea>


                                        <button
                                            type="submit"
                                            class="mt-2 apple-btn rounded-xl px-3 py-2 text-xs font-bold"
                                        >
                                            <i class="fa-solid fa-floppy-disk mr-1"></i>
                                            Save Transcript
                                        </button>

                                    </form>

                                </div>

                            @endif


                            {{-- Summary --}}
                            @if($recording->summary)

                                @php
                                    $sum = (array) $recording->summary;
                                @endphp


                                <div class="meeting-result-section">

                                    <h4 class="text-sm font-black text-slate-900">
                                        <i class="fa-solid fa-wand-magic-sparkles mr-1 text-violet-600"></i>
                                        AI Meeting Summary
                                    </h4>


                                    <div class="mt-3 grid gap-3 md:grid-cols-2">


                                        <div class="meeting-summary-box">

                                            <h5 class="text-sm font-black text-slate-800">
                                                Main Discussion Points
                                            </h5>


                                            <ul class="mt-2 list-disc pl-5 text-sm text-slate-600">

                                                @forelse($sum['main_points'] ?? [] as $point)

                                                    <li>
                                                        {{ $point }}
                                                    </li>

                                                @empty

                                                    <li>
                                                        No discussion points yet.
                                                    </li>

                                                @endforelse

                                            </ul>

                                        </div>


                                        <div class="meeting-summary-box">

                                            <h5 class="text-sm font-black text-slate-800">
                                                Decisions
                                            </h5>


                                            <ul class="mt-2 list-disc pl-5 text-sm text-slate-600">

                                                @forelse($sum['decisions'] ?? [] as $decision)

                                                    <li>
                                                        {{ $decision }}
                                                    </li>

                                                @empty

                                                    <li>
                                                        No decisions yet.
                                                    </li>

                                                @endforelse

                                            </ul>

                                        </div>


                                        <div class="meeting-summary-box md:col-span-2">

                                            <h5 class="text-sm font-black text-slate-800">
                                                Action Items
                                            </h5>


                                            <ul class="mt-2 space-y-2 text-sm text-slate-600">

                                                @forelse($sum['action_items'] ?? [] as $item)

                                                    <li class="flex items-start gap-2">

                                                        <i class="fa-solid fa-circle-check mt-1 text-emerald-500"></i>

                                                        <div>

                                                            @if(is_array($item))

                                                                <span>
                                                                    {{ $item['task'] ?? '' }}
                                                                </span>


                                                                @if(!empty($item['assigned_to']))
                                                                    <span class="text-slate-400">
                                                                        —
                                                                        {{ $item['assigned_to'] }}
                                                                    </span>
                                                                @endif


                                                                @if(!empty($item['deadline']))
                                                                    <span class="text-slate-400">
                                                                        ·
                                                                        {{ $item['deadline'] }}
                                                                    </span>
                                                                @endif

                                                            @else

                                                                {{ $item }}

                                                            @endif

                                                        </div>

                                                    </li>

                                                @empty

                                                    <li>
                                                        No action items yet.
                                                    </li>

                                                @endforelse

                                            </ul>

                                        </div>


                                        @if(!empty($sum['questions_for_followup']))

                                            <div class="meeting-summary-box md:col-span-2">

                                                <h5 class="text-sm font-black text-slate-800">
                                                    Follow-up Questions
                                                </h5>


                                                <ul class="mt-2 list-disc pl-5 text-sm text-slate-600">

                                                    @foreach($sum['questions_for_followup'] as $question)

                                                        <li>
                                                            {{ $question }}
                                                        </li>

                                                    @endforeach

                                                </ul>

                                            </div>

                                        @endif

                                    </div>

                                </div>

                            @endif

                            {{-- Segments --}}
                            <div
                                class="meeting-segments-section"
                                data-recording-id="{{ $recording->id }}"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                    <h4 class="text-sm font-black text-slate-900">
                                        <i class="fa-solid fa-scissors mr-1 text-amber-600"></i>
                                        Audio Segments
                                    </h4>
                                    <span class="text-xs text-slate-500" id="segments-count-{{ $recording->id }}">Loading...</span>
                                </div>
                                <div class="meeting-segments-list space-y-2" id="segments-list-{{ $recording->id }}"></div>
                            </div>

                        </article>


                    @empty

                        <x-empty-state
                            icon="fa-solid fa-headphones"
                            title="No recordings yet."
                        >
                            <x-slot name="action">
                                <button
                                    type="button"
                                    data-go-to-recording
                                    class="apple-btn rounded-xl px-4 py-2 text-sm font-bold"
                                >
                                    <i class="fa-solid fa-record-vinyl mr-1"></i>
                                    Record Meeting
                                </button>
                            </x-slot>
                        </x-empty-state>

                    @endforelse


                    @if(method_exists($recordings, 'links'))

                        <div>
                            {{ $recordings->links() }}
                        </div>

                    @endif

                </div>

            </div>

        </div>

    </section>

</div>


{{-- =============================================================
     SHARE MEETING INFORMATION MODAL
     FIX: fixed header/footer + body-only scrolling
============================================================== --}}
<dialog
    id="meeting-share-dialog"
    class="meeting-share-dialog"
>

    <form
        id="meeting-share-form"
        method="POST"
        class="meeting-share-form"
    >
        @csrf


        {{-- Fixed Header --}}
        <header class="meeting-share-header">

            <div class="min-w-0">

                <h3 class="text-lg font-black text-slate-900">
                    Share Meeting Information
                </h3>


                <p class="mt-1 text-xs text-slate-500">
                    Send notes, transcript and AI summary by email.
                </p>

            </div>


            <button
                type="button"
                data-close-meeting-share
                class="meeting-dialog-close shrink-0"
                aria-label="Close share dialog"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </header>


        {{-- ONLY THIS SECTION SCROLLS --}}
        <div class="meeting-share-body">

            <div
                id="meeting-share-validation"
                class="meeting-recording-message error hidden mb-4"
                role="alert"
            ></div>

            <div>

                <label
                    for="meeting-share-emails"
                    class="meeting-label"
                >
                    Recipients
                </label>


                <textarea
                    name="emails"
                    id="meeting-share-emails"
                    rows="3"
                    required
                    class="pm-input mt-1 w-full"
                    placeholder="attendee@example.com, another@example.com"
                ></textarea>


                <p class="mt-1 text-[11px] leading-5 text-slate-400">
                    Attendee email addresses are filled automatically.
                    You can add or remove recipients before sending.
                </p>

            </div>


            <div class="mt-5">

                <label class="meeting-label">
                    Include
                </label>


                <div class="mt-2 grid gap-3 sm:grid-cols-3">


                    <label class="meeting-share-option">

                        <input
                            type="checkbox"
                            id="share-include-notes"
                            name="include_notes"
                            value="1"
                            checked
                        >


                        <span>
                            <i class="fa-solid fa-note-sticky"></i>
                            Notes
                        </span>

                    </label>


                    <label
                        id="share-transcript-option"
                        class="meeting-share-option"
                    >

                        <input
                            type="checkbox"
                            id="share-include-transcript"
                            name="include_transcript"
                            value="1"
                            checked
                        >


                        <span>
                            <i class="fa-solid fa-file-lines"></i>
                            Transcript
                        </span>

                    </label>


                    <label
                        id="share-summary-option"
                        class="meeting-share-option"
                    >

                        <input
                            type="checkbox"
                            id="share-include-summary"
                            name="include_summary"
                            value="1"
                            checked
                        >


                        <span>
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                            AI Summary
                        </span>

                    </label>

                </div>

            </div>


            <div class="mt-5">

                <label
                    for="meeting-share-message"
                    class="meeting-label"
                >
                    Message to Attendees
                </label>


                <textarea
                    id="meeting-share-message"
                    name="message"
                    rows="5"
                    class="pm-input mt-1 w-full"
                    placeholder="Optional message..."
                ></textarea>

            </div>


            <div class="meeting-share-tip">

                <i class="fa-solid fa-shield-halved mt-0.5"></i>

                <p>
                    Review the transcript and AI summary before sharing.
                    Only the sections selected above will be included in the email.
                </p>

            </div>

        </div>


        {{-- Always-visible Footer --}}
        <footer class="meeting-share-footer">

            <button
                type="button"
                data-close-meeting-share
                class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold"
            >
                Cancel
            </button>


            <button
                type="submit"
                id="meeting-share-submit"
                class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold text-white"
            >
                <i class="fa-solid fa-paper-plane mr-1"></i>
                Send Email
            </button>

        </footer>

    </form>

</dialog>


{{-- =============================================================
     EMAIL NOTES MODAL
============================================================== --}}
<dialog
    id="email-notes-dialog"
    class="meeting-simple-dialog"
>

    <form
        method="POST"
        action="{{ route('meetings.notes.email', $meeting) }}"
        class="meeting-simple-dialog-card"
    >
        @csrf


        <header class="meeting-dialog-header">

            <div>

                <h3 class="text-lg font-black text-slate-900">
                    Email Meeting Notes
                </h3>


                <p class="mt-1 text-xs text-slate-500">
                    Attendee addresses are filled automatically.
                </p>

            </div>


            <button
                type="button"
                data-close-email-notes
                class="meeting-dialog-close"
                aria-label="Close email notes dialog"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </header>


        <div class="meeting-dialog-body">

            <label
                for="email-notes-recipients"
                class="meeting-label"
            >
                Recipients
            </label>


            <textarea
                id="email-notes-recipients"
                name="emails"
                rows="3"
                required
                class="pm-input mt-1 w-full"
                placeholder="name@example.com, another@example.com"
            >{{ $attendeeEmailString }}</textarea>


            <p class="mt-1 text-[11px] text-slate-400">
                Add or remove recipients as needed.
            </p>

        </div>


        <footer class="meeting-dialog-footer">

            <button
                type="button"
                data-close-email-notes
                class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold"
            >
                Cancel
            </button>


            <button
                type="submit"
                class="btn-primary rounded-xl px-4 py-2.5 text-sm font-bold text-white"
            >
                <i class="fa-solid fa-paper-plane mr-1"></i>
                Send Notes
            </button>

        </footer>

    </form>

</dialog>


{{-- =============================================================
     DELETE RECORDING MODAL
============================================================== --}}
<dialog
    id="recording-delete-dialog"
    class="meeting-simple-dialog"
>

    <form
        id="recording-delete-form"
        method="POST"
        class="meeting-simple-dialog-card"
    >
        @csrf
        @method('DELETE')


        <header class="meeting-dialog-header">

            <div class="flex items-start gap-3">

                <div class="meeting-danger-icon">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>


                <div>

                    <h3 class="text-lg font-black text-slate-900">
                        Delete Recording?
                    </h3>


                    <p class="mt-1 text-xs text-slate-500">
                        This action cannot be undone.
                    </p>

                </div>

            </div>


            <button
                type="button"
                data-close-recording-delete
                class="meeting-dialog-close"
                aria-label="Close delete dialog"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </header>


        <div class="meeting-dialog-body">

            <div class="rounded-xl border border-rose-100 bg-rose-50 p-4">

                <p
                    id="recording-delete-text"
                    class="text-sm text-rose-800"
                >
                    Delete this recording?
                </p>

            </div>

        </div>


        <footer class="meeting-dialog-footer">

            <button
                type="button"
                data-close-recording-delete
                class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold"
            >
                Cancel
            </button>


            <button
                type="submit"
                class="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-700"
            >
                <i class="fa-solid fa-trash-can mr-1"></i>
                Delete
            </button>

        </footer>

    </form>

</dialog>


{{-- =============================================================
     AUDIO EDITOR MODAL (Cut/Trim like CapCut)
============================================================== --}}
<dialog
    id="audio-editor-dialog"
    class="meeting-audio-editor-dialog"
>

    <div class="meeting-audio-editor-form">
        {{-- Fixed Header --}}
        <header class="meeting-audio-editor-header">

            <div class="min-w-0">

                <h3 class="text-lg font-black text-slate-900">
                    <i class="fa-solid fa-waveform-lines mr-1 text-amber-600"></i>
                    Edit Audio
                </h3>


                <p class="mt-1 text-xs text-slate-500">
                    Select a portion of the audio to create a segment for transcription.
                </p>

            </div>


            <button
                type="button"
                data-close-audio-editor
                class="meeting-dialog-close shrink-0"
                aria-label="Close audio editor"
            >
                <i class="fa-solid fa-xmark"></i>
            </button>

        </header>


        {{-- ONLY THIS SECTION SCROLLS --}}
        <div class="meeting-audio-editor-body">

            <div
                id="audio-editor-validation"
                class="meeting-recording-message error hidden mb-4"
                role="alert"
            ></div>

            {{-- Waveform Visualization --}}
            <div class="meeting-waveform-container">
                <canvas
                    id="audio-waveform-canvas"
                    class="meeting-waveform-canvas"
                ></canvas>
                <div class="meeting-waveform-overlay">
                    <div
                        id="waveform-selection"
                        class="meeting-waveform-selection hidden"
                    ></div>
                    <div
                        id="waveform-playhead"
                        class="meeting-waveform-playhead hidden"
                    ></div>
                </div>
            </div>

            {{-- Time Controls --}}
            <div class="mt-4 grid gap-3 sm:grid-cols-3">

                <div>
                    <label class="meeting-label">Start Time</label>
                    <input
                        type="text"
                        id="segment-start-time"
                        class="pm-input mt-1 w-full text-center font-mono"
                        placeholder="00:00"
                        readonly
                    >
                </div>

                <div>
                    <label class="meeting-label">End Time</label>
                    <input
                        type="text"
                        id="segment-end-time"
                        class="pm-input mt-1 w-full text-center font-mono"
                        placeholder="00:00"
                        readonly
                    >
                </div>

                <div>
                    <label class="meeting-label">Duration</label>
                    <input
                        type="text"
                        id="segment-duration"
                        class="pm-input mt-1 w-full text-center font-mono"
                        placeholder="00:00"
                        readonly
                    >
                </div>

            </div>

            {{-- Manual Time Input --}}
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="meeting-label">Set Start (seconds)</label>
                    <input
                        type="number"
                        id="segment-start-input"
                        class="pm-input mt-1 w-full"
                        min="0"
                        step="0.1"
                        placeholder="e.g., 30.5"
                    >
                </div>
                <div>
                    <label class="meeting-label">Set End (seconds)</label>
                    <input
                        type="number"
                        id="segment-end-input"
                        class="pm-input mt-1 w-full"
                        min="0.1"
                        step="0.1"
                        placeholder="e.g., 60.0"
                    >
                </div>
            </div>

            {{-- Segment Metadata --}}
            <div class="mt-4 space-y-3">
                <div>
                    <label
                        for="segment-title"
                        class="meeting-label"
                    >
                        Segment Title (Optional)
                    </label>
                    <input
                        type="text"
                        id="segment-title"
                        class="pm-input mt-1 w-full"
                        placeholder="e.g., Key Discussion Point"
                        maxlength="255"
                    >
                </div>

                <div>
                    <label
                        for="segment-notes"
                        class="meeting-label"
                    >
                        Notes (Optional)
                    </label>
                    <textarea
                        id="segment-notes"
                        rows="3"
                        class="pm-input mt-1 w-full"
                        placeholder="Add context about this segment..."
                    ></textarea>
                </div>
            </div>

            {{-- Preview Audio --}}
            <div class="mt-4">
                <label class="meeting-label">Preview Selection</label>
                <audio
                    id="segment-preview-audio"
                    controls
                    preload="metadata"
                    class="w-full hidden"
                ></audio>
                <p class="mt-1 text-xs text-slate-500" id="preview-hint">Select a region on the waveform to preview.</p>
            </div>

        </div>


        {{-- Always-visible Footer --}}
        <footer class="meeting-audio-editor-footer">

            <button
                type="button"
                data-close-audio-editor
                class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold"
            >
                Cancel
            </button>


            <button
                type="button"
                id="create-segment-button"
                class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold text-white"
                disabled
            >
                <i class="fa-solid fa-scissors mr-1"></i>
                Create Segment
            </button>

        </footer>

    </div>

</dialog>
<style>

.meeting-tabs{
    display:flex;
    gap:.2rem;
    overflow-x:auto;
    border-bottom:1px solid #e2e8f0;
    padding:0 1rem;
}

.meeting-tab{
    position:relative;
    display:inline-flex;
    align-items:center;
    gap:.45rem;
    white-space:nowrap;
    padding:.9rem .8rem;
    border:0;
    background:transparent;
    font-size:.8rem;
    font-weight:800;
    color:#64748b;
}

.meeting-tab.is-active{
    color:#0f766e;
}

.meeting-tab.is-active::after{
    content:'';
    position:absolute;
    height:2px;
    left:.7rem;
    right:.7rem;
    bottom:0;
    background:#0d9488;
}

.meeting-panel[hidden]{
    display:none!important;
}


/* =============================================================
   Cards
============================================================= */

.meeting-work-card{
    min-width:0;
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
}

.meeting-label{
    display:block;
    font-size:.75rem;
    font-weight:800;
    color:#334155;
}


/* =============================================================
   Recorder
============================================================= */

.meeting-recording-badge{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:6px 10px;
    border-radius:999px;
    background:#f1f5f9;
    color:#475569;
    font-size:.7rem;
    font-weight:900;
}

.meeting-recording-dot{
    width:8px;
    height:8px;
    border-radius:999px;
    background:#94a3b8;
}

.meeting-recording-badge.is-recording{
    background:#fff1f2;
    color:#be123c;
}

.meeting-recording-badge.is-recording .meeting-recording-dot{
    background:#e11d48;
    animation:meetingRecorderPulse 1s infinite;
}

.meeting-recording-badge.is-paused{
    background:#fffbeb;
    color:#92400e;
}

.meeting-recording-badge.is-paused .meeting-recording-dot{
    background:#f59e0b;
}

.meeting-audio-source{
    display:flex;
    align-items:center;
    gap:10px;
    padding:12px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}

.meeting-audio-source-icon{
    display:grid;
    place-items:center;
    width:36px;
    height:36px;
    flex:0 0 36px;
    border:1px solid #e2e8f0;
    border-radius:10px;
    background:#fff;
    color:#64748b;
}

.meeting-audio-source.is-active{
    border-color:#86efac;
    background:#f0fdf4;
}

.meeting-audio-source.is-active .meeting-audio-source-icon{
    border-color:#86efac;
    color:#047857;
}

.meeting-recording-help{
    display:flex;
    gap:9px;
    margin-top:14px;
    padding:12px 14px;
    border:1px solid #bae6fd;
    border-radius:12px;
    background:#f0f9ff;
    color:#075985;
    font-size:.75rem;
    line-height:1.5;
}

.meeting-consent{
    display:flex;
    align-items:flex-start;
    gap:9px;
    margin-top:14px;
    padding:12px 14px;
    border:1px solid #fde68a;
    border-radius:12px;
    background:#fffbeb;
    color:#92400e;
    font-size:.78rem;
    line-height:1.5;
}

.meeting-control{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    min-height:40px;
    padding:9px 13px;
    border:0;
    border-radius:10px;
    font-size:.78rem;
    font-weight:800;
}

.meeting-control-start{
    background:#e11d48;
    color:#fff;
}

.meeting-control-pause{
    background:#fef3c7;
    color:#92400e;
}

.meeting-control-resume{
    background:#d1fae5;
    color:#065f46;
}

.meeting-control-stop{
    background:#0f172a;
    color:#fff;
}

.meeting-control:disabled{
    cursor:not-allowed;
    opacity:.55;
}

.meeting-timer{
    margin-left:auto;
    padding:8px 12px;
    border-radius:9px;
    background:#f1f5f9;
    color:#0f172a;
    font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
    font-size:.88rem;
    font-weight:900;
}

.meeting-recording-message{
    margin-top:12px;
    padding:10px 12px;
    border-radius:10px;
    font-size:.75rem;
    font-weight:700;
}

.meeting-recording-message.success{
    border:1px solid #a7f3d0;
    background:#ecfdf5;
    color:#047857;
}

.meeting-recording-message.warning{
    border:1px solid #fde68a;
    background:#fffbeb;
    color:#92400e;
}

.meeting-recording-message.error{
    border:1px solid #fecdd3;
    background:#fff1f2;
    color:#be123c;
}

.meeting-recording-message.info{
    border:1px solid #bfdbfe;
    background:#eff6ff;
    color:#1d4ed8;
}

.meeting-status-pill{
    display:inline-flex;
    align-items:center;
    padding:3px 8px;
    border-radius:999px;
    background:#f1f5f9;
    color:#475569;
    font-size:.7rem;
    font-weight:800;
}

.meeting-language-select{
    width:auto;
    min-width:130px;
    min-height:36px!important;
    padding:.35rem .6rem!important;
    font-size:.72rem!important;
}


/* =============================================================
   Transcript / Summary
============================================================= */

.meeting-result-section{
    margin-top:16px;
    padding-top:14px;
    border-top:1px solid #e2e8f0;
}

.meeting-summary-box{
    min-width:0;
    padding:13px;
    border-radius:12px;
    background:#f8fafc;
}

.meeting-share-button{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    min-height:35px;
    padding:8px 11px;
    border:0;
    border-radius:10px;
    background:#0f766e;
    color:#fff;
    font-size:.75rem;
    font-weight:800;
}

.meeting-share-button:hover{
    background:#115e59;
}

.meeting-delete-button{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 10px;
    border:1px solid #fecdd3;
    border-radius:10px;
    background:#fff1f2;
    color:#be123c;
    font-size:.72rem;
    font-weight:800;
}


/* =============================================================
   SHARE MODAL
   Fixed header + body scrolling + always-visible footer
============================================================= */

.meeting-share-dialog{
    width:min(94vw,820px);
    max-width:820px;

    height:min(88dvh,760px);
    max-height:88dvh;

    padding:0;
    border:0;
    border-radius:20px;
    background:transparent;

    overflow:hidden;
}

.meeting-share-dialog[open]{
    display:block;
}

.meeting-share-dialog::backdrop{
    background:rgba(15,23,42,.65);
    backdrop-filter:blur(3px);
}

.meeting-share-form{
    display:grid;

    grid-template-rows:
        auto
        minmax(0,1fr)
        auto;

    width:100%;
    height:100%;
    max-height:88dvh;

    overflow:hidden;

    border-radius:20px;
    background:#fff;

    box-shadow:
        0 28px 80px
        rgba(15,23,42,.35);
}


/*
 * Header never scrolls
 */
.meeting-share-header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;

    min-height:0;
    flex-shrink:0;

    padding:18px 20px;

    border-bottom:1px solid #e2e8f0;

    background:#fff;
}


/*
 * IMPORTANT:
 * this is the ONLY scrolling area in the share modal.
 */
.meeting-share-body{
    min-height:0;
    overflow-y:auto;
    overflow-x:hidden;

    overscroll-behavior:contain;
    -webkit-overflow-scrolling:touch;

    padding:20px;

    background:#fff;

    scrollbar-width:thin;
    scrollbar-color:#94a3b8 transparent;
}

.meeting-share-body::-webkit-scrollbar{
    width:7px;
}

.meeting-share-body::-webkit-scrollbar-track{
    background:transparent;
}

.meeting-share-body::-webkit-scrollbar-thumb{
    border-radius:999px;
    background:#94a3b8;
}


/*
 * Footer remains visible at all times.
 */
.meeting-share-footer{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:10px;

    min-height:0;
    flex-shrink:0;

    padding:14px 20px;

    border-top:1px solid #e2e8f0;

    background:#fff;

    box-shadow:
        0 -8px 18px
        rgba(15,23,42,.04);
}

.meeting-share-option{
    display:flex;
    align-items:center;
    gap:8px;

    min-height:62px;

    padding:11px 12px;

    border:1px solid #e2e8f0;
    border-radius:12px;

    background:#f8fafc;

    cursor:pointer;
}

.meeting-share-option input{
    flex:0 0 auto;
}

.meeting-share-option span{
    display:flex;
    align-items:center;
    gap:7px;

    min-width:0;

    font-size:.75rem;
    font-weight:800;
    color:#475569;
}

.meeting-share-option.is-disabled{
    opacity:.45;
    cursor:not-allowed;
}

.meeting-share-tip{
    display:flex;
    gap:9px;

    margin-top:20px;
    margin-bottom:4px;

    padding:12px 14px;

    border:1px solid #e2e8f0;
    border-radius:12px;

    background:#f8fafc;

    color:#64748b;

    font-size:.73rem;
    line-height:1.5;
}

.meeting-share-dialog .pm-input{
    width:100%;
}

.meeting-share-dialog textarea{
    max-width:100%;
    resize:vertical;
}


/* =============================================================
   Small Generic Meeting Dialogs
============================================================= */

.meeting-simple-dialog{
    width:min(94vw,520px);
    max-width:520px;
    max-height:90dvh;

    padding:0;
    border:0;
    border-radius:18px;

    background:transparent;
}

.meeting-simple-dialog::backdrop{
    background:rgba(15,23,42,.62);
    backdrop-filter:blur(2px);
}

.meeting-simple-dialog-card{
    display:grid;
    grid-template-rows:auto minmax(0,1fr) auto;

    max-height:90dvh;

    overflow:hidden;

    border-radius:18px;
    background:#fff;

    box-shadow:
        0 24px 70px
        rgba(15,23,42,.3);
}

.meeting-dialog-header,
.meeting-dialog-footer{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;

    flex-shrink:0;

    padding:16px 18px;

    background:#fff;
}

.meeting-dialog-header{
    border-bottom:1px solid #e2e8f0;
}

.meeting-dialog-footer{
    justify-content:flex-end;
    border-top:1px solid #e2e8f0;
}

.meeting-dialog-body{
    min-height:0;
    overflow-y:auto;
    padding:18px;
}

.meeting-dialog-close{
    display:grid;
    place-items:center;

    width:36px;
    height:36px;

    flex:0 0 36px;

    border:1px solid #e2e8f0;
    border-radius:10px;

    background:#fff;
    color:#475569;
}

.meeting-dialog-close:hover{
    background:#f8fafc;
}

.meeting-danger-icon{
    display:grid;
    place-items:center;

    width:40px;
    height:40px;

    flex:0 0 40px;

    border-radius:12px;

    background:#fff1f2;
    color:#e11d48;
}


/* =============================================================
   Animations
============================================================= */

@keyframes meetingRecorderPulse{
    0%,100%{
        opacity:1;
    }

    50%{
        opacity:.3;
    }
}


/* =============================================================
   Mobile
============================================================= */

@media(max-width:640px){

    .meeting-tabs{
        padding:0 .5rem;
    }

    .meeting-tab{
        padding:.8rem .65rem;
        font-size:.74rem;
    }

    .meeting-timer{
        width:100%;
        margin-left:0;
        text-align:center;
    }

    .meeting-control{
        flex:1 1 calc(50% - 5px);
    }


    /*
     * Give the modal a little more vertical space on phones,
     * while keeping the footer outside the scrolling body.
     */
    .meeting-share-dialog{
        width:96vw;
        height:92dvh;
        max-height:92dvh;
        border-radius:16px;
    }

    .meeting-share-form{
        max-height:92dvh;
        border-radius:16px;
    }

    .meeting-share-header{
        padding:14px 15px;
    }

    .meeting-share-body{
        padding:15px;
    }

    .meeting-share-footer{
        padding:
            12px
            15px
            max(
                12px,
                env(safe-area-inset-bottom)
            );
    }

    .meeting-share-footer button{
        flex:1;
    }

    .meeting-share-option{
        min-height:54px;
    }


    .meeting-simple-dialog{
        width:96vw;
    }

    .meeting-dialog-header,
    .meeting-dialog-footer{
        padding:14px;
    }

    .meeting-dialog-body{
        padding:14px;
    }

}

.meeting-edit-audio-button{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    min-height:36px;
    padding:8px 12px;
    border:0;
    border-radius:10px;
    background:#fef3c7;
    color:#92400e;
    font-size:.75rem;
    font-weight:800;
}

.meeting-edit-audio-button:hover{
    background:#fde68a;
}

.meeting-segments-section{
    margin-top:16px;
    padding-top:14px;
    border-top:1px solid #e2e8f0;
}

.meeting-segments-list{
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(250px, 1fr));
    gap:12px;
}

.meeting-segment-item{
    display:flex;
    flex-direction:column;
    gap:8px;
    padding:12px;
    border:1px solid #e2e8f0;
    border-radius:10px;
    background:#f8fafc;
    min-height:120px;
}

.meeting-segment-item.playing{
    border-color:#f59e0b;
    background:#fffbeb;
}

.meeting-segment-info{
    flex:1;
    min-width:0;
}

.meeting-segment-title{
    display:flex;
    align-items:center;
    gap:6px;
    font-size:.75rem;
    font-weight:800;
    color:#1e293b;
}

.meeting-segment-times{
    display:flex;
    align-items:center;
    gap:4px;
    margin-top:2px;
    font-size:.65rem;
    color:#64748b;
    font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
}

.meeting-segment-actions{
    display:flex;
    flex-wrap:wrap;
    gap:4px;
}

.meeting-segment-action{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:4px;
    min-height:30px;
    padding:4px 8px;
    border:1px solid #e2e8f0;
    border-radius:8px;
    background:#fff;
    color:#475569;
    font-size:.65rem;
    font-weight:700;
}

.meeting-segment-action:hover{
    background:#f1f5f9;
}

.meeting-segment-action.transcribe{
    border-color:#bfdbfe;
    color:#1d4ed8;
}

.meeting-segment-action.transcribe:hover{
    background:#eff6ff;
}

.meeting-segment-action.summarize{
    border-color:#ddd6fe;
    color:#7c3aed;
}

.meeting-segment-action.summarize:hover{
    background:#f5f3ff;
}

.meeting-segment-action.delete{
    border-color:#fecdd3;
    color:#be123c;
}

.meeting-segment-action.delete:hover{
    background:#fff1f2;
}

.meeting-segment-action.play{
    border-color:#a7f3d0;
    color:#047857;
}

.meeting-segment-action.play:hover{
    background:#ecfdf5;
}

/* =============================================================
   Audio Editor Dialog
============================================================= */

.meeting-audio-editor-dialog{
    width:min(96vw,1000px);
    max-width:1000px;
    max-height:90dvh;

    padding:0;
    border:0;
    border-radius:20px;

    background:transparent;
}

.meeting-audio-editor-dialog[open]{
    display:block;
}

.meeting-audio-editor-dialog::backdrop{
    background:rgba(15,23,42,.65);
    backdrop-filter:blur(3px);
}

.meeting-audio-editor-form{
    display:grid;

    grid-template-rows:
        auto
        minmax(0,1fr)
        auto;

    width:100%;
    height:100%;
    max-height:90dvh;

    overflow:hidden;

    border-radius:20px;
    background:#fff;

    box-shadow:
        0 28px 80px
        rgba(15,23,42,.35);
}

.meeting-audio-editor-header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:1rem;

    min-height:0;
    flex-shrink:0;

    padding:18px 20px;

    border-bottom:1px solid #e2e8f0;

    background:#fff.
}

.meeting-audio-editor-body{
    min-height:0;
    overflow-y:auto;
    overflow-x:hidden;

    overscroll-behavior:contain;
    -webkit-overflow-scrolling:touch;

    padding:20px;

    background:#fff;

    scrollbar-width:thin;
    scrollbar-color:#94a3b8 transparent;
}

.meeting-audio-editor-body::-webkit-scrollbar{
    width:7px;
}

.meeting-audio-editor-body::-webkit-scrollbar-track{
    background:transparent;
}

.meeting-audio-editor-body::-webkit-scrollbar-thumb{
    border-radius:999px;
    background:#94a3b8.
}

.meeting-audio-editor-footer{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:10px;

    min-height:0;
    flex-shrink:0;

    padding:14px 20px;

    border-top:1px solid #e2e8f0;

    background:#fff;

    box-shadow:
        0 -8px 18px
        rgba(15,23,42,.04).
}

/* =============================================================
   Waveform
============================================================= */

.meeting-waveform-container{
    position:relative;
    width:100%;
    height:180px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
    overflow:hidden;
}

.meeting-waveform-canvas{
    display:block;
    width:100%;
    height:100%;
    touch-action:none;
}

.meeting-waveform-overlay{
    position:absolute;
    inset:0;
    pointer-events:none;
}

.meeting-waveform-selection{
    position:absolute;
    top:0;
    bottom:0.
    background:rgba(249,115,22,.2);
    border-left:2px solid #f97316;
    border-right:2px solid #f97316.
    pointer-events:none.
}

.meeting-waveform-selection.active{
    pointer-events:auto.
}

.meeting-waveform-playhead{
    position:absolute;
    top:0;
    bottom:0.
    width:2px.
    background:#f97316.
    pointer-events:none.
    z-index:10.
}

.meeting-waveform-time-markers{
    display:flex.
    justify-content:space-between.
    padding:4px 8px.
    font-size:.6rem.
    color:#64748b.
    font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace.
}

@media(max-width:640px){

    .meeting-audio-editor-dialog{
        width:98vw.
        height:95dvh.
        max-height:95dvh.
        border-radius:16px.
    }

    .meeting-audio-editor-form{
        max-height:95dvh.
        border-radius:16px.
    }

    .meeting-audio-editor-header{
        padding:14px 15px.
    }

    .meeting-audio-editor-body{
        padding:15px.
    }

    .meeting-audio-editor-footer{
        padding:
            12px
            15px
            max(
                12px,
                env(safe-area-inset-bottom)
            ).
    }

    .meeting-audio-editor-footer button{
        flex:1.
    }

    .meeting-waveform-container{
        height:140px.
    }

    .meeting-segments-list{
        grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));
    }
}

</style>


{{-- =============================================================
     JAVASCRIPT
============================================================== --}}
<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {
        'use strict';


        /*
        |--------------------------------------------------------------------------
        | Helpers
        |--------------------------------------------------------------------------
        */

        function openDialog(
            dialog
        ) {
            if (!dialog) {
                return;
            }

            if (
                typeof dialog.showModal
                === 'function'
            ) {
                if (!dialog.open) {
                    dialog.showModal();
                }

                return;
            }

            dialog.setAttribute(
                'open',
                'open'
            );
        }


        function closeDialog(
            dialog
        ) {
            if (!dialog) {
                return;
            }

            if (
                typeof dialog.close
                === 'function'
                && dialog.open
            ) {
                dialog.close();

                return;
            }

            dialog.removeAttribute(
                'open'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Tabs
        |--------------------------------------------------------------------------
        */

        const tabs =
            Array.from(
                document.querySelectorAll(
                    '[data-meeting-tab]'
                )
            );

        const panels =
            Array.from(
                document.querySelectorAll(
                    '[data-meeting-panel]'
                )
            );


        window.activateMeetingTab =
            function (
                name
            ) {
                tabs.forEach(
                    function (tab) {
                        const active =
                            tab.dataset.meetingTab
                            === name;

                        tab.classList.toggle(
                            'is-active',
                            active
                        );

                        tab.setAttribute(
                            'aria-selected',
                            active
                                ? 'true'
                                : 'false'
                        );
                    }
                );


                panels.forEach(
                    function (panel) {
                        panel.hidden =
                            panel.dataset.meetingPanel
                            !== name;
                    }
                );


                if (
                    name === 'notes'
                ) {
                    history.replaceState(
                        null,
                        '',
                        location.pathname
                        + location.search
                    );
                } else {
                    history.replaceState(
                        null,
                        '',
                        '#'
                        + name
                    );
                }

                // Load segments when transcripts-summary tab is activated
                if (name === 'transcripts-summary') {
                    document.querySelectorAll('.meeting-segments-section').forEach(function(section) {
                        const recordingId = section.dataset.recordingId;
                        if (recordingId) {
                            refreshSegments(recordingId);
                        }
                    });
                }
            };


        tabs.forEach(
            function (tab) {
                tab.addEventListener(
                    'click',
                    function () {
                        window.activateMeetingTab(
                            tab.dataset.meetingTab
                        );
                    }
                );
            }
        );


        const initialHash =
            location.hash
                .replace(
                    '#',
                    ''
                );


        window.activateMeetingTab(
            [
                'record-meeting',
                'transcripts-summary'
            ].includes(
                initialHash
            )
                ? initialHash
                : 'notes'
        );


        /*
        |--------------------------------------------------------------------------
        | Shared click handling
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'click',
            function (event) {

                /*
                 * Open share modal.
                 */
                const shareButton =
                    event.target.closest(
                        '[data-meeting-share]'
                    );


                if (shareButton) {
                    event.preventDefault();

                    const dialog =
                        document.getElementById(
                            'meeting-share-dialog'
                        );

                    const form =
                        document.getElementById(
                            'meeting-share-form'
                        );

                    const emails =
                        document.getElementById(
                            'meeting-share-emails'
                        );

                    const message =
                        document.getElementById(
                            'meeting-share-message'
                        );

                    const notes =
                        document.getElementById(
                            'share-include-notes'
                        );

                    const transcript =
                        document.getElementById(
                            'share-include-transcript'
                        );

                    const summary =
                        document.getElementById(
                            'share-include-summary'
                        );

                    const transcriptOption =
                        document.getElementById(
                            'share-transcript-option'
                        );

                    const summaryOption =
                        document.getElementById(
                            'share-summary-option'
                        );


                    if (
                        !dialog
                        || !form
                        || !emails
                    ) {
                        console.error(
                            'Meeting share modal elements were not found.'
                        );

                        return;
                    }


                    const action =
                        shareButton.dataset.action
                        || '';

                    const attendees =
                        shareButton.dataset.attendees
                        || '';

                    const hasTranscript =
                        shareButton
                            .dataset
                            .hasTranscript
                        === '1';

                    const hasSummary =
                        shareButton
                            .dataset
                            .hasSummary
                        === '1';


                    if (!action) {
                        console.error(
                            'Meeting share action URL is missing.'
                        );

                        return;
                    }


                    form.action =
                        action;


                    emails.value =
                        attendees;


                    if (message) {
                        message.value =
                            '';
                    }

                    const validation =
                        document.getElementById(
                            'meeting-share-validation'
                        );

                    if (validation) {
                        validation.textContent =
                            '';

                        validation.classList.add(
                            'hidden'
                        );
                    }


                    if (notes) {
                        notes.checked =
                            true;
                    }


                    if (transcript) {
                        transcript.disabled =
                            !hasTranscript;

                        transcript.checked =
                            hasTranscript;
                    }


                    transcriptOption
                        ?.classList
                        .toggle(
                            'is-disabled',
                            !hasTranscript
                        );


                    if (summary) {
                        summary.disabled =
                            !hasSummary;

                        summary.checked =
                            hasSummary;
                    }


                    summaryOption
                        ?.classList
                        .toggle(
                            'is-disabled',
                            !hasSummary
                        );


                    /*
                     * Critical scroll reset:
                     * always open the modal at the top of the body.
                     */
                    const shareBody =
                        dialog.querySelector(
                            '.meeting-share-body'
                        );

                    if (shareBody) {
                        shareBody.scrollTop =
                            0;
                    }


                    openDialog(
                        dialog
                    );


                    window.setTimeout(
                        function () {
                            emails.focus();

                            if (shareBody) {
                                shareBody.scrollTop =
                                    0;
                            }
                        },
                        30
                    );

                    return;
                }


                /*
                 * Close share modal.
                 */
                if (
                    event.target.closest(
                        '[data-close-meeting-share]'
                    )
                ) {
                    event.preventDefault();

                    closeDialog(
                        document.getElementById(
                            'meeting-share-dialog'
                        )
                    );

                    return;
                }


                /*
                 * Open delete modal.
                 */
                const deleteButton =
                    event.target.closest(
                        '[data-recording-delete]'
                    );


                if (deleteButton) {
                    event.preventDefault();

                    const dialog =
                        document.getElementById(
                            'recording-delete-dialog'
                        );

                    const form =
                        document.getElementById(
                            'recording-delete-form'
                        );

                    const text =
                        document.getElementById(
                            'recording-delete-text'
                        );


                    if (
                        !dialog
                        || !form
                    ) {
                        console.error(
                            'Recording delete modal elements were not found.'
                        );

                        return;
                    }


                    const action =
                        deleteButton.dataset.action
                        || '';

                    const label =
                        deleteButton.dataset.label
                        || 'this recording';


                    if (!action) {
                        console.error(
                            'Delete action URL is missing.'
                        );

                        return;
                    }


                    form.action =
                        action;


                    if (text) {
                        text.textContent =
                            'Delete '
                            + label
                            + '? The audio, transcript and AI summary will be permanently removed.';
                    }


                    openDialog(
                        dialog
                    );

                    return;
                }


                /*
                 * Close delete modal.
                 */
                if (
                    event.target.closest(
                        '[data-close-recording-delete]'
                    )
                ) {
                    event.preventDefault();

                    closeDialog(
                        document.getElementById(
                            'recording-delete-dialog'
                        )
                    );

                    return;
                }


                /*
                 * Open Email Notes.
                 */
                if (
                    event.target.closest(
                        '[data-open-email-notes]'
                    )
                ) {
                    event.preventDefault();

                    openDialog(
                        document.getElementById(
                            'email-notes-dialog'
                        )
                    );

                    return;
                }


                /*
                 * Close Email Notes.
                 */
                if (
                    event.target.closest(
                        '[data-close-email-notes]'
                    )
                ) {
                    event.preventDefault();

                    closeDialog(
                        document.getElementById(
                            'email-notes-dialog'
                        )
                    );

                    return;
                }


                /*
                 * Empty-recordings CTA.
                 */
                if (
                    event.target.closest(
                        '[data-go-to-recording]'
                    )
                ) {
                    event.preventDefault();

                    window.activateMeetingTab(
                        'record-meeting'
                    );
                }
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Close dialogs when clicking backdrop
        |--------------------------------------------------------------------------
        */

        [
            'meeting-share-dialog',
            'recording-delete-dialog',
            'email-notes-dialog'
        ].forEach(
            function (id) {

                const dialog =
                    document.getElementById(
                        id
                    );

                dialog?.addEventListener(
                    'click',
                    function (event) {
                        if (
                            event.target
                            === dialog
                        ) {
                            closeDialog(
                                dialog
                            );
                        }
                    }
                );
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Share form submission
        |--------------------------------------------------------------------------
        */

        const shareForm =
            document.getElementById(
                'meeting-share-form'
            );


        shareForm?.addEventListener(
            'submit',
            function (event) {

                const notes =
                    document.getElementById(
                        'share-include-notes'
                    );

                const transcript =
                    document.getElementById(
                        'share-include-transcript'
                    );

                const summary =
                    document.getElementById(
                        'share-include-summary'
                    );


                const selected =
                    (
                        notes?.checked
                        || transcript?.checked
                        || summary?.checked
                    );


                if (!selected) {
                    event.preventDefault();

                    const validation =
                        document.getElementById(
                            'meeting-share-validation'
                        );

                    if (validation) {
                        validation.textContent =
                            'Select at least one item to share.';

                        validation.classList.remove(
                            'hidden'
                        );
                    }

                    const body =
                        document.querySelector(
                            '#meeting-share-dialog .meeting-share-body'
                        );


                    if (body) {
                        body.scrollTo({
                            top:0,
                            behavior:'smooth'
                        });
                    }
                }
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Recorder
        |--------------------------------------------------------------------------
        */

        const startButton =
            document.getElementById(
                'start-recording'
            );

        const pauseButton =
            document.getElementById(
                'pause-recording'
            );

        const resumeButton =
            document.getElementById(
                'resume-recording'
            );

        const stopButton =
            document.getElementById(
                'stop-recording'
            );

        const consent =
            document.getElementById(
                'record-consent'
            );

        const recordingStatus =
            document.getElementById(
                'recording-status'
            );

        const recordingTimer =
            document.getElementById(
                'recording-timer'
            );

        const recordingBadge =
            document.getElementById(
                'recording-badge'
            );

        const recordingBadgeLabel =
            document.getElementById(
                'recording-badge-label'
            );

        const recordingMessage =
            document.getElementById(
                'recording-message'
            );

        const microphoneStatus =
            document.getElementById(
                'microphone-status'
            );

        const meetingAudioStatus =
            document.getElementById(
                'meeting-audio-status'
            );

        const microphoneCard =
            document.getElementById(
                'microphone-source-card'
            );

        const meetingAudioCard =
            document.getElementById(
                'meeting-audio-source-card'
            );


        let recorder =
            null;

        let displayStream =
            null;

        let microphoneStream =
            null;

        let mixedStream =
            null;

        let audioContext =
            null;

        let recorderChunks =
            [];

        let recordingId =
            null;

        let elapsedSeconds =
            0;

        let timerHandle =
            null;

        let recorderStopping =
            false;


        function showRecordingMessage(
            text,
            type = 'info'
        ) {
            if (!recordingMessage) {
                return;
            }


            recordingMessage.textContent =
                text;


            recordingMessage.className =
                'meeting-recording-message '
                + type;
        }


        function clearRecordingMessage() {
            if (!recordingMessage) {
                return;
            }


            recordingMessage.textContent =
                '';


            recordingMessage.className =
                'hidden meeting-recording-message';
        }


        function setRecorderState(
            state
        ) {
            startButton
                ?.classList
                .toggle(
                    'hidden',
                    state !== 'idle'
                );


            pauseButton
                ?.classList
                .toggle(
                    'hidden',
                    state !== 'recording'
                );


            resumeButton
                ?.classList
                .toggle(
                    'hidden',
                    state !== 'paused'
                );


            stopButton
                ?.classList
                .toggle(
                    'hidden',
                    ![
                        'recording',
                        'paused',
                        'saving'
                    ].includes(state)
                );


            if (stopButton) {
                stopButton.disabled =
                    state === 'saving';
            }


            recordingBadge
                ?.classList
                .remove(
                    'is-recording',
                    'is-paused'
                );


            let text =
                'Not recording';


            if (
                state === 'recording'
            ) {
                text =
                    'Recording';

                recordingBadge
                    ?.classList
                    .add(
                        'is-recording'
                    );
            }


            if (
                state === 'paused'
            ) {
                text =
                    'Paused';

                recordingBadge
                    ?.classList
                    .add(
                        'is-paused'
                    );
            }


            if (
                state === 'saving'
            ) {
                text =
                    'Saving...';
            }


            if (recordingBadgeLabel) {
                recordingBadgeLabel.textContent =
                    text;
            }
        }


        function setAudioSourceState(
            microphoneActive,
            meetingActive
        ) {
            microphoneCard
                ?.classList
                .toggle(
                    'is-active',
                    microphoneActive
                );


            meetingAudioCard
                ?.classList
                .toggle(
                    'is-active',
                    meetingActive
                );


            if (microphoneStatus) {
                microphoneStatus.textContent =
                    microphoneActive
                        ? 'Captured'
                        : 'Waiting';
            }


            if (meetingAudioStatus) {
                meetingAudioStatus.textContent =
                    meetingActive
                        ? 'Captured'
                        : 'Waiting';
            }
        }


        function formatRecordingTime(
            seconds
        ) {
            const hours =
                Math.floor(
                    seconds / 3600
                );

            const minutes =
                Math.floor(
                    (
                        seconds
                        % 3600
                    ) / 60
                );

            const secs =
                seconds % 60;


            return [
                hours,
                minutes,
                secs
            ]
                .map(
                    value =>
                        String(value)
                            .padStart(
                                2,
                                '0'
                            )
                )
                .join(':');
        }


        function renderRecordingTimer() {
            if (recordingTimer) {
                recordingTimer.textContent =
                    formatRecordingTime(
                        elapsedSeconds
                    );
            }
        }


        function startRecordingTimer() {
            stopRecordingTimer();


            timerHandle =
                window.setInterval(
                    function () {
                        elapsedSeconds++;

                        renderRecordingTimer();
                    },
                    1000
                );
        }


        function stopRecordingTimer() {
            if (timerHandle) {
                window.clearInterval(
                    timerHandle
                );

                timerHandle =
                    null;
            }
        }


        function preferredMimeType() {
            const types = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'video/webm;codecs=opus',
                'video/webm',
                'audio/ogg;codecs=opus'
            ];


            return types.find(
                function (type) {
                    return (
                        window.MediaRecorder
                        && MediaRecorder
                            .isTypeSupported(
                                type
                            )
                    );
                }
            ) || '';
        }


        async function releaseMedia() {
            stopRecordingTimer();


            [
                displayStream,
                microphoneStream,
                mixedStream
            ].forEach(
                function (stream) {
                    stream
                        ?.getTracks()
                        .forEach(
                            function (track) {
                                try {
                                    track.stop();
                                } catch (_) {
                                    //
                                }
                            }
                        );
                }
            );


            displayStream =
                null;

            microphoneStream =
                null;

            mixedStream =
                null;


            if (audioContext) {
                try {
                    await audioContext.close();
                } catch (_) {
                    //
                }
            }


            audioContext =
                null;


            setAudioSourceState(
                false,
                false
            );
        }


        async function createRecordingSession() {
            const response =
                await fetch(
                    @json(route('meetings.recordings.store', $meeting)),
                    {
                        method:
                            'POST',

                        credentials:
                            'same-origin',

                        headers: {
                            'X-CSRF-TOKEN':
                                @json(csrf_token()),

                            'Accept':
                                'application/json',

                            'Content-Type':
                                'application/json'
                        },

                        body:
                            JSON.stringify({
                                consent:1
                            })
                    }
                );


            const payload =
                await response
                    .json()
                    .catch(
                        function () {
                            return {};
                        }
                    );


            if (!response.ok) {
                throw new Error(
                    payload.message
                    || 'Could not start recording session.'
                );
            }


            const id =
                Number(
                    payload.recording_id
                    || payload.recording?.id
                    || 0
                );


            if (!id) {
                throw new Error(
                    'The server did not return a recording ID.'
                );
            }


            return id;
        }


        async function saveRecording(
            blob
        ) {
            if (!recordingId) {
                throw new Error(
                    'The recording session could not be identified.'
                );
            }


            const extension =
                blob.type.includes(
                    'ogg'
                )
                    ? 'ogg'
                    : 'webm';


            const formData =
                new FormData();


            formData.append(
                'recording',
                blob,
                'meeting-recording-'
                + Date.now()
                + '.'
                + extension
            );


            formData.append(
                'duration_seconds',
                String(
                    Math.max(
                        1,
                        elapsedSeconds
                    )
                )
            );


            formData.append(
                'capture_type',
                'mixed'
            );


            formData.append(
                'meeting_audio_captured',
                '1'
            );


            formData.append(
                'microphone_audio_captured',
                '1'
            );


            const url =
                @json(
                    route(
                        'meeting-recordings.stop',
                        [
                            'recording' =>
                                '__ID__'
                        ]
                    )
                )
                .replace(
                    '__ID__',
                    String(recordingId)
                );


            const response =
                await fetch(
                    url,
                    {
                        method:
                            'POST',

                        credentials:
                            'same-origin',

                        headers: {
                            'X-CSRF-TOKEN':
                                @json(csrf_token()),

                            'Accept':
                                'application/json'
                        },

                        body:
                            formData
                    }
                );


            const payload =
                await response
                    .json()
                    .catch(
                        function () {
                            return {};
                        }
                    );


            if (!response.ok) {
                throw new Error(
                    payload.message
                    || 'Could not save recording.'
                );
            }


            return payload;
        }


        async function startMeetingRecording() {
            clearRecordingMessage();


            if (
                !consent
                || !consent.checked
            ) {
                showRecordingMessage(
                    'Confirm recording consent before starting.',
                    'warning'
                );

                return;
            }


            if (
                !navigator.mediaDevices
                || !navigator.mediaDevices.getDisplayMedia
                || !navigator.mediaDevices.getUserMedia
                || !window.MediaRecorder
            ) {
                showRecordingMessage(
                    'Your browser does not support complete meeting recording. Please use a recent version of Chrome or Edge.',
                    'error'
                );

                return;
            }


            try {
                await releaseMedia();


                recorderChunks =
                    [];

                recordingId =
                    null;

                elapsedSeconds =
                    0;

                recorderStopping =
                    false;


                renderRecordingTimer();


                if (recordingStatus) {
                    recordingStatus.textContent =
                        'Select the meeting browser tab and enable Share tab audio.';
                }


                /*
                 * Meeting/tab/system audio.
                 */
                displayStream =
                    await navigator
                        .mediaDevices
                        .getDisplayMedia({
                            video:true,
                            audio:true
                        });


                const meetingAudioTracks =
                    displayStream
                        .getAudioTracks();


                if (
                    !meetingAudioTracks.length
                ) {
                    throw new Error(
                        'Meeting audio is not being captured. Select the meeting tab and enable Share tab audio.'
                    );
                }


                /*
                 * User microphone / headset.
                 */
                microphoneStream =
                    await navigator
                        .mediaDevices
                        .getUserMedia({
                            video:false,

                            audio:{
                                echoCancellation:true,
                                noiseSuppression:true,
                                autoGainControl:true
                            }
                        });


                if (
                    !microphoneStream
                        .getAudioTracks()
                        .length
                ) {
                    throw new Error(
                        'Microphone audio could not be captured.'
                    );
                }


                /*
                 * Mix both streams.
                 */
                const AudioContextClass =
                    window.AudioContext
                    || window.webkitAudioContext;


                if (!AudioContextClass) {
                    throw new Error(
                        'Your browser does not support audio mixing.'
                    );
                }


                audioContext =
                    new AudioContextClass();


                if (
                    audioContext.state
                    === 'suspended'
                ) {
                    await audioContext.resume();
                }


                const destination =
                    audioContext
                        .createMediaStreamDestination();


                const meetingSource =
                    audioContext
                        .createMediaStreamSource(
                            new MediaStream(
                                meetingAudioTracks
                            )
                        );


                const microphoneSource =
                    audioContext
                        .createMediaStreamSource(
                            microphoneStream
                        );


                meetingSource.connect(
                    destination
                );


                microphoneSource.connect(
                    destination
                );


                mixedStream =
                    new MediaStream(
                        destination.stream
                            .getAudioTracks()
                    );


                if (
                    !mixedStream
                        .getAudioTracks()
                        .length
                ) {
                    throw new Error(
                        'The meeting and microphone audio could not be combined.'
                    );
                }


                recordingId =
                    await createRecordingSession();


                const mimeType =
                    preferredMimeType();


                const options = {
                    audioBitsPerSecond:
                        128000
                };


                if (mimeType) {
                    options.mimeType =
                        mimeType;
                }


                recorder =
                    new MediaRecorder(
                        mixedStream,
                        options
                    );


                recorder.addEventListener(
                    'dataavailable',
                    function (event) {
                        if (
                            event.data
                            && event.data.size > 0
                        ) {
                            recorderChunks.push(
                                event.data
                            );
                        }
                    }
                );


                recorder.addEventListener(
                    'stop',
                    async function () {
                        if (recorderStopping) {
                            return;
                        }


                        recorderStopping =
                            true;


                        stopRecordingTimer();


                        setRecorderState(
                            'saving'
                        );


                        if (recordingStatus) {
                            recordingStatus.textContent =
                                'Saving recording...';
                        }


                        try {
                            if (
                                !recorderChunks.length
                            ) {
                                throw new Error(
                                    'No audio was captured.'
                                );
                            }


                            const blob =
                                new Blob(
                                    recorderChunks,
                                    {
                                        type:
                                            recorder.mimeType
                                            || 'audio/webm'
                                    }
                                );


                            await saveRecording(
                                blob
                            );


                            showRecordingMessage(
                                'Meeting recording saved successfully.',
                                'success'
                            );


                            await releaseMedia();


                            /*
                             * Reload directly into transcript tab.
                             */
                            window.location.href =
                                window.location.pathname
                                + window.location.search
                                + '#transcripts-summary';


                            window.location.reload();

                        } catch (error) {
                            console.error(
                                error
                            );


                            showRecordingMessage(
                                error.message
                                || 'Could not save recording.',
                                'error'
                            );


                            await releaseMedia();


                            setRecorderState(
                                'idle'
                            );
                        } finally {
                            recorder =
                                null;

                            recorderChunks =
                                [];

                            recorderStopping =
                                false;
                        }
                    }
                );


                /*
                 * Stop/save when user stops sharing.
                 */
                displayStream
                    .getTracks()
                    .forEach(
                        function (track) {
                            track.addEventListener(
                                'ended',
                                function () {
                                    if (
                                        recorder
                                        && recorder.state
                                            !== 'inactive'
                                    ) {
                                        recorder.stop();
                                    }
                                },
                                {
                                    once:true
                                }
                            );
                        }
                    );


                recorder.start(
                    1000
                );


                setAudioSourceState(
                    true,
                    true
                );


                setRecorderState(
                    'recording'
                );


                startRecordingTimer();


                if (recordingStatus) {
                    recordingStatus.textContent =
                        'Recording meeting audio and microphone.';
                }


                showRecordingMessage(
                    'Recording started. Meeting audio and your microphone are both being captured.',
                    'success'
                );

            } catch (error) {
                console.error(
                    error
                );


                await releaseMedia();


                recorder =
                    null;

                recorderChunks =
                    [];


                setRecorderState(
                    'idle'
                );


                let errorMessage =
                    error.message
                    || 'Could not start recording.';


                if (
                    error.name
                    === 'NotAllowedError'
                ) {
                    errorMessage =
                        'Permission was not granted. Allow microphone access, select the meeting tab and enable Share tab audio.';
                }


                showRecordingMessage(
                    errorMessage,
                    'error'
                );


                if (recordingStatus) {
                    recordingStatus.textContent =
                        errorMessage;
                }
            }
        }


        startButton
            ?.addEventListener(
                'click',
                startMeetingRecording
            );


        pauseButton
            ?.addEventListener(
                'click',
                function () {
                    if (
                        !recorder
                        || recorder.state
                            !== 'recording'
                    ) {
                        return;
                    }


                    recorder.pause();


                    stopRecordingTimer();


                    setRecorderState(
                        'paused'
                    );


                    if (recordingStatus) {
                        recordingStatus.textContent =
                            'Recording paused.';
                    }
                }
            );


        resumeButton
            ?.addEventListener(
                'click',
                function () {
                    if (
                        !recorder
                        || recorder.state
                            !== 'paused'
                    ) {
                        return;
                    }


                    recorder.resume();


                    startRecordingTimer();


                    setRecorderState(
                        'recording'
                    );


                    if (recordingStatus) {
                        recordingStatus.textContent =
                            'Recording resumed.';
                    }
                }
            );


        stopButton
            ?.addEventListener(
                'click',
                function () {
                    if (
                        !recorder
                        || recorder.state
                            === 'inactive'
                    ) {
                        return;
                    }


                    stopRecordingTimer();


                    setRecorderState(
                        'saving'
                    );


                    if (recordingStatus) {
                        recordingStatus.textContent =
                            'Finishing and saving recording...';
                    }


                    recorder.stop();
                }
            );


        /*
        |--------------------------------------------------------------------------
        | Page cleanup
        |--------------------------------------------------------------------------
        */

        window.addEventListener(
            'beforeunload',
            function () {
                [
                    displayStream,
                    microphoneStream,
                    mixedStream
                ].forEach(
                    function (stream) {
                        stream
                            ?.getTracks()
                            .forEach(
                                function (track) {
                                    track.stop();
                                }
                            );
                    }
                );
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Auto-hide success alerts
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '[data-auto-dismiss]'
            )
            .forEach(
                function (alert) {
                    window.setTimeout(
                        function () {
                            alert.style.transition =
                                'opacity .3s ease';

                            alert.style.opacity =
                                '0';


                            window.setTimeout(
                                function () {
                                    alert.remove();
                                },
                                350
                            );
                        },
                        5000
                    );
                }
            );


        setRecorderState(
            'idle'
        );


        setAudioSourceState(
            false,
            false
        );


        renderRecordingTimer();


        /*
        |--------------------------------------------------------------------------
        | Audio Editor (Cut/Trim like CapCut)
        |--------------------------------------------------------------------------
        */

        const audioEditorDialog =
            document.getElementById('audio-editor-dialog');
        const waveformCanvas =
            document.getElementById('audio-waveform-canvas');
        const waveformCtx = waveformCanvas
            ? waveformCanvas.getContext('2d')
            : null;
        const selectionOverlay =
            document.getElementById('waveform-selection');
        const playheadOverlay =
            document.getElementById('waveform-playhead');
        const segmentStartTimeEl =
            document.getElementById('segment-start-time');
        const segmentEndTimeEl =
            document.getElementById('segment-end-time');
        const segmentDurationEl =
            document.getElementById('segment-duration');
        const segmentStartInput =
            document.getElementById('segment-start-input');
        const segmentEndInput =
            document.getElementById('segment-end-input');
        const segmentTitleInput =
            document.getElementById('segment-title');
        const segmentNotesInput =
            document.getElementById('segment-notes');
        const previewAudio =
            document.getElementById('segment-preview-audio');
        const previewHint =
            document.getElementById('preview-hint');
        const createSegmentButton =
            document.getElementById('create-segment-button');
        const validationEl =
            document.getElementById('audio-editor-validation');

        let audioEditorState = {
            audioUrl: '',
            duration: 0,
            recordingId: null,
            audioBuffer: null,
            peaks: null,
            selectionStart: 0,
            selectionEnd: 0,
            isSelecting: false,
            isPlaying: false,
            previewSource: null,
        };

        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        }

        function showAudioEditorValidation(message, type = 'error') {
            if (!validationEl) return;
            validationEl.textContent = message;
            validationEl.className = 'meeting-recording-message ' + type;
        }

        function clearAudioEditorValidation() {
            if (!validationEl) return;
            validationEl.textContent = '';
            validationEl.className = 'hidden meeting-recording-message';
        }

        async function loadAudioForEditor(recordingId, audioUrl, duration) {
            audioEditorState.recordingId = recordingId;
            audioEditorState.audioUrl = audioUrl;
            audioEditorState.duration = duration;
            audioEditorState.selectionStart = 0;
            audioEditorState.selectionEnd = Math.min(30, duration);
            audioEditorState.isSelecting = false;
            audioEditorState.isPlaying = false;

            segmentStartInput.value = '';
            segmentEndInput.value = '';
            segmentTitleInput.value = '';
            segmentNotesInput.value = '';
            previewAudio.classList.add('hidden');
            previewHint.classList.remove('hidden');
            createSegmentButton.disabled = true;
            clearAudioEditorValidation();

            try {
                const response = await fetch(audioUrl);
                const arrayBuffer = await response.arrayBuffer();

                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!audioEditorState.audioContext) {
                    audioEditorState.audioContext = new AudioContextClass();
                }
                const audioContext = audioEditorState.audioContext;

                if (audioContext.state === 'suspended') {
                    await audioContext.resume();
                }

                audioEditorState.audioBuffer = await audioContext.decodeAudioData(arrayBuffer);
                audioEditorState.peaks = computePeaks(audioEditorState.audioBuffer, waveformCanvas.width || 800);

                drawWaveform();

                updateSelectionDisplay();
                updatePreviewAudio();
            } catch (e) {
                console.error('Failed to load audio for editor:', e);
                showAudioEditorValidation('Failed to load audio waveform. Please try again.');
            }
        }

        function computePeaks(audioBuffer, width) {
            const channelData = audioBuffer.getChannelData(0);
            const samplesPerPixel = Math.ceil(channelData.length / width);
            const peaks = [];

            for (let i = 0; i < width; i++) {
                const start = i * samplesPerPixel;
                const end = Math.min(start + samplesPerPixel, channelData.length);
                let min = 1.0;
                let max = -1.0;

                for (let j = start; j < end; j++) {
                    const value = channelData[j];
                    if (value < min) min = value;
                    if (value > max) max = value;
                }

                peaks.push({ min, max });
            }

            return peaks;
        }

        function drawWaveform() {
            if (!waveformCanvas || !waveformCtx || !audioEditorState.peaks) return;

            const dpr = window.devicePixelRatio || 1;
            const rect = waveformCanvas.getBoundingClientRect();
            waveformCanvas.width = rect.width * dpr;
            waveformCanvas.height = rect.height * dpr;
            waveformCtx.scale(dpr, dpr);

            const width = rect.width;
            const height = rect.height;
            const centerY = height / 2;
            const maxAmplitude = centerY - 4;

            waveformCtx.clearRect(0, 0, width, height);

            waveformCtx.fillStyle = '#e2e8f0';
            waveformCtx.fillRect(0, 0, width, height);

            waveformCtx.strokeStyle = '#94a3b8';
            waveformCtx.lineWidth = 1;
            waveformCtx.beginPath();
            waveformCtx.moveTo(0, centerY);
            waveformCtx.lineTo(width, centerY);
            waveformCtx.stroke();

            const peaks = audioEditorState.peaks;
            const step = width / peaks.length;

            waveformCtx.strokeStyle = '#0f766e';
            waveformCtx.lineWidth = 1.5;

            waveformCtx.beginPath();
            for (let i = 0; i < peaks.length; i++) {
                const x = i * step;
                const peak = peaks[i];
                const y1 = centerY - peak.max * maxAmplitude;
                const y2 = centerY - peak.min * maxAmplitude;

                waveformCtx.moveTo(x, y1);
                waveformCtx.lineTo(x, y2);
            }
            waveformCtx.stroke();

            drawSelection();
            drawPlayhead();
        }

        function drawSelection() {
            if (!selectionOverlay || audioEditorState.duration === 0) return;

            const rect = waveformCanvas.getBoundingClientRect();
            const startX = (audioEditorState.selectionStart / audioEditorState.duration) * rect.width;
            const endX = (audioEditorState.selectionEnd / audioEditorState.duration) * rect.width;

            selectionOverlay.style.left = startX + 'px';
            selectionOverlay.style.width = Math.max(0, endX - startX) + 'px';
            selectionOverlay.classList.remove('hidden');
        }

        function drawPlayhead() {
            if (!playheadOverlay || audioEditorState.duration === 0) return;

            const rect = waveformCanvas.getBoundingClientRect();
            let playheadPos = 0;

            if (audioEditorState.previewSource && audioEditorState.isPlaying) {
                const currentTime = audioEditorState.audioContext.currentTime - audioEditorState.previewStartTime;
                playheadPos = (currentTime / audioEditorState.duration) * rect.width;
            } else if (audioEditorState.selectionStart > 0) {
                playheadPos = (audioEditorState.selectionStart / audioEditorState.duration) * rect.width;
            }

            playheadOverlay.style.left = playheadPos + 'px';
            playheadOverlay.classList.remove('hidden');
        }

        function updateSelectionDisplay() {
            if (segmentStartTimeEl) segmentStartTimeEl.value = formatTime(audioEditorState.selectionStart);
            if (segmentEndTimeEl) segmentEndTimeEl.value = formatTime(audioEditorState.selectionEnd);
            if (segmentDurationEl) segmentDurationEl.value = formatTime(audioEditorState.selectionEnd - audioEditorState.selectionStart);
        }

        function updatePreviewAudio() {
            if (!previewAudio || !audioEditorState.audioBuffer) return;

            const duration = audioEditorState.selectionEnd - audioEditorState.selectionStart;
            if (duration < 0.1) {
                previewAudio.classList.add('hidden');
                previewHint.classList.remove('hidden');
                previewHint.textContent = 'Select a region on the waveform to preview.';
                createSegmentButton.disabled = true;
                return;
            }

            createSegmentButton.disabled = false;
            previewHint.classList.add('hidden');

            const startSample = Math.floor(audioEditorState.selectionStart * audioEditorState.audioBuffer.sampleRate);
            const endSample = Math.floor(audioEditorState.selectionEnd * audioEditorState.audioBuffer.sampleRate);
            const length = endSample - startSample;

            const offlineContext = new OfflineAudioContext(
                audioEditorState.audioBuffer.numberOfChannels,
                length,
                audioEditorState.audioBuffer.sampleRate
            );

            const bufferSource = offlineContext.createBufferSource();
            bufferSource.buffer = audioEditorState.audioBuffer;
            bufferSource.connect(offlineContext.destination);
            bufferSource.start(0, audioEditorState.selectionStart, duration);

            offlineContext.startRendering().then(renderedBuffer => {
                const wavBlob = bufferToWave(renderedBuffer, renderedBuffer.length);
                const url = URL.createObjectURL(wavBlob);

                if (audioEditorState.previewObjectUrl) {
                    URL.revokeObjectURL(audioEditorState.previewObjectUrl);
                }
                audioEditorState.previewObjectUrl = url;

                previewAudio.src = url;
                previewAudio.classList.remove('hidden');
            }).catch(e => {
                console.error('Failed to render preview:', e);
                previewHint.textContent = 'Could not generate preview.';
                previewHint.classList.remove('hidden');
            });
        }

        function bufferToWave(abuffer, len) {
            const numOfChan = abuffer.numberOfChannels;
            const length = len * numOfChan * 2 + 44;
            const buffer = new ArrayBuffer(length);
            const view = new DataView(buffer);
            const channels = [];
            let sample;
            let offset = 0;
            let pos = 0;

            writeString(view, 'RIFF'); offset += 4;
            view.setUint32(offset, length - 8, true); offset += 4;
            writeString(view, 'WAVE'); offset += 4;
            writeString(view, 'fmt '); offset += 4;
            view.setUint32(offset, 16, true); offset += 4;
            view.setUint16(offset, 1, true); offset += 2;
            view.setUint16(offset, numOfChan, true); offset += 2;
            view.setUint32(offset, abuffer.sampleRate, true); offset += 4;
            view.setUint32(offset, abuffer.sampleRate * numOfChan * 2, true); offset += 4;
            view.setUint16(offset, numOfChan * 2, true); offset += 2;
            view.setUint16(offset, 16, true); offset += 4;
            writeString(view, 'data'); offset += 4;
            view.setUint32(offset, len * numOfChan * 2, true); offset += 4;

            for (let i = 0; i < abuffer.numberOfChannels; i++) {
                channels.push(abuffer.getChannelData(i));
            }

            while (pos < len) {
                for (let i = 0; i < numOfChan; i++) {
                    sample = Math.max(-1, Math.min(1, channels[i][pos]));
                    sample = sample < 0 ? sample * 0x8000 : sample * 0x7FFF;
                    view.setInt16(offset, sample, true);
                    offset += 2;
                }
                pos++;
            }

            return new Blob([buffer], { type: 'audio/wav' });
        }

        function writeString(view, string) {
            for (let i = 0; i < string.length; i++) {
                view.setUint8(offset + i, string.charCodeAt(i));
            }
            offset += string.length;
        }

        function getClickPosition(event) {
            const rect = waveformCanvas.getBoundingClientRect();
            const clientX = event.touches ? event.touches[0].clientX : event.clientX;
            return (clientX - rect.left) / rect.width;
        }

        function handleWaveformPointerDown(event) {
            if (!audioEditorState.audioBuffer) return;

            const pos = getClickPosition(event);
            const time = Math.max(0, Math.min(audioEditorState.duration, pos * audioEditorState.duration));

            audioEditorState.isSelecting = true;
            audioEditorState.selectionStart = time;
            audioEditorState.selectionEnd = time;

            document.addEventListener('mousemove', handleWaveformPointerMove);
            document.addEventListener('mouseup', handleWaveformPointerUp);
            document.addEventListener('touchmove', handleWaveformPointerMove, { passive: false });
            document.addEventListener('touchend', handleWaveformPointerUp);

            event.preventDefault();
        }

        function handleWaveformPointerMove(event) {
            if (!audioEditorState.isSelecting || !audioEditorState.audioBuffer) return;

            const pos = getClickPosition(event);
            const time = Math.max(0, Math.min(audioEditorState.duration, pos * audioEditorState.duration));

            audioEditorState.selectionEnd = time;

            if (audioEditorState.selectionStart > audioEditorState.selectionEnd) {
                const temp = audioEditorState.selectionStart;
                audioEditorState.selectionStart = audioEditorState.selectionEnd;
                audioEditorState.selectionEnd = temp;
            }

            drawSelection();
            updateSelectionDisplay();
            updatePreviewAudio();
        }

        function handleWaveformPointerUp() {
            audioEditorState.isSelecting = false;
            document.removeEventListener('mousemove', handleWaveformPointerMove);
            document.removeEventListener('mouseup', handleWaveformPointerUp);
            document.removeEventListener('touchmove', handleWaveformPointerMove);
            document.removeEventListener('touchend', handleWaveformPointerUp);
        }

        function handleStartInputChange() {
            const value = parseFloat(segmentStartInput.value);
            if (!isNaN(value) && value >= 0 && value < audioEditorState.duration) {
                audioEditorState.selectionStart = value;
                if (audioEditorState.selectionEnd <= audioEditorState.selectionStart) {
                    audioEditorState.selectionEnd = Math.min(audioEditorState.duration, audioEditorState.selectionStart + 1);
                }
                drawSelection();
                updateSelectionDisplay();
                updatePreviewAudio();
            }
        }

        function handleEndInputChange() {
            const value = parseFloat(segmentEndInput.value);
            if (!isNaN(value) && value > 0 && value <= audioEditorState.duration) {
                audioEditorState.selectionEnd = value;
                if (audioEditorState.selectionStart >= audioEditorState.selectionEnd) {
                    audioEditorState.selectionStart = Math.max(0, audioEditorState.selectionEnd - 1);
                }
                drawSelection();
                updateSelectionDisplay();
                updatePreviewAudio();
            }
        }

        async function handlePreviewPlay() {
            if (!previewAudio.src || audioEditorState.isPlaying) return;

            audioEditorState.isPlaying = true;

            try {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!audioEditorState.audioContext || audioEditorState.audioContext.state === 'closed') {
                    audioEditorState.audioContext = new AudioContextClass();
                }

                if (audioEditorState.audioContext.state === 'suspended') {
                    await audioEditorState.audioContext.resume();
                }

                audioEditorState.previewStartTime = audioEditorState.audioContext.currentTime - (audioEditorState.selectionStart || 0);

                const source = audioEditorState.audioContext.createBufferSource();
                source.buffer = audioEditorState.audioBuffer;
                source.connect(audioEditorState.audioContext.destination);
                source.start(0, audioEditorState.selectionStart, audioEditorState.selectionEnd - audioEditorState.selectionStart);

                audioEditorState.previewSource = source;

                const animatePlayhead = () => {
                    if (audioEditorState.isPlaying && audioEditorState.previewSource) {
                        drawPlayhead();
                        requestAnimationFrame(animatePlayhead);
                    }
                };
                animatePlayhead();

                source.onended = () => {
                    audioEditorState.isPlaying = false;
                    audioEditorState.previewSource = null;
                    drawPlayhead();
                };
            } catch (e) {
                console.error('Preview play failed:', e);
                audioEditorState.isPlaying = false;
            }
        }

        function handlePreviewPause() {
            if (audioEditorState.previewSource) {
                try {
                    audioEditorState.previewSource.stop();
                } catch (_) {}
                audioEditorState.previewSource = null;
            }
            audioEditorState.isPlaying = false;
            drawPlayhead();
        }

        async function handleCreateSegment() {
            if (!audioEditorState.recordingId) return;

            const start = audioEditorState.selectionStart;
            const end = audioEditorState.selectionEnd;

            if (end - start < 0.5) {
                showAudioEditorValidation('Segment must be at least 0.5 seconds long.', 'warning');
                return;
            }

            if (start < 0 || end > audioEditorState.duration) {
                showAudioEditorValidation('Invalid selection range.', 'error');
                return;
            }

            createSegmentButton.disabled = true;
            createSegmentButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Creating...';

            try {
                const response = await fetch(
                    @json(route('meeting-recordings.segments.store', ['recording' => '__ID__'])).replace('__ID__', audioEditorState.recordingId),
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-CSRF-TOKEN': @json(csrf_token()),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            start_seconds: Math.round(start),
                            end_seconds: Math.round(end),
                            title: segmentTitleInput.value.trim() || null,
                            notes: segmentNotesInput.value.trim() || null,
                        }),
                    }
                );

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || 'Failed to create segment.');
                }

                closeDialog(audioEditorDialog);
                showAudioEditorValidation('Segment created successfully!', 'success');

                refreshSegments(audioEditorState.recordingId);
            } catch (e) {
                console.error('Create segment failed:', e);
                showAudioEditorValidation(e.message || 'Failed to create segment. Please try again.', 'error');
            } finally {
                createSegmentButton.disabled = false;
                createSegmentButton.innerHTML = '<i class="fa-solid fa-scissors mr-1"></i> Create Segment';
            }
        }

        async function refreshSegments(recordingId) {
            const listEl = document.getElementById('segments-list-' + recordingId);
            const countEl = document.getElementById('segments-count-' + recordingId);

            if (!listEl) return;

            try {
                const response = await fetch(
                    @json(route('meeting-recordings.segments.index', ['recording' => '__ID__'])).replace('__ID__', recordingId),
                    {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    }
                );

                const payload = await response.json().catch(() => null);

                if (!response.ok || !payload || !payload.ok || !Array.isArray(payload.segments)) {
                    throw new Error((payload && payload.message) || 'Failed to load segments.');
                }

                listEl.innerHTML = '';

                if (countEl) {
                    countEl.textContent = payload.segments.length + ' segment' + (payload.segments.length !== 1 ? 's' : '');
                }

                if (payload.segments.length === 0) {
                    listEl.innerHTML = '<p class="text-xs text-slate-500">No segments yet. Use Edit Audio (Cut/Trim) to cut one from this recording.</p>';
                    return;
                }

                    payload.segments.forEach(segment => {
                        const item = document.createElement('div');
                        item.className = 'meeting-segment-item';
                        item.dataset.segmentId = segment.id;

                        item.innerHTML = `
                            <div class="meeting-segment-info">
                                <div class="meeting-segment-title">
                                    <i class="fa-solid fa-waveform-lines text-amber-600"></i>
                                    ${segment.title ? escapeHtml(segment.title) : 'Segment #' + segment.id}
                                </div>
                                <div class="meeting-segment-times">
                                    ${segment.formatted_start} - ${segment.formatted_end} (${segment.formatted_duration})
                                </div>
                            </div>
                            <div class="meeting-segment-actions">
                                <button
                                    type="button"
                                    class="meeting-segment-action play"
                                    data-action="play"
                                    data-audio-url="${segment.audio_url}"
                                >
                                    <i class="fa-solid fa-play"></i> Play
                                </button>
                                ${segment.transcription_status !== 'completed' ? `
                                    <form method="POST" action="${@json(route('meeting-recording-segments.transcribe', ['segment' => '__ID__'])).replace('__ID__', segment.id)}" class="inline">
                                        @csrf
                                        <button type="submit" class="meeting-segment-action transcribe">
                                            <i class="fa-solid fa-wand-magic-sparkles"></i> Transcribe
                                        </button>
                                    </form>
                                ` : `
                                    <button
                                        type="button"
                                        class="meeting-segment-action transcribe"
                                        disabled
                                        title="Already transcribed"
                                    >
                                        <i class="fa-solid fa-check"></i> Transcribed
                                    </button>
                                `}
                                ${segment.transcript && segment.summary_status !== 'completed' ? `
                                    <form method="POST" action="${@json(route('meeting-recording-segments.summarize', ['segment' => '__ID__'])).replace('__ID__', segment.id)}" class="inline">
                                        @csrf
                                        <button type="submit" class="meeting-segment-action summarize">
                                            <i class="fa-solid fa-wand-magic-sparkles"></i> Summarize
                                        </button>
                                    </form>
                                ` : ''}
                                <form method="POST" action="${@json(route('meeting-recording-segments.destroy', ['segment' => '__ID__'])).replace('__ID__', segment.id)}" class="inline" onsubmit="return confirm('Delete this segment?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="meeting-segment-action delete">
                                        <i class="fa-solid fa-trash-can"></i> Delete
                                    </button>
                                </form>
                            </div>
                        `;

                        listEl.appendChild(item);
                    });

                    listEl.querySelectorAll('[data-action="play"]').forEach(btn => {
                        btn.addEventListener('click', function() {
                            playSegmentAudio(this.dataset.audioUrl, this.closest('.meeting-segment-item'));
                        });
                    });
            } catch (e) {
                console.error('Failed to load segments:', e);
                if (countEl) {
                    countEl.textContent = 'Could not load segments';
                }
                listEl.innerHTML = '<p class="text-xs text-red-500">Could not load segments. Please refresh the page and try again.</p>';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function playSegmentAudio(audioUrl, itemEl) {
            if (!audioUrl) return;

            document.querySelectorAll('.meeting-segment-item.playing').forEach(el => {
                el.classList.remove('playing');
                const audio = el.querySelector('audio');
                if (audio) audio.pause();
            });

            if (itemEl.classList.contains('playing')) {
                itemEl.classList.remove('playing');
                return;
            }

            let audioEl = itemEl.querySelector('audio');
            if (!audioEl) {
                audioEl = document.createElement('audio');
                audioEl.src = audioUrl;
                audioEl.preload = 'auto';
                itemEl.appendChild(audioEl);
            }

            audioEl.play().then(() => {
                itemEl.classList.add('playing');
            }).catch(e => {
                console.error('Play failed:', e);
            });

            audioEl.addEventListener('ended', () => {
                itemEl.classList.remove('playing');
            });
            audioEl.addEventListener('pause', () => {
                itemEl.classList.remove('playing');
            });
        }

        document.addEventListener('click', function(event) {
            const editButton = event.target.closest('.meeting-edit-audio-button');
            if (editButton) {
                event.preventDefault();
                const recordingId = editButton.dataset.recordingId;
                const audioUrl = editButton.dataset.audioUrl;
                const duration = parseInt(editButton.dataset.duration, 10);

                loadAudioForEditor(recordingId, audioUrl, duration);
                openDialog(audioEditorDialog);
                return;
            }

            if (event.target.closest('[data-close-audio-editor]')) {
                event.preventDefault();
                closeDialog(audioEditorDialog);

                if (audioEditorState.previewObjectUrl) {
                    URL.revokeObjectURL(audioEditorState.previewObjectUrl);
                    audioEditorState.previewObjectUrl = null;
                }
                if (audioEditorState.previewSource) {
                    try { audioEditorState.previewSource.stop(); } catch (_) {}
                    audioEditorState.previewSource = null;
                }
                audioEditorState.isPlaying = false;
                return;
            }

            if (event.target === createSegmentButton) {
                event.preventDefault();
                handleCreateSegment();
            }

            if (event.target === previewAudio) {
                if (previewAudio.paused) {
                    handlePreviewPlay();
                } else {
                    handlePreviewPause();
                }
            }
        });

        if (waveformCanvas) {
            waveformCanvas.addEventListener('mousedown', handleWaveformPointerDown);
            waveformCanvas.addEventListener('touchstart', handleWaveformPointerDown, { passive: false });
        }

        if (segmentStartInput) {
            segmentStartInput.addEventListener('change', handleStartInputChange);
        }

        if (segmentEndInput) {
            segmentEndInput.addEventListener('change', handleEndInputChange);
        }

        if (previewAudio) {
            previewAudio.addEventListener('play', () => {
                handlePreviewPlay();
            });
            previewAudio.addEventListener('pause', () => {
                handlePreviewPause();
            });
            previewAudio.addEventListener('ended', () => {
                handlePreviewPause();
            });
        }

        window.addEventListener('resize', () => {
            if (audioEditorState.peaks) {
                drawWaveform();
            }
        });


        /*
        |--------------------------------------------------------------------------
        | Transcription Capacity Check (pre-check before submit)
        |--------------------------------------------------------------------------
        */

        document.querySelectorAll('.transcribe-form').forEach(function(form) {
            form.addEventListener('submit', async function(event) {
                const submitBtn = form.querySelector('.transcribe-submit-btn');
                const checkUrl = form.dataset.checkCapacityUrl;

                if (!checkUrl) return;

                event.preventDefault();

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Checking...';

                try {
                    const response = await fetch(checkUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({}),
                    });

                    const data = await response.json();

                    if (data.can_transcribe) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Transcribing...';
                        form.submit();
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Transcribe & Summarise';

                        let title = 'Cannot Transcribe';
                        switch (data.reason) {
                            case 'quota_exceeded':
                                title = 'Quota Exceeded';
                                break;
                            case 'file_too_large':
                                title = 'File Too Large';
                                break;
                            case 'openai_not_configured':
                                title = 'Service Unavailable';
                                break;
                            case 'no_audio':
                                title = 'No Audio';
                                break;
                        }

                        showTranscriptionCapacityModal(title, data.message, data);
                    }
                } catch (e) {
                    console.error('Capacity check failed:', e);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Transcribe & Summarise';
                    alert('Failed to check transcription capacity. Please try again.');
                }
            });
        });

        function showTranscriptionCapacityModal(title, message, data) {
            const existingModal = document.getElementById('transcription-capacity-modal');
            if (existingModal) existingModal.remove();

            let detailsHtml = '';
            if (data.file_size_mb !== undefined) {
                detailsHtml += `<p class="text-sm text-slate-600">File size: ${data.file_size_mb} MB</p>`;
            }
            if (data.has_active_access !== undefined) {
                detailsHtml += `<p class="text-sm text-slate-600">Active subscription: ${data.has_active_access ? 'Yes' : 'No'}</p>`;
            }
            if (data.extra_minutes_remaining !== undefined) {
                detailsHtml += `<p class="text-sm text-slate-600">Extra minutes remaining: ${data.extra_minutes_remaining}</p>`;
            }

            const modalHtml = `
                <dialog id="transcription-capacity-modal" class="meeting-simple-dialog">
                    <div class="meeting-simple-dialog-card">
                        <header class="meeting-dialog-header">
                            <div>
                                <h3 class="text-lg font-black text-slate-900">${title}</h3>
                                <p class="mt-1 text-xs text-slate-500">${message}</p>
                            </div>
                            <button type="button" data-close-capacity-modal class="meeting-dialog-close" aria-label="Close">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </header>
                        <div class="meeting-dialog-body">
                            ${detailsHtml ? `<div class="mb-4 p-3 bg-slate-50 rounded-xl">${detailsHtml}</div>` : ''}
                            <div class="text-sm text-slate-600">
                                <p>To transcribe recordings, you need either:</p>
                                <ul class="list-disc pl-5 mt-2 space-y-1">
                                    <li>An active subscription</li>
                                    <li>Extra recording quota minutes (top-up)</li>
                                </ul>
                            </div>
                        </div>
                        <footer class="meeting-dialog-footer">
                            <button type="button" data-close-capacity-modal class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold">OK</button>
                            ${data.reason === 'quota_exceeded' || data.reason === 'file_too_large' ? `
                                <a href="{{ route('subscription.plans') }}" class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold text-white">
                                    <i class="fa-solid fa-plus mr-1"></i> Get More Minutes
                                </a>
                            ` : ''}
                        </footer>
                    </div>
                </dialog>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = document.getElementById('transcription-capacity-modal');
            modal.showModal();

            modal.addEventListener('click', function(e) {
                if (e.target === modal || e.target.closest('[data-close-capacity-modal]')) {
                    modal.close();
                    modal.remove();
                }
            });
        }

    }
);
</script>

@endsection
