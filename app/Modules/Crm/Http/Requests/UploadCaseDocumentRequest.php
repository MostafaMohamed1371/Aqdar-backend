<?php

namespace App\Modules\Crm\Http\Requests;

use App\Core\Enums\CaseDocumentKind;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UploadCaseDocumentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $max = (int) config('aqdar.files.documents.max_kb', 10240);

        return [
            'kind' => ['nullable', Rule::enum(CaseDocumentKind::class)],
            'file' => ['required', 'file', 'max:'.$max, 'mimes:'.implode(',', config('aqdar.files.documents.mimes'))],
        ];
    }
}
