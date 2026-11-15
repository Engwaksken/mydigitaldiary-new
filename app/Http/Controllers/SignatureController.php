<?php

namespace App\Http\Controllers;

use App\Models\Signature;
use App\Models\SignedDocument;
use App\Models\SignedDocumentPlacement;
use App\Services\SignatureImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Multi-placement signing: a document can carry several signature
 * placements at once - same or different signatures, same or different
 * pages (for PDFs). Positioning happens client-side (see
 * signature/show.blade.php's drag/resize JS, which renders every page
 * and lets the user drop signatures onto any of them); this controller
 * just receives the final list of placements and stamps each one.
 */
class SignatureController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $search = $request->query('doc_q');
        $period = $request->query('doc_period');
        $from = $request->query('doc_from');
        $to = $request->query('doc_to');

        $documentsQuery = SignedDocument::where('user_id', $user->id)
            ->when($search, fn ($q) => $q->where('original_filename', 'like', '%' . $search . '%'));

        match ($period) {
            'daily' => $documentsQuery->whereDate('created_at', now()->toDateString()),
            'weekly' => $documentsQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'monthly' => $documentsQuery->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'annual' => $documentsQuery->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]),
            'range' => ($from && $to)
                ? $documentsQuery->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                : null,
            default => null,
        };

        $allDocuments = SignedDocument::where('user_id', $user->id);

        return view('signature.show', [
            'signatures' => Signature::where('user_id', $user->id)->orderByDesc('id')->get(),
            'documents' => $documentsQuery->with('placements')->orderByDesc('id')->paginate(15, ['*'], 'documents_page')->withQueryString(),
            'pendingPreview' => session('signature_pending_preview'),
            'gdAvailable' => extension_loaded('gd'),
            'fpdiAvailable' => class_exists(\setasign\Fpdi\Fpdi::class),
            'docSearch' => $search,
            'docPeriod' => $period,
            'docFrom' => $from,
            'docTo' => $to,
            'stats' => [
                'today' => (clone $allDocuments)->whereDate('created_at', now()->toDateString())->count(),
                'signed' => (clone $allDocuments)->where('was_stamped', true)->count(),
                'total' => (clone $allDocuments)->count(),
            ],
        ]);
    }

    public function storeSignature(Request $request, SignatureImageService $signatureImages): RedirectResponse
    {
        $data = $request->validate([
            'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'drawn_signature' => ['nullable', 'string'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $request->hasFile('signature') && blank($data['drawn_signature'] ?? null)) {
            return back()->withErrors([
                'signature' => 'Draw with your finger/pen or upload an e-signature image.',
            ])->withInput();
        }

        $path = $signatureImages->store(
            $request->file('signature'),
            $data['drawn_signature'] ?? null
        );

        Signature::create([
            'user_id' => $request->user()->id,
            'label' => $data['label'] ?? null,
            'file_path' => $path,
        ]);

        return back()->with('success', 'Signature saved and ready to use.');
    }

    public function destroySignature(Request $request, Signature $signature): RedirectResponse
    {
        abort_unless($signature->user_id === $request->user()->id, 403);

        Storage::disk('public')->delete($signature->file_path);
        $signature->delete();

        return back()->with('success', 'Signature removed.');
    }

    /**
     * Step 1 of 2: receives the document plus the full list of
     * placements the user built in the page editor (JSON - see
     * pmCollectPlacements() in the view), stamps every one of them, and
     * stashes the result in the session for review before it's saved
     * permanently.
     */
    public function previewDocument(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'document' => ['required', 'file', 'max:10240'],
            'placements' => ['required', 'string'],
        ]);

        $placements = json_decode($data['placements'], true);
        if (! is_array($placements) || count($placements) === 0) {
            return back()->withErrors(['placements' => 'Add at least one signature to the document before previewing.']);
        }

        // Only placements referencing a signature the CURRENT user
        // actually owns are trusted - resolve and attach the real model
        // now rather than trusting a signature_id passed from the form.
        $ownedSignatures = Signature::where('user_id', $user->id)->get()->keyBy('id');
        $resolvedPlacements = [];
        foreach ($placements as $placement) {
            $signature = $ownedSignatures->get((int) ($placement['signature_id'] ?? 0));
            if (! $signature) {
                continue;
            }
            $resolvedPlacements[] = [
                'signature_id' => $signature->id,
                'signature_path' => $signature->file_path,
                'page_number' => max(1, (int) ($placement['page_number'] ?? 1)),
                'x_percent' => (float) ($placement['x_percent'] ?? 0),
                'y_percent' => (float) ($placement['y_percent'] ?? 0),
                'width_percent' => max(1, (float) ($placement['width_percent'] ?? 20)),
                'height_percent' => max(1, (float) ($placement['height_percent'] ?? 10)),
            ];
        }

        if (count($resolvedPlacements) === 0) {
            return back()->withErrors(['placements' => 'None of the submitted signatures could be matched to your account - try again.']);
        }

        $file = $request->file('document');
        $originalName = $file->getClientOriginalName();
        $mimeType = (string) $file->getMimeType();
        $originalPath = $file->store('signed-documents/originals', 'public');

        $signedPath = null;
        $stampError = null;

        if (str_starts_with($mimeType, 'image/')) {
            [$signedPath, $stampError] = $this->stampImageWithPlacements($originalPath, $resolvedPlacements);
        } elseif ($mimeType === 'application/pdf') {
            [$signedPath, $stampError] = $this->stampPdfWithPlacements($originalPath, $resolvedPlacements);
        } else {
            $stampError = "This file type isn't a supported image or PDF, so it can't be stamped automatically - it'll be stored as-is.";
        }

        $wasStamped = (bool) $signedPath;

        session(['signature_pending_preview' => [
            'original_filename' => $originalName,
            'original_path' => $originalPath,
            'signed_path' => $signedPath,
            'was_stamped' => $wasStamped,
            'stamp_error' => $wasStamped ? null : $stampError,
            'mime_type' => $mimeType,
            'placements' => $resolvedPlacements,
        ]]);

        return redirect()->route('signature.show');
    }

    /**
     * Streams the pending (not-yet-saved) preview file inline through
     * Laravel itself, rather than the direct public-disk URL the view
     * used before (Storage::disk('public')->url()) - that approach
     * depends on the storage symlink actually being set up correctly
     * on the server and on the web server having direct access to that
     * path, neither of which this endpoint needs: it reads the file via
     * PHP and streams it back, the same robust approach
     * downloadDocument() below already uses for saved documents.
     * Session-scoped rather than requiring an ID in the URL, since
     * this file has no SignedDocument record yet to authorize against.
     */
    public function previewPendingFile(Request $request)
    {
        $pending = session('signature_pending_preview');
        abort_unless($pending, 404);

        $path = $pending['signed_path'] ?? $pending['original_path'];
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, ['Content-Disposition' => 'inline']);
    }

    /**
     * Step 2 of 2 - confirms the pending preview: creates the permanent
     * SignedDocument record AND one SignedDocumentPlacement row per
     * placement, so reopening the document later shows exactly what was
     * placed where (position/page/size), not just the flattened result.
     */
    public function confirmDocument(Request $request): RedirectResponse
    {
        $pending = session('signature_pending_preview');

        if (! $pending) {
            return redirect()->route('signature.show')->withErrors(['document' => 'Nothing pending to confirm - try uploading again.']);
        }

        $document = SignedDocument::create([
            'user_id' => $request->user()->id,
            'signature_id' => $pending['placements'][0]['signature_id'] ?? null,
            'original_filename' => $pending['original_filename'],
            'original_file_path' => $pending['original_path'],
            'signed_file_path' => $pending['signed_path'],
            'was_stamped' => $pending['was_stamped'],
            'stamp_error' => $pending['stamp_error'] ?? null,
            'signed_at' => now(),
            'mime_type' => $pending['mime_type'],
        ]);

        foreach ($pending['placements'] as $placement) {
            $document->placements()->create([
                'signature_id' => $placement['signature_id'],
                'page_number' => $placement['page_number'],
                'x_percent' => $placement['x_percent'],
                'y_percent' => $placement['y_percent'],
                'width_percent' => $placement['width_percent'],
                'height_percent' => $placement['height_percent'],
            ]);
        }

        session()->forget('signature_pending_preview');

        return redirect()->route('signature.show')->with('success', $pending['was_stamped']
            ? 'Document signed and saved.'
            : 'Document saved.');
    }

    public function cancelPreview(Request $request): RedirectResponse
    {
        $pending = session('signature_pending_preview');

        if ($pending) {
            Storage::disk('public')->delete(array_filter([$pending['original_path'], $pending['signed_path']]));
            session()->forget('signature_pending_preview');
        }

        return redirect()->route('signature.show');
    }

    /**
     * Public, no-login-required document access for external
     * recipients (e.g. a client viewing what they just signed) - the
     * `signed` route middleware validates this URL was genuinely
     * generated by this app and hasn't expired, which is the
     * security boundary here instead of an owner check. See
     * signature/show.blade.php's share links for where this URL
     * actually gets generated (URL::temporarySignedRoute()).
     */
    public function showSharedDocument(Request $request, SignedDocument $signedDocument)
    {
        $path = $signedDocument->signed_file_path ?? $signedDocument->original_file_path;
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, $signedDocument->original_filename, ['Content-Disposition' => 'inline']);
    }

    public function downloadDocument(Request $request, SignedDocument $signedDocument)
    {
        abort_unless($signedDocument->user_id === $request->user()->id, 403);

        $path = $signedDocument->signed_file_path ?? $signedDocument->original_file_path;

        return Storage::disk('public')->download($path, $signedDocument->original_filename);
    }

    /**
     * Streams the signed file inline (not as an attachment) so the owner
     * can VIEW it in the browser - PDFs render in the tab, images show
     * directly - rather than only ever downloading it. Same inline
     * disposition the public shared route uses, but gated to the owner.
     */
    public function viewDocument(Request $request, SignedDocument $signedDocument)
    {
        abort_unless($signedDocument->user_id === $request->user()->id, 403);

        $path = $signedDocument->signed_file_path ?? $signedDocument->original_file_path;
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, $signedDocument->original_filename, ['Content-Disposition' => 'inline']);
    }

    public function destroyDocument(Request $request, SignedDocument $signedDocument): RedirectResponse
    {
        abort_unless($signedDocument->user_id === $request->user()->id, 403);

        Storage::disk('public')->delete(array_filter([$signedDocument->original_file_path, $signedDocument->signed_file_path]));
        $signedDocument->delete();

        return back()->with('success', 'Document removed.');
    }

    public function bulkDestroyDocuments(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'document_ids' => ['required', 'array'],
            'document_ids.*' => ['integer'],
        ]);

        $documents = SignedDocument::where('user_id', $request->user()->id)
            ->whereIn('id', $data['document_ids'])
            ->get();

        foreach ($documents as $document) {
            Storage::disk('public')->delete(array_filter([$document->original_file_path, $document->signed_file_path]));
        }

        $count = $documents->count();
        SignedDocument::whereIn('id', $documents->pluck('id'))->delete();

        return back()->with('success', $count === 1 ? '1 document removed.' : "{$count} documents removed.");
    }

    /**
     * Composites EVERY placement targeting page 1 (the only "page" an
     * image has) onto the document - sequential GD composites, one per
     * placement, all saved as a single output image.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function stampImageWithPlacements(string $documentPath, array $placements): array
    {
        if (! extension_loaded('gd')) {
            return [null, 'The GD image library isn\'t enabled on this server, so images can\'t be stamped automatically. Ask whoever manages hosting to enable the php-gd extension.'];
        }

        $documentFullPath = Storage::disk('public')->path($documentPath);
        $document = $this->loadGdImage($documentFullPath);

        if (! $document) {
            return [null, 'The uploaded document could not be read as an image.'];
        }

        $docWidth = imagesx($document);
        $docHeight = imagesy($document);
        $anyStamped = false;
        $lastError = null;

        foreach ($placements as $placement) {
            if ($placement['page_number'] !== 1) {
                continue; // an image only ever has one "page"
            }

            $signatureFullPath = Storage::disk('public')->path($placement['signature_path']);
            $signature = $this->loadGdImage($signatureFullPath);
            if (! $signature) {
                $lastError = 'One of your saved signature files could not be read as an image.';
                continue;
            }

            $sigWidth = imagesx($signature);
            $sigHeight = imagesy($signature);
            $targetWidth = max(1, (int) round($docWidth * $placement['width_percent'] / 100));
            $targetHeight = max(1, (int) round($docHeight * $placement['height_percent'] / 100));

            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
            imagealphablending($resized, false);
            imagecopyresampled($resized, $signature, 0, 0, 0, 0, $targetWidth, $targetHeight, $sigWidth, $sigHeight);

            $destX = (int) round($docWidth * $placement['x_percent'] / 100);
            $destY = (int) round($docHeight * $placement['y_percent'] / 100);
            $destX = max(0, min($destX, $docWidth - $targetWidth));
            $destY = max(0, min($destY, $docHeight - $targetHeight));

            imagealphablending($document, true);
            imagesavealpha($document, true);
            imagecopy($document, $resized, $destX, $destY, 0, 0, $targetWidth, $targetHeight);

            imagedestroy($signature);
            imagedestroy($resized);
            $anyStamped = true;
        }

        if (! $anyStamped) {
            imagedestroy($document);

            return [null, $lastError ?? 'None of the placements applied to this document.'];
        }

        $outputPath = 'signed-documents/signed/' . uniqid('signed_') . '.png';
        $this->ensureDirectoryExists($outputPath);
        $saved = imagepng($document, Storage::disk('public')->path($outputPath));
        imagedestroy($document);

        return $saved ? [$outputPath, null] : [null, 'The stamped image could not be saved to disk - check storage permissions.'];
    }

    /**
     * Walks every page of the PDF via FPDI, and for whichever page(s)
     * have placements targeting them, draws each one at its stored
     * position/size (converted from percentages to that page's actual
     * millimeter dimensions).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function stampPdfWithPlacements(string $documentPath, array $placements): array
    {
        if (! class_exists(\setasign\Fpdi\Fpdi::class)) {
            return [null, 'PDF signing needs one more component installed on the server (setasign/fpdi) - ask your developer to run: composer require setasign/fpdi setasign/fpdf'];
        }

        $documentFullPath = Storage::disk('public')->path($documentPath);

        try {
            $pdf = new \setasign\Fpdi\Fpdi();
            $pageCount = $pdf->setSourceFile($documentFullPath);
            $placementsByPage = collect($placements)->groupBy('page_number');
            $anyStamped = false;

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                foreach ($placementsByPage->get($pageNo, []) as $placement) {
                    $signatureFullPath = Storage::disk('public')->path($placement['signature_path']);
                    if (! is_file($signatureFullPath)) {
                        continue;
                    }

                    $widthMm = $size['width'] * $placement['width_percent'] / 100;
                    $heightMm = $size['height'] * $placement['height_percent'] / 100;
                    $xMm = max(0, min($size['width'] * $placement['x_percent'] / 100, $size['width'] - $widthMm));
                    $yMm = max(0, min($size['height'] * $placement['y_percent'] / 100, $size['height'] - $heightMm));

                    $pdf->Image($signatureFullPath, $xMm, $yMm, $widthMm, $heightMm);
                    $anyStamped = true;
                }
            }

            if (! $anyStamped) {
                return [null, 'None of the placements applied to this document - check they were dropped onto a valid page.'];
            }

            $outputPath = 'signed-documents/signed/' . uniqid('signed_') . '.pdf';
            $this->ensureDirectoryExists($outputPath);
            $pdf->Output(Storage::disk('public')->path($outputPath), 'F');

            return [$outputPath, null];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PDF signature stamping failed.', ['error' => $e->getMessage()]);

            return [null, 'The PDF could not be stamped (' . $e->getMessage() . ') - it will be stored as-is.'];
        }
    }

    /**
     * FPDI's Output() and GD's imagepng() both write via raw PHP
     * filesystem calls, not through Laravel's Storage abstraction - so
     * unlike Storage::put(), they do NOT auto-create a missing directory
     * and just fail with "no such file or directory" instead. This
     * makes sure the folder exists first.
     */
    private function ensureDirectoryExists(string $storagePath): void
    {
        $directory = dirname(Storage::disk('public')->path($storagePath));

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
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
