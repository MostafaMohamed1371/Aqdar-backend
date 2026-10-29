<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\CaseStage;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateCaseStageRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(CaseStage::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'requested_document_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
