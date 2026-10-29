<?php

namespace App\Modules\Listing\Services;

use App\Core\Enums\ListingStatus;
use Illuminate\Validation\ValidationException;

class ListingStateMachine
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['in_review', 'rejected'],
        'in_review' => ['needs_changes', 'approved', 'rejected'],
        'needs_changes' => ['resubmitted', 'rejected'],
        'resubmitted' => ['in_review', 'rejected'],
        'approved' => ['published', 'rejected', 'archived'],
        'published' => ['hold', 'closed', 'approved', 'under_offer', 'archived', 'in_review'],
        'under_offer' => ['published', 'hold', 'closed', 'approved', 'archived', 'in_review'],
        'hold' => ['published', 'closed', 'approved', 'archived'],
        'closed' => [],
        'archived' => ['in_review'],
        'rejected' => ['in_review'],
    ];

    public function assertCanTransition(ListingStatus $from, ListingStatus $to): void
    {
        $allowed = self::TRANSITIONS[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => [__('aqdar.listings.invalid_transition', [
                    'from' => $from->value,
                    'to' => $to->value,
                ])],
            ]);
        }
    }

    public function canTransition(ListingStatus $from, ListingStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }
}
