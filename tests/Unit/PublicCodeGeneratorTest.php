<?php

namespace Tests\Unit;

use App\Core\Enums\PublicCodePrefix;
use App\Core\Identity\PublicCodeGenerator;
use App\Modules\Crm\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_formats_listing_and_yearly_codes(): void
    {
        $generator = app(PublicCodeGenerator::class);

        $this->assertSame('AQ-0001', $generator->next(PublicCodePrefix::Listing));
        $this->assertSame('AQ-0002', $generator->next(PublicCodePrefix::Listing));
        $this->assertSame('INQ-2026-00001', $generator->next(PublicCodePrefix::Inquiry, 2026));
        $this->assertSame('INQ-2026-00002', $generator->next(PublicCodePrefix::Inquiry, 2026));
    }

    #[Test]
    public function it_continues_after_codes_already_on_the_table(): void
    {
        Lead::factory()->create(['public_code' => 'LD-2026-00001']);
        $generator = app(PublicCodeGenerator::class);

        $this->assertSame('LD-2026-00002', $generator->next(PublicCodePrefix::Lead, 2026));
    }
}
