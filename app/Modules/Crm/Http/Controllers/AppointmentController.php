<?php

namespace App\Modules\Crm\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Crm\Http\Requests\StoreAppointmentRequest;
use App\Modules\Crm\Http\Resources\AppointmentResource;
use App\Modules\Crm\Models\Appointment;
use App\Modules\Crm\Services\AppointmentService;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class AppointmentController extends BaseController
{
    public function __construct(private AppointmentService $appointments) {}

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $appointment = $this->appointments->create($user, $request->validated());

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(201);
    }

    public function slots(Request $request): JsonResponse
    {
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return response()->json(['data' => $this->appointments->slotsFor(Carbon::parse($validated['date']))]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return AppointmentResource::collection($this->appointments->forBuyer($user));
    }

    public function show(Request $request, Appointment $appointment): AppointmentResource
    {
        /** @var User $user */
        $user = $request->user();

        return new AppointmentResource($this->appointments->showForBuyer($user, $appointment));
    }
}
