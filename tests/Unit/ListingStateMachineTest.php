<?php

namespace Tests\Unit;

use App\Core\Enums\ListingStatus;
use App\Modules\Listing\Services\ListingStateMachine;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ListingStateMachineTest extends TestCase
{
    public function test_draft_cannot_jump_to_published(): void
    {
        $this->expectException(ValidationException::class);

        app(ListingStateMachine::class)->assertCanTransition(
            ListingStatus::Draft,
            ListingStatus::Published,
        );
    }

    public function test_allowed_seller_and_review_path(): void
    {
        $machine = app(ListingStateMachine::class);

        $this->assertTrue($machine->canTransition(ListingStatus::Draft, ListingStatus::Submitted));
        $this->assertTrue($machine->canTransition(ListingStatus::Submitted, ListingStatus::InReview));
        $this->assertTrue($machine->canTransition(ListingStatus::NeedsChanges, ListingStatus::Resubmitted));
        $this->assertTrue($machine->canTransition(ListingStatus::Approved, ListingStatus::Published));
        $this->assertFalse($machine->canTransition(ListingStatus::Rejected, ListingStatus::Published));
        $this->assertTrue($machine->canTransition(ListingStatus::Rejected, ListingStatus::InReview));
        $this->assertTrue($machine->canTransition(ListingStatus::Published, ListingStatus::Approved));
        $this->assertTrue($machine->canTransition(ListingStatus::Published, ListingStatus::UnderOffer));
        $this->assertTrue($machine->canTransition(ListingStatus::Published, ListingStatus::Archived));
        $this->assertTrue($machine->canTransition(ListingStatus::Published, ListingStatus::InReview));
        $this->assertTrue($machine->canTransition(ListingStatus::UnderOffer, ListingStatus::Published));
        $this->assertTrue($machine->canTransition(ListingStatus::Archived, ListingStatus::InReview));
    }
}
