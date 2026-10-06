{{-- Share the uploaded logo with browser tabs as well as installed PWAs. --}}
<link rel="icon" type="image/png" sizes="192x192" href="{{ app(\App\Services\PwaIconService::class)->url('icon-192') }}">
