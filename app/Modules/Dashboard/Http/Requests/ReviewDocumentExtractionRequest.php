<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ReviewDocumentExtractionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['confirmed', 'rejected'])],
            'fields' => ['nullable', 'array', 'max:20'],
            'fields.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
