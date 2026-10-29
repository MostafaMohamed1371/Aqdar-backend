<?php

namespace App\Modules\Crm\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class StoreAppointmentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'integer'],
            'requested_date' => ['required', 'date', 'after_or_equal:today'],
            'requested_slot' => ['required', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:300'],
            'consent' => ['sometimes', 'accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->exists('consent')) {
            $this->merge(['consent' => $this->boolean('consent')]);
        }
    }
}
