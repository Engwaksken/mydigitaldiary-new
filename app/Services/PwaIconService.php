<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PwaIconService
{
    private const VARIANTS = [
        'icon-192' => [192, 'any'],
        'icon-512' => [512, 'any'],
        'maskable-512' => [512, 'maskable'],
        'apple-touch-icon' => [180, 'any'],
    ];

    public function icons(): array
    {
        $logo = $this->logo();

        return collect(self::VARIANTS)->map(function (array $variant, string $name) use ($logo): array {
            return [
                'src' => $logo
                    ? route('pwa.icon', ['version' => $logo['version'], 'variant' => $name], false)
                    : '/icons/'.$name.'.png',
                'sizes' => $variant[0].'x'.$variant[0],
                'type' => 'image/png',
                'purpose' => $variant[1],
            ];
        })->values()->all();
    }

    public function url(string $variant): string
    {
        $index = array_search($variant, array_keys(self::VARIANTS), true);
        if ($index === false) {
            throw new \InvalidArgumentException('Unknown PWA icon variant.');
        }

        return $this->icons()[$index]['src'];
    }

    public function png(string $version, string $variant): string
    {
        abort_unless(isset(self::VARIANTS[$variant]), 404);
        $logo = $this->logo();
        abort_unless($logo && hash_equals($logo['version'], $version), 404);
        abort_unless(function_exists('imagecreatefromstring'), 503, 'PHP GD is required to generate PWA icons.');

        return Cache::remember('pwa-icon:'.$version.':'.$variant, 86400, function () use ($logo, $variant): string {
            $source = imagecreatefromstring($logo['contents']);
            abort_if($source === false, 422, 'The uploaded logo could not be decoded.');
            $size = self::VARIANTS[$variant][0];
            $canvas = imagecreatetruecolor($size, $size);

            try {
                // An opaque canvas also works for iOS. Keep the full logo in
                // Android's central maskable safe zone, without stretching it.
                $background = imagecolorallocate($canvas, 255, 255, 255);
                imagefill($canvas, 0, 0, $background);
                $available = $size * ($variant === 'maskable-512' ? 0.6 : 0.9);
                $scale = min($available / imagesx($source), $available / imagesy($source));
                $width = max(1, (int) round(imagesx($source) * $scale));
                $height = max(1, (int) round(imagesy($source) * $scale));
                imagecopyresampled($canvas, $source, (int) (($size - $width) / 2), (int) (($size - $height) / 2), 0, 0, $width, $height, imagesx($source), imagesy($source));

                ob_start();
                try {
                    imagepng($canvas);

                    return (string) ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            } finally {
                imagedestroy($source);
                imagedestroy($canvas);
            }
        });
    }

    private function logo(): ?array
    {
        try {
            $path = SiteSetting::current()->logo_path;
            $disk = Storage::disk('public');
            if (! $path || ! $disk->exists($path)) {
                return null;
            }

            $contents = $disk->get($path);
            if (@getimagesizefromstring($contents) === false) {
                return null;
            }

            return [
                'contents' => $contents,
                'version' => substr(hash('sha256', 'pwa-icons-v1|'.$contents), 0, 16),
            ];
        } catch (Throwable) {
            // Missing branding must not prevent a guest/error page rendering.
            return null;
        }
    }
}
