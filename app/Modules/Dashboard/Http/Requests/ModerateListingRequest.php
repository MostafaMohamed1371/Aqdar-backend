<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;
use App\Modules\Listing\Models\ListingStatusHistory;

class ModerateListingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'field_notes' => ['sometimes', 'array:'.implode(',', ListingStatusHistory::NOTE_SECTIONS)],
            'field_notes.*' => ['nullable', 'string', 'max:300'],
        ];
    }
}
