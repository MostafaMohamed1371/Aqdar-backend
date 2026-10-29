<?php

namespace App\Modules\Crm\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class StoreSupportMessageRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:2000'],
            'consent' => ['required', 'accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->exists('consent')) {
            $this->merge(['consent' => $this->boolean('consent')]);
        }
    }

    protected function fieldsToTrim(): array
    {
        return array_merge(parent::fieldsToTrim(), ['body']);
    }
}
