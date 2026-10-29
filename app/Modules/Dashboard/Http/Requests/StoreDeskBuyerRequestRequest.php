<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\BuyerIntent;
use App\Core\Enums\ContactMethod;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreDeskBuyerRequestRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'buyer_id' => ['required', 'integer', 'exists:users,id'],
            'intent' => ['required', Rule::enum(BuyerIntent::class)],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'area_sqm_min' => ['nullable', 'numeric', 'min:0'],
            'area_sqm_max' => ['nullable', 'numeric', 'min:0'],
            'contact_method' => ['nullable', Rule::enum(ContactMethod::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:staff_users,id'],
        ];
    }
}
