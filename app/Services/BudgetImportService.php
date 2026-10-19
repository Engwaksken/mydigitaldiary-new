<?php

namespace App\Services;

use App\Models\User;
use App\Services\Ai\ActiveAiClient;
use App\Services\Ai\PromptGuard;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class BudgetImportService
{
    public function __construct(
        private readonly ActiveAiClient $ai,
        private readonly ReceiptExtractionService $receipts,
    ) {}

    public function extract(User $user, UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $source = null;

        if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
            $source = $this->receipts->extract($user, $file);
        } else {
            $text = match ($ext) {
                'xlsx','xls','csv' => $this->spreadsheetText($file),
                'pdf' => $this->pdfText($file),
                'docx' => $this->docxText($file),
                'doc' => throw new RuntimeException('Legacy .doc is not directly readable. Save it as .docx or PDF and try again.'),
                default => throw new RuntimeException('Unsupported budget file type.'),
            };
            if ($ext === 'pdf' && trim($text) === '') {
                // Scanned PDF: reuse the same vision extraction flow used by Expenses.
                $source = $this->receipts->extract($user, $file);
            } else {
                $source = ['document_text' => mb_substr($text, 0, 30000)];
            }
        }

        $system = <<<'SYS'
Extract a budget into JSON with keys: title, currency, start_date, end_date, confidence, notes, items.
items must contain: category, sub_category, description, planned_amount, actual_amount, period, month_year, date, notes, confidence.
Valid period values are weekly, monthly, annually. Use monthly when the period is unclear.
Use null when unknown and never invent an amount that is not supported by the source.
SYS;

        // The file name and contents are both untrusted, so both are fenced.
        $prompt = 'Filename: '.PromptGuard::data('filename', $file->getClientOriginalName())
            . "\nMIME: {$file->getMimeType()}\n\nSource data:\n"
            . PromptGuard::data('document', (string) json_encode($source, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

        $result = $this->ai->json($system, $prompt);
        $items = array_values(array_filter($result['items'] ?? [], 'is_array'));

        return [
            'title' => (string) ($result['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
            'currency' => (string) ($result['currency'] ?? 'UGX'),
            'start_date' => $result['start_date'] ?? null,
            'end_date' => $result['end_date'] ?? null,
            'confidence' => (float) ($result['confidence'] ?? 0),
            'notes' => $result['notes'] ?? null,
            'items' => array_map(fn ($item) => [
                'category' => trim((string) ($item['category'] ?? 'General')) ?: 'General',
                'sub_category' => $item['sub_category'] ?? null,
                'description' => trim((string) ($item['description'] ?? '')),
                'planned_amount' => is_numeric($item['planned_amount'] ?? null) ? (float) $item['planned_amount'] : 0,
                'actual_amount' => is_numeric($item['actual_amount'] ?? null) ? (float) $item['actual_amount'] : null,
                'period' => in_array($item['period'] ?? '', ['weekly','monthly','annually'], true) ? $item['period'] : 'monthly',
                'month_year' => $this->monthYear($item['month_year'] ?? $item['date'] ?? $result['start_date'] ?? null),
                'date' => $item['date'] ?? null,
                'notes' => $item['notes'] ?? null,
                'confidence' => (float) ($item['confidence'] ?? 0),
            ], $items),
            'source' => $ext,
            'filename' => $file->getClientOriginalName(),
        ];
    }

    private function monthYear(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') return null;
        try { return \Illuminate\Support\Carbon::parse($value)->format('Y-m'); }
        catch (\Throwable) { return preg_match('/^\d{4}-\d{2}$/', trim($value)) ? trim($value) : null; }
    }

    private function spreadsheetText(UploadedFile $file): string
    {
        $book = IOFactory::load($file->getRealPath());
        $chunks = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            $chunks[] = 'SHEET: '.$sheet->getTitle();
            foreach ($sheet->toArray(null, true, true, false) as $row) {
                $chunks[] = implode("\t", array_map(fn ($v) => (string) ($v ?? ''), $row));
            }
        }
        return implode("\n", $chunks);
    }

    private function pdfText(UploadedFile $file): string
    {
        $text = trim((new PdfParser())->parseFile($file->getRealPath())->getText());
        return $text;
    }

    private function docxText(UploadedFile $file): string
    {
        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) throw new RuntimeException('Could not open DOCX file.');
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! $xml) return '';
        $xml = str_replace(['</w:p>','</w:tr>'], ["\n","\n"], $xml);
        return trim(html_entity_decode(strip_tags($xml)));
    }
}
