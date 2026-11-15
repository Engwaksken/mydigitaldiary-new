{{--
    Standalone page, not extending layouts.app - whoever scans the QR
    code or opens the shared link is almost never a logged-in user, and
    that layout's sidebar assumes auth()->user() exists.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $card->name }}{{ $card->title ? ' - ' . $card->title : '' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --brand-1: #00897B; --brand-2: #73BEB6; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="px-6 pt-8 pb-16 text-center relative" style="background: linear-gradient(135deg, {{ $card->cardColor() }}, {{ $card->cardColorSecondary() }});">
            @if ($card->logoDataUri())
                <img src="{{ $card->logoDataUri() }}" alt="" class="max-h-16 mb-4 mx-auto object-contain">
            @endif
            @if ($card->photoDataUri())
                <img src="{{ $card->photoDataUri() }}" alt="{{ $card->name }}"
                     class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-lg absolute left-1/2 -translate-x-1/2" style="bottom: -3rem;">
            @else
                <div class="w-24 h-24 rounded-full bg-white/10 border-4 border-white shadow-lg absolute left-1/2 -translate-x-1/2 flex items-center justify-center" style="bottom: -3rem;">
                    <i class="fa-solid fa-user text-white text-3xl" aria-hidden="true"></i>
                </div>
            @endif
        </div>

        <div class="pt-14 pb-6 px-6 text-center">
            <h1 class="text-xl font-bold text-slate-800">{{ $card->name }}</h1>
            @if ($card->title)
                <p class="text-sm text-slate-500">{{ $card->title }}</p>
            @endif
            @if ($card->company)
                <p class="text-sm font-medium text-[var(--brand-1)] mt-0.5">{{ $card->company }}</p>
            @endif

            @if ($card->bio)
                <p class="text-sm text-slate-600 mt-4">{{ $card->bio }}</p>
            @endif

            <div class="mt-6 space-y-2 text-left">
                @if ($card->phone)
                    <a href="tel:{{ $card->phone }}" class="flex items-center gap-3 text-sm text-slate-700 hover:text-[var(--brand-1)] py-1.5">
                        <span class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0"><i class="fa-solid fa-phone text-xs" aria-hidden="true"></i></span>
                        {{ $card->phone }}
                    </a>
                @endif
                @if ($card->whatsapp_phone)
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $card->whatsapp_phone) }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 text-sm text-slate-700 hover:text-[var(--brand-1)] py-1.5">
                        <span class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0"><i class="fa-brands fa-whatsapp text-xs" aria-hidden="true"></i></span>
                        WhatsApp
                    </a>
                @endif
                @if ($card->email)
                    <a href="mailto:{{ $card->email }}" class="flex items-center gap-3 text-sm text-slate-700 hover:text-[var(--brand-1)] py-1.5">
                        <span class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0"><i class="fa-solid fa-envelope text-xs" aria-hidden="true"></i></span>
                        {{ $card->email }}
                    </a>
                @endif
                @if ($card->website)
                    <a href="{{ str_starts_with($card->website, 'http') ? $card->website : 'https://' . $card->website }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 text-sm text-slate-700 hover:text-[var(--brand-1)] py-1.5">
                        <span class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0"><i class="fa-solid fa-globe text-xs" aria-hidden="true"></i></span>
                        {{ $card->website }}
                    </a>
                @endif
                @if ($card->address)
                    <div class="flex items-center gap-3 text-sm text-slate-700 py-1.5">
                        <span class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0"><i class="fa-solid fa-location-dot text-xs" aria-hidden="true"></i></span>
                        {{ $card->address }}
                    </div>
                @endif
            </div>

            @if (! empty($card->social_links))
                <div class="flex items-center justify-center gap-3 mt-5">
                    @foreach ($card->social_links as $platform => $url)
                        <a href="{{ str_starts_with($url, 'http') ? $url : 'https://' . $url }}" target="_blank" rel="noopener noreferrer"
                           class="w-9 h-9 rounded-full bg-slate-100 text-slate-600 hover:bg-[var(--brand-1)] hover:text-white flex items-center justify-center transition-colors">
                            <i class="fa-brands fa-{{ $platform }} text-sm" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            @endif

            <button type="button" onclick="pmSaveContact()" class="mt-6 w-full bg-[var(--brand-1)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--brand-2)] transition-colors">
                <i class="fa-solid fa-address-card" aria-hidden="true"></i> Save Contact
            </button>
        </div>
    </div>

    <script>
        // A .vcf (vCard) file - every phone/email app knows how to import
        // one on tap, no server round-trip or extra library needed.
        function pmSaveContact() {
            var lines = [
                'BEGIN:VCARD', 'VERSION:3.0',
                'FN:{{ addslashes($card->name) }}',
                @if ($card->title) 'TITLE:{{ addslashes($card->title) }}', @endif
                @if ($card->company) 'ORG:{{ addslashes($card->company) }}', @endif
                @if ($card->phone) 'TEL;TYPE=WORK,VOICE:{{ addslashes($card->phone) }}', @endif
                @if ($card->email) 'EMAIL:{{ addslashes($card->email) }}', @endif
                @if ($card->website) 'URL:{{ addslashes($card->website) }}', @endif
                'END:VCARD',
            ];
            var blob = new Blob([lines.join('\n')], { type: 'text/vcard' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = '{{ addslashes(\Illuminate\Support\Str::slug($card->name)) }}.vcf';
            link.click();
        }
    </script>
</body>
</html>
