<?php

namespace Tests\Feature;

use App\Core\Enums\StaffRole;
use App\Modules\Dashboard\Services\StaffGuideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardGuideTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_any_signed_in_staff_can_open_guide_and_download_pdf(): void
    {
        $this->actingAsStaff(StaffRole::Sales);

        $this->get(route('dashboard.guide'))
            ->assertOk()
            ->assertSee('GUIDE', false)
            ->assertSee(__('aqdar.guide.title'))
            ->assertSee(__('aqdar.guide.hero_title'))
            ->assertSee(__('aqdar.guide.download_pdf'))
            ->assertSee('guide-hero', false)
            ->assertSee('guide-section', false)
            ->assertSee('ADM-02', false)
            ->assertSee(route('dashboard.guide.pdf'), false);

        $pdf = $this->get(route('dashboard.guide.pdf'));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('Content-Type'));
    }

    public function test_guest_cannot_open_guide(): void
    {
        $this->get(route('dashboard.guide'))->assertRedirect();
        $this->get(route('dashboard.guide.pdf'))->assertRedirect();
    }

    public function test_guide_service_covers_all_adm_screens(): void
    {
        $ids = collect(app(StaffGuideService::class)->sections())->pluck('id');

        foreach (['overview', 'flow_listing', 'flow_crm', 'roles', 'mobile'] as $required) {
            $this->assertTrue($ids->contains($required), "missing section {$required}");
        }

        for ($n = 1; $n <= 15; $n++) {
            $id = 'adm_'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $this->assertTrue($ids->contains($id), "missing {$id}");
        }

        $binary = app(StaffGuideService::class)->pdfBinary();
        $this->assertStringStartsWith('%PDF', $binary);
        $this->assertGreaterThan(500, strlen($binary));
    }
}
