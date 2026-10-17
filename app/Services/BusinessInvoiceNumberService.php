<?php

namespace App\Services;

use App\Models\BusinessInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sequential, zero-padded invoice numbers (001, 002 … 999, 1000) scoped to
 * the issuing business. A "business" is the owning user: invoices, templates
 * and the business card all hang off user_id, and business_invoices already
 * has a unique (user_id, invoice_number) index.
 */
class BusinessInvoiceNumberService
{
    public const PAD_LENGTH = 3;

    /**
     * Randomly generated numbers from before sequential numbering
     * (BI-2026-K3JD8X92 / BI-2026-0A1B2C3D). Their trailing characters are
     * random, so they never seed the new sequence.
     */
    private const LEGACY_RANDOM_PATTERN = '/^BI-\d{4}-[A-Z0-9]{8}$/';

    /** Preview the next number (no lock; for display only). */
    public function peekNext(int $userId): string
    {
        $issued = (int) User::query()->whereKey($userId)->value('last_business_invoice_number');

        return $this->format(max($issued, $this->highestNumber($userId)) + 1);
    }

    /**
     * Create an invoice with the next number for its business. The owner's
     * user row is locked for the duration of the transaction so concurrent
     * requests for the same business are serialised; the unique index is the
     * final guard. The user's last_business_invoice_number keeps the
     * high-water mark, so deleting the newest invoice never frees its number.
     */
    public function createInvoice(int $userId, array $attributes): BusinessInvoice
    {
        return DB::transaction(function () use ($userId, $attributes): BusinessInvoice {
            $owner = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $next = max((int) $owner->last_business_invoice_number, $this->highestNumber($userId)) + 1;

            $invoice = BusinessInvoice::query()->create([
                ...$attributes,
                'user_id' => $userId,
                'invoice_number' => $this->format($next),
            ]);

            $owner->forceFill(['last_business_invoice_number' => $next])->saveQuietly();

            return $invoice;
        });
    }

    public function format(int $number): string
    {
        return str_pad((string) $number, self::PAD_LENGTH, '0', STR_PAD_LEFT);
    }

    /** Numeric value of an invoice number, or null when it has none to offer. */
    public function parse(?string $invoiceNumber): ?int
    {
        $invoiceNumber = trim((string) $invoiceNumber);
        if ($invoiceNumber === '' || preg_match(self::LEGACY_RANDOM_PATTERN, $invoiceNumber)) {
            return null;
        }

        if (! preg_match('/(\d+)$/', $invoiceNumber, $matches)) {
            return null;
        }

        // Ignore absurdly long digit runs rather than overflowing an int.
        $digits = ltrim($matches[1], '0');

        return strlen($digits) > 15 ? null : (int) $digits;
    }

    public function highestNumber(int $userId): int
    {
        $highest = 0;
        BusinessInvoice::query()->where('user_id', $userId)->pluck('invoice_number')
            ->each(function (?string $number) use (&$highest): void {
                $highest = max($highest, $this->parse($number) ?? 0);
            });

        return $highest;
    }
}
