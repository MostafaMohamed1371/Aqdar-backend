<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffInboxController extends BaseController
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): View
    {
        /** @var StaffUser $staff */
        $staff = $request->user();

        return view('dashboard::inbox.index', [
            'rows' => $this->notifications->forStaff($staff),
        ]);
    }

    public function read(Request $request, InboxNotification $notification): RedirectResponse
    {
        /** @var StaffUser $staff */
        $staff = $request->user();
        $this->notifications->markReadFor($staff, $notification);

        return redirect()->to($this->destination($notification));
    }

    private function destination(InboxNotification $notification): string
    {
        $staff = request()->user();
        $data = is_array($notification->data) ? $notification->data : [];
        $event = (string) $notification->event_key;
        $listingId = (int) ($data['listing_id'] ?? 0);
        $listingCode = (string) ($data['listing_code'] ?? '');
        $leadCode = (string) ($data['lead_code'] ?? '');
        $publicCode = (string) ($data['public_code'] ?? '');

        if (str_starts_with($event, 'listing_') || $event === 'staff_new_submission') {
            if ($listingId > 0 && $staff?->can('approve_listing')) {
                return route('dashboard.listings.show', $listingId);
            }

            return route('dashboard.listings.index', array_filter(['q' => $listingCode ?: null]));
        }
        if (in_array($event, ['staff_unassigned_lead', 'sales_followup_overdue', 'inquiry_created', 'staff_inquiry_assigned'], true)) {
            return route('dashboard.leads.index', array_filter([
                'q' => $leadCode ?: $publicCode ?: null,
                'assigned_to' => $event === 'staff_unassigned_lead' ? 'unassigned' : null,
            ]));
        }
        if ($event === 'staff_new_buyer_request') {
            return route('dashboard.requests.index', array_filter(['q' => $publicCode ?: null]));
        }

        return route('dashboard.inbox');
    }
}
