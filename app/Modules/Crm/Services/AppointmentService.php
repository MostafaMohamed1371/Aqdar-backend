<?php

namespace App\Modules\Crm\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\LeadStatus;
use App\Core\Enums\Permission;
use App\Core\Enums\PublicCodePrefix;
use App\Core\Identity\PublicCodeGenerator;
use App\Modules\Crm\Models\Appointment;
use App\Modules\Dashboard\Support\DeskRealtime;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Services\ContactLeakScanner;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Staff\Models\StaffUser;
use App\Modules\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function __construct(
        private LeadService $leads,
        private PublicCodeGenerator $codes,
        private ContactLeakScanner $scanner,
        private AuditLogger $audit,
        private NotificationService $notifications,
    ) {}

    /** @return array{date: string, open: bool, slots: list<array{value: string, available: bool}>} */
    public function slotsFor(CarbonInterface $date): array
    {
        $open = $this->isBookableDay($date);

        return [
            'date' => $date->toDateString(),
            'open' => $open,
            'slots' => collect(config('aqdar.appointments.slots', []))
                ->map(fn (string $slot) => [
                    'value' => $slot,
                    'available' => $open && $date->copy()->setTimeFromTimeString($slot)->isFuture(),
                ])
                ->values()
                ->all(),
        ];
    }

    public function isBookableDay(CarbonInterface $date): bool
    {
        $day = $date->copy()->startOfDay();

        return ! $day->isBefore(today())
            && ! $day->isAfter(today()->addDays((int) config('aqdar.appointments.days_ahead', 30)))
            && ! in_array($day->dayOfWeek, config('aqdar.appointments.closed_weekdays', []), true);
    }

    public function create(User $buyer, array $data): Appointment
    {
        $listing = Listing::query()->find($data['listing_id']);
        abort_if($listing === null || ! $listing->isPubliclyVisible(), 404);

        if ((int) $listing->user_id === (int) $buyer->id) {
            throw ValidationException::withMessages([
                'listing_id' => [__('aqdar.crm.cannot_inquire_own')],
            ]);
        }

        $date = Carbon::parse($data['requested_date']);
        $slot = collect($this->slotsFor($date)['slots'])->firstWhere('value', $data['requested_slot']);
        if ($slot === null || ! $slot['available']) {
            throw ValidationException::withMessages([
                'requested_slot' => [__('aqdar.crm.slot_unavailable')],
            ]);
        }

        $notes = $data['notes'] ?? null;
        if (is_string($notes) && $notes !== '' && $this->scanner->scan($notes) !== null) {
            throw ValidationException::withMessages([
                'notes' => [__('aqdar.crm.message_no_contact')],
            ]);
        }

        $appointment = DB::transaction(function () use ($buyer, $listing, $data, $notes): Appointment {
            $lead = $this->leads->openOrCreateForInquiry($buyer, $listing);
            $lead->forceFill([
                'next_action' => 'viewing',
                'next_action_at' => $data['requested_date'],
            ])->save();

            return Appointment::query()->create([
                'public_code' => $this->codes->next(PublicCodePrefix::Appointment),
                'user_id' => $buyer->id,
                'listing_id' => $listing->id,
                'lead_id' => $lead->id,
                'requested_date' => $data['requested_date'],
                'requested_slot' => $data['requested_slot'],
                'notes' => $notes,
                'status' => AppointmentStatus::Requested,
                'consent_at' => now(),
            ]);
        });

        $this->audit->write('appointment.requested', $buyer, $appointment, [
            'listing_id' => $listing->id,
        ]);

        $appointment->loadMissing(['listing.media', 'lead.assignee']);
        $context = [
            'public_code' => $appointment->public_code,
            'listing_code' => $listing->public_code,
            'listing_id' => $listing->id,
            'lead_code' => $appointment->lead?->public_code,
            'status' => AppointmentStatus::Requested->value,
        ];
        $this->notifications->notifySafely($buyer, 'appointment_created', $context, 'appointment_created:'.$appointment->id);
        $assignee = $appointment->lead?->assignee;
        if ($assignee !== null) {
            $this->notifications->notifySafely(
                $assignee,
                'staff_new_appointment',
                $context,
                'staff_new_appointment:'.$appointment->id.':'.$assignee->id,
            );
        } else {
            $this->notifications->notifyStaffQueue(
                'staff_new_appointment',
                $context,
                Permission::ManageCrm,
                true,
                'staff_new_appointment:'.$appointment->id,
            );
        }
        if ($appointment->lead_id) {
            DeskRealtime::push('leads', 'appointment', (int) $appointment->lead_id, meta: [
                'public_code' => $appointment->lead?->public_code,
                'status' => $appointment->lead?->status?->value,
            ]);
        }

        return $appointment;
    }

    public function forBuyer(User $buyer): LengthAwarePaginator
    {
        return Appointment::query()
            ->where('user_id', $buyer->id)
            ->with(['listing.media', 'lead:id,public_code,status'])
            ->latest('id')
            ->paginate(20);
    }

    public function showForBuyer(User $buyer, Appointment $appointment): Appointment
    {
        abort_unless((int) $appointment->user_id === (int) $buyer->id, 404);

        return $appointment->load(['listing.media', 'lead']);
    }

    public function setStatus(StaffUser $staff, Appointment $appointment, AppointmentStatus $status): Appointment
    {
        $lead = $appointment->lead;
        abort_if($lead === null, 404);
        $this->leads->assertCanAct($staff, $lead);

        $appointment->forceFill(['status' => $status])->save();

        if ($status === AppointmentStatus::Confirmed) {
            $this->leads->setStatus($lead, $staff, LeadStatus::VisitScheduled);
            $lead->forceFill([
                'next_action' => 'viewing',
                'next_action_at' => $appointment->requested_date,
            ])->save();
        }

        $this->audit->write('appointment.status', $staff, $appointment, [
            'status' => $status->value,
        ]);

        $fresh = $appointment->fresh(['buyer', 'listing', 'lead']) ?? $appointment;
        if ($fresh->buyer !== null) {
            $this->notifications->notifySafely($fresh->buyer, 'appointment_updated', [
                'public_code' => $fresh->public_code,
                'listing_code' => $fresh->listing?->public_code,
                'listing_id' => $fresh->listing_id,
                'status' => $status->value,
            ], 'appointment_updated:'.$fresh->id.':'.$status->value);
        }
        if ($fresh->lead_id) {
            DeskRealtime::push('leads', 'appointment_status', (int) $fresh->lead_id, meta: [
                'public_code' => $fresh->lead?->public_code,
                'status' => $fresh->lead?->status?->value,
            ]);
        }

        return $fresh;
    }
}
