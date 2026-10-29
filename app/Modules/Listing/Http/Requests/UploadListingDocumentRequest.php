<?php

namespace App\Modules\Listing\Http\Requests;

use App\Core\Enums\DocumentType;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UploadListingDocumentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $max = (int) config('aqdar.files.documents.max_kb', 10240);

        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'file' => ['required', 'file', 'max:'.$max, 'mimes:'.implode(',', config('aqdar.files.documents.mimes'))],
        ];
    }
}
