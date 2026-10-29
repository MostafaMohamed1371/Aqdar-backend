<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\BuyerRequestStatus;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateBuyerRequestStatusRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(BuyerRequestStatus::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
