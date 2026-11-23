<?php

namespace Tests\Integration;

use App\Core\Enums\StaffRole;
use App\Modules\Catalog\Models\Developer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DeveloperCrmFieldsTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_staff_can_store_and_see_developer_company_contact_fields(): void
    {
        $this->actingAsStaff();

        $this->post(route('dashboard.developers.store'), [
            'slug' => 'nile-dev',
            'name_ar' => 'النيل',
            'name_en' => 'Nile Dev',
            'contact_name' => 'Sara Account Manager',
            'contact_phone' => '+20 100 555 0101',
            'contact_email' => 'sara@nile.example',
            'website' => 'https://nile.example',
            'internal_notes' => 'Prefers email.',
        ])->assertRedirect(route('dashboard.developers.index'));

        $this->assertDatabaseHas('developers', [
            'slug' => 'nile-dev',
            'contact_name' => 'Sara Account Manager',
            'contact_email' => 'sara@nile.example',
            'internal_notes' => 'Prefers email.',
        ]);

        $this->get(route('dashboard.developers.index'))
            ->assertOk()
            ->assertSee('Sara Account Manager')
            ->assertSee('nile.example');

        $this->get(route('dashboard.developers.index', ['q' => 'sara']))
            ->assertOk()
            ->assertSee('Nile Dev');
    }

    public function test_invalid_contact_fields_are_rejected(): void
    {
        $this->actingAsStaff();

        $this->post(route('dashboard.developers.store'), [
            'slug' => 'bad-dev',
            'name_ar' => 'س',
            'name_en' => 'Bad',
            'contact_phone' => 'abc',
            'contact_email' => 'not-an-email',
            'website' => 'javascript:alert(1)',
        ])->assertSessionHasErrors(['contact_phone', 'contact_email', 'website']);
    }

    public function test_only_catalog_managers_can_write_contact_fields(): void
    {
        $this->actingAsStaff(StaffRole::Sales);

        $this->post(route('dashboard.developers.store'), [
            'slug' => 'blocked',
            'name_ar' => 'س',
            'name_en' => 'Blocked',
            'contact_name' => 'x',
        ])->assertForbidden();
    }

    public function test_developer_contact_data_never_reaches_the_public_catalog_api(): void
    {
        Developer::factory()->create([
            'slug' => 'private-dev',
            'contact_name' => 'Secret Person',
            'contact_phone' => '+20 100 999 8888',
            'contact_email' => 'secret@dev.example',
            'internal_notes' => 'Do not leak',
        ]);

        $response = $this->getJson('/api/v1/developers')->assertOk();

        $body = $response->getContent();
        foreach (['Secret Person', '+20 100 999 8888', 'secret@dev.example', 'Do not leak'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $body);
        }
    }
}
