<?php

namespace App\Modules\Listing\Http\Requests;

use App\Core\Http\Requests\BaseFormRequest;

class UploadListingMediaRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $max = (int) config('aqdar.files.media.max_kb', 10240);

        return [
            'file' => ['required', 'file', 'max:'.$max, 'mimes:'.implode(',', config('aqdar.files.media.mimes'))],
            'caption' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
