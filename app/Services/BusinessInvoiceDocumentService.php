<?php

namespace App\Services;

use App\Models\BusinessInvoice;
use Barryvdh\DomPDF\PDF;
use Barryvdh\DomPDF\Facade\Pdf as DomPdfFacade;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BusinessInvoiceDocumentService
{
    public function pdf(BusinessInvoice $invoice, string $title = 'INVOICE'): PDF
    {
        $invoice->loadMissing('template');

        if ($invoice->template && ! $this->isPdfTemplate($invoice->template->storage_path)
            && Storage::disk('local')->exists($invoice->template->storage_path)) {
            $html = Storage::disk('local')->get($invoice->template->storage_path);

            return DomPdfFacade::loadHTML($this->renderTemplate($html, $invoice, $title))
                ->setPaper('a4')
                ->setOptions([
                    'isRemoteEnabled' => false,
                    'isPhpEnabled' => false,
                    'isJavascriptEnabled' => false,
                    'chroot' => storage_path('app/private'),
                ]);
        }

        return DomPdfFacade::loadView('business-invoices.pdf', [
            'invoice' => $invoice,
            'documentTitle' => $title,
        ])->setPaper('a4');
    }

    /** Return the final PDF bytes, prepending uploaded PDF stationery pages. */
    public function output(BusinessInvoice $invoice, string $title = 'INVOICE'): string
    {
        $basePdf = $this->pdf($invoice, $title)->output();
        $templatePath = $invoice->template?->storage_path;

        if (! $templatePath || ! $this->isPdfTemplate($templatePath)
            || ! Storage::disk('local')->exists($templatePath)) {
            return $basePdf;
        }

        $merged = new Fpdi;
        $this->appendPdfFile($merged, Storage::disk('local')->path($templatePath));
        $this->appendPdfStream($merged, $basePdf);

        return $merged->Output('S');
    }

    public function validatePdfTemplate(string $contents): ?string
    {
        if (! str_starts_with($contents, '%PDF-')) {
            return 'The uploaded file is not a valid PDF.';
        }

        try {
            $reader = new Fpdi;
            if ($reader->setSourceFile(StreamReader::createByString($contents)) < 1) {
                return 'The PDF template must contain at least one page.';
            }
        } catch (\Throwable) {
            return 'This PDF could not be read. Upload an unencrypted PDF file.';
        }

        return null;
    }

    private function isPdfTemplate(string $path): bool
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function appendPdfFile(Fpdi $target, string $path): void
    {
        $pageCount = $target->setSourceFile($path);
        for ($page = 1; $page <= $pageCount; $page++) {
            $templateId = $target->importPage($page);
            $size = $target->getTemplateSize($templateId);
            $target->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $target->useTemplate($templateId);
        }
    }

    private function appendPdfStream(Fpdi $target, string $contents): void
    {
        $pageCount = $target->setSourceFile(StreamReader::createByString($contents));
        for ($page = 1; $page <= $pageCount; $page++) {
            $templateId = $target->importPage($page);
            $size = $target->getTemplateSize($templateId);
            $target->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $target->useTemplate($templateId);
        }
    }

    public function validateTemplate(string $html): ?string
    {
        if (strlen($html) > 512_000) {
            return 'The template must be no larger than 500 KB.';
        }

        $unsafePatterns = [
            '/<(script|iframe|object|embed|base|form|link|applet|img|svg|video|audio|source)\b/i',
            '/\bon[a-z]+\s*=/i',
            '/\b(src|href)\s*=/i',
            '/javascript\s*:/i',
            '/(?:https?|file|ftp)\s*:/i',
            '/@import\b|@font-face\b|url\s*\(|expression\s*\(/i',
        ];

        foreach ($unsafePatterns as $pattern) {
            if (preg_match($pattern, $html)) {
                return 'Templates cannot contain scripts, external resources, forms, or executable content.';
            }
        }

        if (! str_contains($html, '{{invoice_number}}') || ! str_contains($html, '{{line_items_table}}')) {
            return 'Add the {{invoice_number}} and {{line_items_table}} placeholders to the template.';
        }

        return null;
    }

    public function sampleTemplate(): string
    {
        return <<<'HTML'
<!doctype html>
<html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#1e293b;font-size:12px;padding:28px}
.brand{border-bottom:3px solid #047857;padding-bottom:14px;margin-bottom:24px}
h1{color:#047857;margin:0 0 6px}.muted{color:#64748b}
.columns{width:100%;margin:22px 0}.columns td{width:50%;vertical-align:top}
table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left}
th{background:#f1f5f9}.right{text-align:right}.total{font-size:17px;font-weight:bold;color:#047857}
</style></head><body>
<div class="brand"><h1>{{business_name}}</h1><div>{{business_address}}</div><div>{{business_email}} · {{business_phone}}</div></div>
<h2>{{document_title}}</h2><p>Invoice {{invoice_number}}</p>
<table class="columns"><tr><td><strong>Bill to</strong><br>{{customer_name}}<br>{{customer_email}}</td><td class="right"><strong>Issued</strong><br>{{issue_date}}<br><strong>Due</strong><br>{{due_date}}</td></tr></table>
{{line_items_table}}
<p class="total right">Total: {{currency}} {{total}}</p>
<p class="muted">Status: {{status}}</p>
</body></html>
HTML;
    }

    private function renderTemplate(string $html, BusinessInvoice $invoice, string $title): string
    {
        if ($error = $this->validateTemplate($html)) {
            throw new RuntimeException($error);
        }

        $escape = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows = '';
        foreach ($invoice->line_items ?? [] as $item) {
            $rows .= '<tr><td>'.$escape($item['description'] ?? '').'</td><td class="right">'.$escape($item['quantity'] ?? '').'</td><td class="right">'.$escape($invoice->currency).' '.number_format((float) ($item['unit_price'] ?? 0), 2).'</td><td class="right">'.$escape($invoice->currency).' '.number_format((float) ($item['amount'] ?? 0), 2).'</td></tr>';
        }
        $table = '<table><thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Unit price</th><th class="right">Amount</th></tr></thead><tbody>'.$rows.'</tbody></table>';

        $values = [
            '{{document_title}}' => $escape($title),
            '{{invoice_number}}' => $escape($invoice->invoice_number),
            '{{business_name}}' => $escape($invoice->business_name),
            '{{business_address}}' => $escape($invoice->business_address),
            '{{business_email}}' => $escape($invoice->business_email),
            '{{business_phone}}' => $escape($invoice->business_phone),
            '{{customer_name}}' => $escape($invoice->customer_name),
            '{{customer_email}}' => $escape($invoice->customer_email),
            '{{issue_date}}' => $escape($invoice->issue_date?->format('d M Y')),
            '{{due_date}}' => $escape($invoice->due_date?->format('d M Y') ?? 'On receipt'),
            '{{currency}}' => $escape($invoice->currency),
            '{{total}}' => number_format((float) $invoice->total, 2),
            '{{status}}' => $escape(ucfirst($invoice->status)),
            '{{line_items_table}}' => $table,
        ];

        return str_replace(array_keys($values), array_values($values), $html);
    }
}
