<?php

namespace Tests\Unit;

use App\Core\Enums\DocumentType;
use App\Core\Enums\MarketSegment;
use Tests\TestCase;

class DocumentTypeTest extends TestCase
{
    public function test_required_types_depend_on_segment(): void
    {
        $this->assertTrue(DocumentType::NationalId->isRequiredFor(MarketSegment::NonDistressed));
        $this->assertTrue(DocumentType::Contract->isRequiredFor(MarketSegment::NonDistressed));
        $this->assertTrue(DocumentType::PaymentProof->isRequiredFor(MarketSegment::Distressed));
        $this->assertFalse(DocumentType::DeveloperStatusLetter->isRequiredFor(MarketSegment::NonDistressed));
        $this->assertTrue(DocumentType::DeveloperStatusLetter->isRequiredFor(MarketSegment::Distressed));
    }
}
