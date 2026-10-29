<?php

namespace App\Modules\Crm\Models;

use App\Core\Enums\CaseDocumentKind;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'case_id',
    'uploaded_by_user_id',
    'uploaded_by_staff_id',
    'kind',
    'disk',
    'path',
    'original_name',
    'mime',
    'size_bytes',
])]
class CaseDocument extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => CaseDocumentKind::class,
            'size_bytes' => 'integer',
        ];
    }

    public function transferCase(): BelongsTo
    {
        return $this->belongsTo(TransferCase::class, 'case_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function staffUploader(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'uploaded_by_staff_id');
    }

    public function contents(): string
    {
        return Storage::disk($this->disk)->get($this->path);
    }
}
