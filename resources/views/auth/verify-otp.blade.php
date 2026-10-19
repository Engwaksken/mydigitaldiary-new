<x-guest-layout>
    <style>
        .otp-secondary-action,
        .otp-secondary-action:hover,
        .otp-secondary-action:focus,
        .otp-secondary-action:active {
            width: auto !important;
            min-width: 0 !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            background-color: transparent !important;
            background-image: none !important;
            box-shadow: none !important;
            outline: none !important;
            color: var(--auth-primary, var(--brand-1, #009688)) !important;
            font: inherit !important;
            font-size: 0.875rem !important;
            font-weight: 500 !important;
            line-height: 1.5 !important;
            text-decoration: none !important;
            cursor: pointer !important;
            appearance: none !important;
            -webkit-appearance: none !important;
        }

        .otp-secondary-action:hover {
            text-decoration: underline !important;
            opacity: 0.85;
        }

        .otp-secondary-action:focus-visible {
            text-decoration: underline !important;
        }

        .otp-start-over {
            font-size: 0.8125rem !important;
        }
    </style>

    <div class="space-y-1">
        <h1 class="text-lg font-semibold text-slate-800">Enter your code</h1>
        <p class="text-sm text-slate-500 leading-relaxed">
            We emailed a 6-digit code to your address. It expires in 10 minutes.
        </p>
    </div>

    <x-auth-session-status class="mt-4 mb-4" :status="session('status')" />

    {{--
        IMPORTANT: Verify has its own form. Do not put Resend in this form and do
        not use formaction here. Some mobile browsers can retain/reuse a clicked
        submit button's formaction and accidentally call the resend endpoint.
    --}}
    <form method="POST" action="{{ route('otp.verify.submit') }}" class="mt-6" id="otpVerifyForm">
        @csrf

        <x-auth-field
            name="code"
            label="Verification Code"
            type="text"
            placeholder="000000"
            hint="Check spam too, if it hasn't shown up."
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="6"
            autofocus
            required
            autocomplete="one-time-code"
            class="text-center text-2xl"
            style="letter-spacing: 0.5em;"
        />

        <div class="flex justify-end mt-6">
            <x-primary-button class="gap-2" id="otpVerifyButton">
                <x-icon name="check" class="text-xs" />
                Verify &amp; Sign In
            </x-primary-button>
        </div>
    </form>

    <div class="flex flex-wrap items-center justify-between gap-4 mt-5">
        {{-- Resend is a completely separate POST request. --}}
        <form method="POST" action="{{ route('otp.resend') }}" id="otpResendForm">
            @csrf
            <button type="submit" class="otp-secondary-action">
                Resend code
            </button>
        </form>

        <form method="POST" action="{{ route('otp.cancel') }}" id="otpCancelForm">
            @csrf
            <button type="submit" class="otp-secondary-action otp-start-over">
                <span class="text-slate-500">Not you?</span>
                <span>Start over</span>
            </button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('otpVerifyForm');
            const button = document.getElementById('otpVerifyButton');

            if (!form || !button) return;

            form.addEventListener('submit', function () {
                if (button.disabled) return;

                button.disabled = true;
                button.dataset.originalHtml = button.innerHTML;
                button.innerHTML = @js(\App\View\Icon::svg('spinner', 'text-xs pm-icon-spin')) + ' Verifying...';
            });
        });
    </script>
</x-guest-layout>
