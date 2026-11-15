<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SignedDocument extends Model
{
    protected $fillable = [
        'user_id', 'signature_id', 'original_filename', 'original_file_path', 'signed_file_path',
        'was_stamped', 'stamp_error', 'position', 'position_x', 'position_y', 'signed_at', 'mime_type',
    ];

    protected $casts = [
        'was_stamped' => 'boolean',
        'signed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function signature()
    {
        return $this->belongsTo(Signature::class);
    }

    public function placements()
    {
        return $this->hasMany(SignedDocumentPlacement::class);
    }

    public function originalUrl(): string
    {
        return Storage::disk('public')->url($this->original_file_path);
    }

    public function signedUrl(): ?string
    {
        return $this->signed_file_path ? Storage::disk('public')->url($this->signed_file_path) : null;
    }

    /**
     * Whichever file actually represents "the document as it stands" -
     * the signed version if one exists, otherwise the plain original
     * (e.g. a file type that couldn't be auto-stamped).
     */
    public function displayUrl(): string
    {
        return $this->signedUrl() ?? $this->originalUrl();
    }
}
