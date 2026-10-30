<?php

namespace Tests\Integration;

use App\Core\Audit\Models\AuditLog;
use App\Core\Enums\StaffRole;
use App\Modules\Staff\Models\Role;
use App\Modules\Staff\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardProfileTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_profile(): void
    {
        $this->get('/dashboard/profile')->assertRedirect('/login');
    }

    public function test_header_profile_opens_the_signed_in_staff_account(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Sales, [
            'name' => 'Sara Saleh',
            'email' => 'sara.saleh@example.com',
            'branch' => 'Cairo',
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee(route('dashboard.profile.show', absolute: false), false)
            ->assertSee(__('aqdar.profile.open'))
            ->assertSee('Sara Saleh')
            ->assertSee($staff->roleName());

        $this->get('/dashboard/profile')
            ->assertOk()
            ->assertSee('data-screen="PROFILE"', false)
            ->assertSee('Sara Saleh')
            ->assertSee('sara.saleh@example.com')
            ->assertSee('Cairo')
            ->assertSee($staff->roleName())
            ->assertSee(__('aqdar.profile.title'))
            ->assertSee(__('aqdar.profile.change_photo'))
            ->assertSee('data-profile-photo', false)
            ->assertSee('class="upload profile-upload"', false)
            ->assertDontSee('Max 1')
            ->assertDontSee('ميجابايت')
            ->assertDontSee('name="role_id"', false);
    }

    public function test_sales_can_open_own_profile_but_not_the_staff_desk(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Sales);

        $this->get('/dashboard/profile')->assertOk();
        $this->get('/dashboard/staff')->assertForbidden();
        $this->get('/dashboard/staff/'.$staff->id.'/edit')->assertForbidden();
    }

    public function test_super_admin_can_set_own_role_from_profile(): void
    {
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin, [
            'name' => 'AQDAR Admin',
            'email' => 'admin@example.com',
        ]);
        $this->makeStaff(StaffRole::SuperAdmin, ['email' => 'second.admin@example.com']);
        $salesRoleId = (int) Role::query()->where('slug', StaffRole::Sales->value)->value('id');
        $superAdminId = (int) $staff->role_id;

        $this->get('/dashboard/profile')
            ->assertOk()
            ->assertSee('name="role_id"', false)
            ->assertSee('Super admin');

        $this->from('/dashboard/profile')
            ->put('/dashboard/profile', [
                'name' => 'AQDAR Admin',
                'email' => 'admin@example.com',
                'role_id' => $salesRoleId,
            ])
            ->assertRedirect(route('dashboard.profile.show'))
            ->assertSessionHas('status', __('aqdar.profile.updated'));

        $this->assertSame($salesRoleId, (int) $staff->fresh()->role_id);
        $this->assertNotSame($superAdminId, (int) $staff->fresh()->role_id);
    }

    public function test_last_super_admin_cannot_demote_own_role_from_profile(): void
    {
        $this->seedStaffRbac();
        $staff = StaffUser::query()->where('email', 'test@example.com')->firstOrFail();
        $this->actingAs($staff);
        $salesRoleId = (int) Role::query()->where('slug', StaffRole::Sales->value)->value('id');

        $this->from('/dashboard/profile')
            ->put('/dashboard/profile', [
                'name' => 'AQDAR Admin',
                'email' => 'admin@example.com',
                'role_id' => $salesRoleId,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertTrue($staff->fresh()->isSuperAdmin());
    }

    public function test_staff_can_update_name_and_email_without_changing_role(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Sales, [
            'name' => 'Sara Saleh',
            'email' => 'sara.saleh@example.com',
        ]);
        $roleId = (int) $staff->role_id;
        $superAdminId = (int) Role::query()->where('slug', StaffRole::SuperAdmin->value)->value('id');

        $this->from('/dashboard/profile')
            ->put('/dashboard/profile', [
                'name' => 'Sara Naguib',
                'email' => 'sara.naguib@example.com',
                'role_id' => $superAdminId,
            ])
            ->assertRedirect(route('dashboard.profile.show'))
            ->assertSessionHas('status', __('aqdar.profile.updated'));

        $staff->refresh();

        $this->assertSame('Sara Naguib', $staff->name);
        $this->assertSame('sara.naguib@example.com', $staff->email);
        $this->assertSame($roleId, (int) $staff->role_id);
        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'staff.profile_updated')->where('actor_id', $staff->id)->count(),
        );
    }

    public function test_profile_email_must_stay_unique(): void
    {
        $this->makeStaff(StaffRole::Operations, ['email' => 'ops.taken@example.com']);
        $this->actingAsStaff(StaffRole::Sales, [
            'name' => 'Sara Saleh',
            'email' => 'sara.saleh@example.com',
        ]);

        $this->from('/dashboard/profile')
            ->put('/dashboard/profile', [
                'name' => 'Sara Saleh',
                'email' => 'ops.taken@example.com',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_password_change_keeps_the_current_session_signed_in(): void
    {
        $staff = $this->actingAsStaff(StaffRole::Sales, [
            'name' => 'Sara Saleh',
            'email' => 'sara.saleh@example.com',
        ]);
        $version = (int) $staff->session_version;

        $this->get('/dashboard/profile')->assertOk();

        $this->from('/dashboard/profile')
            ->put('/dashboard/profile', [
                'name' => 'Sara Saleh',
                'email' => 'sara.saleh@example.com',
                'password' => 'Newpass12',
                'password_confirmation' => 'Newpass12',
            ])
            ->assertRedirect(route('dashboard.profile.show'));

        $staff->refresh();
        $this->assertTrue(Hash::check('Newpass12', $staff->password));
        $this->assertSame($version + 1, (int) $staff->session_version);
        $this->get('/dashboard/profile')->assertOk();
    }

    public function test_profile_page_is_bilingual_without_raw_translation_keys(): void
    {
        $this->actingAsStaff(StaffRole::Sales, [
            'name' => 'Sara Saleh',
            'email' => 'sara.saleh@example.com',
        ]);

        foreach (['ar', 'en'] as $locale) {
            $response = $this->withSession(['locale' => $locale])->get('/dashboard/profile');
            $html = $response->getContent();

            $response->assertOk()
                ->assertSee(__('aqdar.profile.title', locale: $locale))
                ->assertSee(__('aqdar.profile.open', locale: $locale))
                ->assertSee(__('aqdar.profile.photo', locale: $locale))
                ->assertSee(__('aqdar.profile.change_photo', locale: $locale));
            $this->assertSame([], $this->untranslatedKeys($html), "Untranslated key on profile ({$locale})");
        }
    }

    public function test_staff_can_upload_and_remove_a_profile_photo(): void
    {
        Storage::fake('public_media');
        $staff = $this->actingAsStaff(StaffRole::Sales, [
            'name' => 'Sara Saleh',
            'email' => 'sara.saleh@example.com',
        ]);

        $this->from('/dashboard/profile')
            ->put('/dashboard/profile', [
                'name' => 'Sara Saleh',
                'email' => 'sara.saleh@example.com',
                'photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
            ])
            ->assertRedirect(route('dashboard.profile.show'));

        $staff->refresh();
        $this->assertNotNull($staff->avatar_path);
        Storage::disk('public_media')->assertExists($staff->avatar_path);

        $this->get('/dashboard/profile')
            ->assertOk()
            ->assertSee($staff->avatarUrl(), false)
            ->assertSee(__('aqdar.profile.remove_photo'));
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee($staff->avatarUrl(), false);

        $oldPath = $staff->avatar_path;

        $this->from('/dashboard/profile')
            ->put('/dashboard/profile', [
                'name' => 'Sara Saleh',
                'email' => 'sara.saleh@example.com',
                'remove_photo' => '1',
            ])
            ->assertRedirect(route('dashboard.profile.show'));

        $staff->refresh();
        $this->assertNull($staff->avatar_path);
        Storage::disk('public_media')->assertMissing($oldPath);
    }

    public function test_profile_photo_must_be_an_image(): void
    {
        Storage::fake('public_media');
        $this->actingAsStaff(StaffRole::Sales, [
            'name' => 'Sara Saleh',
            'email' => 'sara.saleh@example.com',
        ]);

        $this->from('/dashboard/profile')
            ->put('/dashboard/profile', [
                'name' => 'Sara Saleh',
                'email' => 'sara.saleh@example.com',
                'photo' => UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');
    }

    public function test_profile_photo_has_no_application_size_limit(): void
    {
        Storage::fake('public_media');
        $this->actingAsStaff(StaffRole::Sales, [
            'name' => 'Sara Saleh',
            'email' => 'sara.saleh@example.com',
        ]);

        $this->from('/dashboard/profile')
            ->put('/dashboard/profile', [
                'name' => 'Sara Saleh',
                'email' => 'sara.saleh@example.com',
                'photo' => UploadedFile::fake()->image('avatar.jpg', 200, 200)->size(20480),
            ])
            ->assertRedirect(route('dashboard.profile.show'))
            ->assertSessionHasNoErrors();
    }

    /** @return list<string> */
    private function untranslatedKeys(string $html): array
    {
        preg_match_all('/\b(?:aqdar|structer)\.[a-z0-9_.]+/', $html, $matches);

        return array_values(array_unique(array_filter(
            $matches[0],
            fn (string $key): bool => str_contains($key, '_') || substr_count($key, '.') >= 2,
        )));
    }
}
