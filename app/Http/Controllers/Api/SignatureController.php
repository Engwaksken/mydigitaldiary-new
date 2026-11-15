<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SignedDocument;
use App\Models\Signature;
use App\Services\SignatureImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Mobile - view/download saved signatures and signed documents, plus a
 * single-image signing flow (stampImage() below) for actually signing a
 * document. The web app's full multi-page drag/resize PDF editor stays
 * web-only - it depends on PDF.js rendering pages in a browser, which
 * doesn't translate to a mobile app without a much bigger, harder-to-
 * verify addition (a PDF-rendering package plus multi-page gesture
 * handling). Signing a single scanned/photographed image document is a
 * genuinely common case this covers instead.
 */
class SignatureController extends Controller
{
    public function signatures(Request $request): JsonResponse
    {
        $signatures = Signature::where('user_id', $request->user()->id)->orderByDesc('id')->get();

        return response()->json(['data' => $signatures->map(fn ($s) => [
            'id' => $s->id,
            'label' => $s->displayLabel(),
            'url' => $s->url(),
        ])]);
    }

    /**
     * Mobile equivalent of the web app's storeSignature() - same
     * validation, same storage disk/path.
     */
    public function storeSignature(Request $request, SignatureImageService $signatureImages): JsonResponse
    {
        $data = $request->validate([
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'drawn_signature' => ['nullable', 'string'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $request->hasFile('signature') && blank($data['drawn_signature'] ?? null)) {
            return response()->json([
                'message' => 'Draw with your finger/pen or upload an e-signature image.',
            ], 422);
        }

        $signature = Signature::create([
            'user_id' => $request->user()->id,
            'label' => $data['label'] ?? null,
            'file_path' => $signatureImages->store(
                $request->file('signature'),
                $data['drawn_signature'] ?? null
            ),
        ]);

        return response()->json(['data' => [
            'id' => $signature->id,
            'label' => $signature->displayLabel(),
            'url' => $signature->url(),
        ]], 201);
    }


    /**
     * Stream a saved signature through the authenticated API instead of
     * exposing /storage directly. Some shared-hosting configurations return
     * 403 for public storage symlinks even though the API upload succeeded.
     */
    public function image(Request $request, Signature $signature): BinaryFileResponse
    {
        abort_unless($signature->user_id === $request->user()->id, 403);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($signature->file_path), 404, 'Signature image not found.');

        return response()->file($disk->path($signature->file_path), [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Type' => $disk->mimeType($signature->file_path) ?: 'image/png',
        ]);
    }

    public function destroySignature(Request $request, Signature $signature): JsonResponse
    {
        abort_unless($signature->user_id === $request->user()->id, 403);

        \Illuminate\Support\Facades\Storage::disk('public')->delete($signature->file_path);
        $signature->delete();

        return response()->json(['message' => 'Signature removed.']);
    }

    public function documents(Request $request): JsonResponse
    {
        $documents = SignedDocument::where('user_id', $request->user()->id)
            ->with('placements')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $documents->map(fn ($d) => [
            'id' => $d->id,
            'original_filename' => $d->original_filename,
            'was_stamped' => $d->was_stamped,
            'stamp_error' => $d->stamp_error,
            'page_count' => $d->placements->pluck('page_number')->unique()->count(),
            'placement_count' => $d->placements->count(),
            'signed_at' => $d->signed_at?->toIso8601String(),
            'download_url' => \Illuminate\Support\Facades\URL::temporarySignedRoute('signature.documents.shared', now()->addDays(30), ['signedDocument' => $d->id]),
        ])]);
    }

    public function destroyDocument(Request $request, SignedDocument $signedDocument): JsonResponse
    {
        abort_unless($signedDocument->user_id === $request->user()->id, 403);

        \Illuminate\Support\Facades\Storage::disk('public')->delete(array_filter([
            $signedDocument->original_file_path, $signedDocument->signed_file_path,
        ]));
        $signedDocument->delete();

        return response()->json(['message' => 'Document removed.']);
    }

    /**
     * Mobile equivalent of the web app's bulkDestroyDocuments() - same
     * ownership-scoped bulk delete, JSON instead of a redirect.
     */
    public function bulkDestroyDocuments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_ids' => ['required', 'array'],
            'document_ids.*' => ['integer'],
        ]);

        $documents = SignedDocument::where('user_id', $request->user()->id)
            ->whereIn('id', $data['document_ids'])
            ->get();

        foreach ($documents as $document) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete(array_filter([
                $document->original_file_path, $document->signed_file_path,
            ]));
        }

        $count = $documents->count();
        SignedDocument::whereIn('id', $documents->pluck('id'))->delete();

        return response()->json(['message' => $count === 1 ? '1 document removed.' : "{$count} documents removed."]);
    }

    /**
     * Mobile equivalent of the web app's preview→confirm signing flow,
     * collapsed into a single request - deliberately scoped to IMAGE
     * documents only (JPG/PNG), not PDFs. The web app's multi-page PDF
     * editor depends on PDF.js rendering pages in a browser; porting
     * that natively would need a PDF-rendering package this app
     * doesn't have and hasn't verified, plus much more complex
     * multi-page gesture handling. Signing a single scanned/
     * photographed document - a common real use case - works exactly
     * like the web app's image-stamping path (same GD-based logic,
     * duplicated here rather than shared across two different
     * controllers), just without the session-based two-step preview
     * step, since the mobile UI already shows a live drag preview
     * before the user ever submits.
     */
    public function stampImage(Request $request): JsonResponse
    {
        if (! extension_loaded('gd')) {
            return response()->json(['message' => "The GD image library isn't enabled on this server, so images can't be stamped."], 422);
        }

        $data = $request->validate([
            'document' => ['required', 'image', 'max:10240'],
            'signature_id' => ['required', 'integer'],
            'x_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'y_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'width_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'height_percent' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $signature = Signature::where('id', $data['signature_id'])->where('user_id', $user->id)->first();
        abort_unless($signature, 404, 'Signature not found.');

        $originalFile = $request->file('document');
        $originalPath = $originalFile->store('signed-documents/originals', 'public');

        $documentFullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($originalPath);
        $signatureFullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($signature->file_path);

        $document = $this->loadGdImage($documentFullPath);
        $signatureImage = $this->loadGdImage($signatureFullPath);

        $wasStamped = false;
        $stampError = null;
        $signedPath = null;

        if (! $document) {
            $stampError = 'The uploaded document could not be read as an image.';
        } elseif (! $signatureImage) {
            $stampError = 'Your saved signature file could not be read as an image.';
        } else {
            $docWidth = imagesx($document);
            $docHeight = imagesy($document);
            $sigWidth = imagesx($signatureImage);
            $sigHeight = imagesy($signatureImage);

            $targetWidth = max(1, (int) round($docWidth * $data['width_percent'] / 100));
            $targetHeight = max(1, (int) round($docHeight * $data['height_percent'] / 100));

            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
            imagealphablending($resized, false);
            imagecopyresampled($resized, $signatureImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $sigWidth, $sigHeight);

            $destX = max(0, min((int) round($docWidth * $data['x_percent'] / 100), $docWidth - $targetWidth));
            $destY = max(0, min((int) round($docHeight * $data['y_percent'] / 100), $docHeight - $targetHeight));

            imagealphablending($document, true);
            imagesavealpha($document, true);
            imagecopy($document, $resized, $destX, $destY, 0, 0, $targetWidth, $targetHeight);

            $signedPath = 'signed-documents/signed/' . uniqid('signed_', true) . '.png';
            $signedFullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($signedPath);
            $dir = dirname($signedFullPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            imagepng($document, $signedFullPath);
            $wasStamped = true;
        }

        $signedDocument = SignedDocument::create([
            'user_id' => $user->id,
            'signature_id' => $signature->id,
            'original_filename' => $originalFile->getClientOriginalName(),
            'original_file_path' => $originalPath,
            'signed_file_path' => $signedPath,
            'was_stamped' => $wasStamped,
            'stamp_error' => $stampError,
            'signed_at' => $wasStamped ? now() : null,
            'mime_type' => $originalFile->getMimeType(),
        ]);

        return response()->json(['data' => [
            'id' => $signedDocument->id,
            'was_stamped' => $wasStamped,
            'stamp_error' => $stampError,
            'download_url' => \Illuminate\Support\Facades\URL::temporarySignedRoute('signature.documents.shared', now()->addDays(30), ['signedDocument' => $signedDocument->id]),
        ]]);
    }


    /** Combine several mobile-signed image pages into one previewable/downloadable PDF. */
    public function bundlePages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_ids' => ['required', 'array', 'min:1'],
            'document_ids.*' => ['integer'],
            'filename' => ['nullable', 'string', 'max:180'],
        ]);

        $documentsById = SignedDocument::where('user_id', $request->user()->id)
            ->whereIn('id', $data['document_ids'])
            ->get()->keyBy('id');
        $documents = collect($data['document_ids'])->map(fn ($id) => $documentsById->get((int) $id))->filter()->values();
        abort_if($documents->count() !== count($data['document_ids']), 422, 'One or more signed pages could not be found.');

        $pages = [];
        foreach ($documents as $document) {
            abort_unless($document->was_stamped && $document->signed_file_path, 422, 'Every selected page must be signed first.');
            $disk = Storage::disk('public');
            abort_unless($disk->exists($document->signed_file_path), 422, 'A signed page file is missing.');
            $mime = $disk->mimeType($document->signed_file_path) ?: 'image/png';
            $pages[] = 'data:'.$mime.';base64,'.base64_encode($disk->get($document->signed_file_path));
        }

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:0}body{margin:0}.page{page-break-after:always;width:100%;height:100vh;display:flex;align-items:center;justify-content:center}.page:last-child{page-break-after:auto}.page img{max-width:100%;max-height:100%;object-fit:contain}</style></head><body>';
        foreach ($pages as $page) $html .= '<div class="page"><img src="'.$page.'"></div>';
        $html .= '</body></html>';
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4');
        $pdfPath = 'signed-documents/signed/'.uniqid('signed_bundle_', true).'.pdf';
        Storage::disk('public')->put($pdfPath, $pdf->output());

        $filename = trim($data['filename'] ?? '') ?: 'signed-document.pdf';
        if (! str_ends_with(strtolower($filename), '.pdf')) $filename .= '.pdf';
        $bundle = SignedDocument::create([
            'user_id' => $request->user()->id,
            'signature_id' => $documents->first()->signature_id,
            'original_filename' => $filename,
            'original_file_path' => $pdfPath,
            'signed_file_path' => $pdfPath,
            'was_stamped' => true,
            'signed_at' => now(),
            'mime_type' => 'application/pdf',
        ]);

        // The bundle contains copies of every page, so remove temporary per-page rows/files.
        foreach ($documents as $document) {
            Storage::disk('public')->delete(array_filter([$document->original_file_path, $document->signed_file_path]));
            $document->delete();
        }

        return response()->json(['data' => [
            'id' => $bundle->id,
            'was_stamped' => true,
            'page_count' => count($pages),
            'download_url' => \Illuminate\Support\Facades\URL::temporarySignedRoute('signature.documents.shared', now()->addDays(30), ['signedDocument' => $bundle->id]),
        ]]);
    }

    private function loadGdImage(string $path)
    {
        $info = @getimagesize($path);
        if (! $info) {
            return null;
        }

        return match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => null,
        } ?: null;
    }
}
