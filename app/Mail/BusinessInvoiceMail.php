<?php

namespace App\Mail;

use App\Models\BusinessInvoice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Invoice / receipt email sent on behalf of a user's business. It is branded
 * with the issuing business (subject, From display name, greeting, sign-off),
 * while the From address stays the application's configured sender.
 */
class BusinessInvoiceMail extends Mailable
{
    public const KIND_INVOICE = 'invoice';

    public const KIND_RECEIPT = 'receipt';

    public function __construct(
        public BusinessInvoice $invoice,
        public string $pdf,
        public string $kind = self::KIND_INVOICE,
    ) {
    }

    public function businessName(): string
    {
        return $this->invoice->issuerName();
    }

    public function envelope(): Envelope
    {
        $business = $this->businessName();
        $subject = $this->kind === self::KIND_RECEIPT
            ? 'Receipt for invoice '.$this->invoice->invoice_number.' from '.$business
            : 'Invoice '.$this->invoice->invoice_number.' from '.$business;

        $replyTo = filled($this->invoice->business_email)
            ? [new Address($this->invoice->business_email, $business)]
            : [];

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $business),
            replyTo: $replyTo,
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'business-invoices.emails.invoice-text',
            with: [
                'invoice' => $this->invoice,
                'businessName' => $this->businessName(),
                'isReceipt' => $this->kind === self::KIND_RECEIPT,
            ],
        );
    }

    public function attachments(): array
    {
        $prefix = $this->kind === self::KIND_RECEIPT ? 'receipt-' : 'invoice-';

        return [
            Attachment::fromData(fn () => $this->pdf, $prefix.$this->invoice->invoice_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
