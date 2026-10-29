<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Core\Enums\AppointmentStatus;
use App\Core\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateDeskAppointmentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
        ];
    }
}
