<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CandidateDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'document_type_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size_kb',
        'status',
        'rejection_reason',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'verified_at'  => 'datetime',
        'file_size_kb' => 'integer',
    ];

    protected $appends = ['file_url'];

    /**
     * Get accessible temporary URL for the uploaded document.
     */
    public function getFileUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        try {
            return Storage::disk('private')->temporaryUrl($this->file_path, now()->addMinutes(30));
        } catch (\Throwable $th) {
            return null;
        }
    }

    /**
     * Get the candidate owning this document.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Get the document type specification.
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * Get the staff user who verified/rejected this document.
     */
    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
