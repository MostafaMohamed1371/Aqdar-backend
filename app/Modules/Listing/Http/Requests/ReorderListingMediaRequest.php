<?php

namespace App\Modules\Listing\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class ReorderListingMediaRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.(int) config('aqdar.files.media.max_count', 20)],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /** @return list<int> */
    public function mediaIds(): array
    {
        return array_map('intval', array_values($this->validated('ids')));
    }
}
