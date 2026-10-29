<?php

namespace App\Modules\Listing\Policies;

use App\Core\Enums\ListingStatus;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;

class ListingPolicy
{
    public function view(User $user, Listing $listing): bool
    {
        return $listing->user_id === $user->id;
    }

    public function update(User $user, Listing $listing): bool
    {
        return $listing->user_id === $user->id && $listing->isEditable();
    }

    public function submit(User $user, Listing $listing): bool
    {
        return $listing->user_id === $user->id && $listing->status === ListingStatus::Draft;
    }

    public function resubmit(User $user, Listing $listing): bool
    {
        return $listing->user_id === $user->id && $listing->status === ListingStatus::NeedsChanges;
    }
}
