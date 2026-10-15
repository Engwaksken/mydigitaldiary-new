{{-- Prefer the separately uploaded browser favicon; use the generated app icon as fallback. --}}
@php
    try {
        $faviconSettings = $siteSettings ?? \App\Models\SiteSetting::current();
    } catch (\Throwable) {
        $faviconSettings = null;
    }
    $uploadedFavicon = filled($faviconSettings?->favicon_path)
        && \Illuminate\Support\Facades\Storage::disk('public')->exists($faviconSettings->favicon_path);
    $faviconUrl = $uploadedFavicon
        ? route('branding.asset', ['asset' => 'favicon'])
        : app(\App\Services\PwaIconService::class)->url('icon-192');
    $faviconType = $uploadedFavicon
        ? (\Illuminate\Support\Facades\Storage::disk('public')->mimeType($faviconSettings->favicon_path) ?: 'image/png')
        : 'image/png';
@endphp
<link rel="icon" type="{{ $faviconType }}" href="{{ $faviconUrl }}?v={{ substr(sha1((string) $faviconSettings?->favicon_path), 0, 10) }}">
