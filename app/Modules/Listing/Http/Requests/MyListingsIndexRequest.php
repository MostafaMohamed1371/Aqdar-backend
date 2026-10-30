<?php

namespace App\Modules\Listing\Http\Requests;

use App\Core\Enums\ListingStatus;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class MyListingsIndexRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $statuses = array_map(fn (ListingStatus $status) => $status->value, ListingStatus::cases());

        return [
            'status' => ['sometimes', 'string', Rule::in([...$statuses, 'review', 'live', 'ended'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
