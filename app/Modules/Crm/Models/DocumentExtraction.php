<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\ExtractionStatus;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'case_document_id',
    'driver',
    'status',
    'fields',
    'reviewed_by_staff_id',
    'reviewed_at',
])]
class DocumentExtraction extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ExtractionStatus::class,
            'fields' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(CaseDocument::class, 'case_document_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'reviewed_by_staff_id');
    }
}
