<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Talk to Sales - {{ $siteSettings->site_name ?? config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --brand-1: #00897B; --brand-2: #73BEB6; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .pm-input { display: block; width: 100%; border: 1px solid #cbd5e1 !important; border-radius: 0.5rem; padding: 0.5rem 0.75rem; background: white !important; color: #1e293b !important; }
        .pm-input:focus { outline: 2px solid var(--brand-2); border-color: transparent !important; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="max-w-5xl mx-auto px-4 py-10">
        <div class="flex items-center gap-3 mb-8">
            @if ($siteSettings->logoUrl())
                <img src="{{ $siteSettings->logoUrl() }}" alt="{{ $siteSettings->site_name }}" class="h-9">
            @endif
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Let's Talk</h1>
                <p class="text-sm text-slate-500">Tell us about your organization and we'll be in touch.</p>
            </div>
        </div>

        @if (session('success'))
            <div role="status" aria-live="polite" class="pm-flash-message rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm mb-6 transition-opacity duration-700">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-stretch">
            {{-- ================= FORM ================= --}}
            <form method="POST" action="{{ route('enterprise.contact.submit') }}" class="bg-white shadow-sm border border-slate-100 rounded-xl p-6 space-y-4">
                @csrf

                <div>
                    <label for="about" class="block text-sm font-medium text-slate-700 mb-1">Tell us a bit about yourself</label>
                    <textarea id="about" name="about" rows="3" required aria-required="true"
                              placeholder="Your role, your organization, and what you're looking for..."
                              class="pm-input">{{ old('about') }}</textarea>
                    @error('about')
                        <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user?->email) }}" required aria-required="true" class="pm-input">
                    @error('email')
                        <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Contact / Phone</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required aria-required="true"
                           autocomplete="tel" placeholder="e.g. +256 700 000000" class="pm-input">
                    @error('phone')
                        <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="country" class="block text-sm font-medium text-slate-700 mb-1">Country</label>
                    <input type="text" id="country" name="country" list="pm-country-list" value="{{ old('country') }}"
                           required aria-required="true" autocomplete="off"
                           placeholder="Start typing to search..." class="pm-input">
                    <datalist id="pm-country-list">
                        @foreach ($countries as $country)
                            <option value="{{ $country }}"></option>
                        @endforeach
                    </datalist>
                    <p class="text-xs text-slate-400 mt-1">Start typing and pick from the list.</p>
                    @error('country')
                        <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="employee_count" class="block text-sm font-medium text-slate-700 mb-1">Employee Count</label>
                    <select id="employee_count" name="employee_count" required aria-required="true" class="pm-input">
                        <option value="" disabled selected>Choose a range</option>
                        @foreach (['1-10', '11-50', '51-200', '201-500', '500+'] as $range)
                            <option value="{{ $range }}" @selected(old('employee_count') === $range)>{{ $range }}</option>
                        @endforeach
                    </select>
                    @error('employee_count')
                        <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all" style="background-color: var(--brand-1);">
                    Continue
                </button>

                @auth
                    <a href="{{ route('subscription.show') }}" class="block text-center text-sm text-slate-500 hover:underline">Back to plans</a>
                @endauth
            </form>

            {{-- ================= BENEFITS & HIGHLIGHTS ================= --}}
            <div class="rounded-xl p-6 sm:p-8 h-full" style="background-color: var(--brand-1);">
                <h2 class="text-lg font-bold text-white mb-1">Why Enterprise?</h2>
                <p class="text-sm text-white/70 mb-6">Built for organizations that need more than a standard plan.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-stretch">
                    @foreach ([
                        ['icon' => 'fa-user-tie', 'title' => 'Dedicated Account Manager', 'text' => 'A real person who knows your setup, not a ticket queue.'],
                        ['icon' => 'fa-users-gear', 'title' => 'Custom Seats & Pricing', 'text' => 'Sized to your actual headcount, not a fixed tier.'],
                        ['icon' => 'fa-shield-halved', 'title' => 'Advanced Security', 'text' => 'Role-based access controls built for larger teams.'],
                        ['icon' => 'fa-headset', 'title' => 'Priority Support', 'text' => 'Faster response times when something needs attention.'],
                        ['icon' => 'fa-chalkboard-user', 'title' => 'Onboarding & Training', 'text' => 'Guided setup so your team is productive from day one.'],
                        ['icon' => 'fa-file-invoice-dollar', 'title' => 'Flexible Billing', 'text' => 'Invoicing terms that fit how your organization actually pays.'],
                    ] as $benefit)
                        <div class="h-full flex flex-col bg-white/10 border border-white/15 rounded-xl p-4">
                            <div class="w-9 h-9 rounded-lg bg-white/15 text-white flex items-center justify-center mb-2 shrink-0">
                                <i class="fa-solid {{ $benefit['icon'] }}" aria-hidden="true"></i>
                            </div>
                            <p class="text-sm font-semibold text-white mb-0.5">{{ $benefit['title'] }}</p>
                            <p class="text-xs text-white/70">{{ $benefit['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        // Same behavior as the shared authenticated layout's flash
        // messages - shown for 5 seconds, then faded out over 0.7s and
        // removed from the DOM. Duplicated here rather than shared since
        // this is a standalone page (guests need to reach it) that
        // doesn't extend that layout.
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.pm-flash-message').forEach(function (message) {
                setTimeout(function () {
                    message.style.opacity = '0';
                    message.addEventListener('transitionend', function () {
                        message.remove();
                    }, { once: true });
                }, 5000);
            });
        });
    </script>
</body>
</html>
