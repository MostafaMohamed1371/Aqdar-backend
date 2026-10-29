<?php

namespace Tests\Integration;

use App\Core\Enums\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_api_uses_accept_language_header_for_validation_messages(): void
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'ar',
        ])->postJson('/api/v1/auth/login', [
            'email' => 'invalid',
            'password' => '',
        ]);

        $response->assertUnprocessable()
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('errors.email.0', __('structer.validation.email.email', locale: 'ar'));
    }

    public function test_dashboard_can_switch_language(): void
    {
        $this->get('/locale/ar')
            ->assertRedirect()
            ->assertSessionHas('locale', 'ar');

        $this->withSession(['locale' => 'ar'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('سجّل الدخول للوصول إلى لوحة التحكم')
            ->assertSee('العربية');
    }

    public function test_arabic_admin_menu_is_rtl_and_bilingual(): void
    {
        $this->actingAsStaff();

        $this->withSession(['locale' => 'ar'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('desk is-rtl', false)
            ->assertSee('لوحة القيادة التنفيذية')
            ->assertSee('Executive Dashboard')
            ->assertSee('الرئيسية')
            ->assertSee('Home');
    }

    public function test_english_admin_menu_is_ltr_and_bilingual(): void
    {
        $this->actingAsStaff();

        $this->withSession(['locale' => 'en'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('desk is-ltr', false)
            ->assertSee('Executive Dashboard')
            ->assertSee('لوحة القيادة التنفيذية');
    }

    public function test_french_locale_translates_dashboard(): void
    {
        $this->withSession(['locale' => 'fr'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Connectez-vous pour accéder à votre tableau de bord');
    }

    public function test_spanish_locale_translates_dashboard(): void
    {
        $this->withSession(['locale' => 'es'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Inicia sesión para acceder a su panel');
    }

    public function test_german_locale_translates_dashboard(): void
    {
        $this->withSession(['locale' => 'de'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Melden Sie sich an, um auf Ihr Dashboard zuzugreifen');
    }

    public function test_remember_me_sets_remember_cookie_on_login(): void
    {
        $this->makeStaff(StaffRole::SuperAdmin, [
            'email' => 'remember@example.com',
            'password' => 'password123',
        ]);

        $response = $this->post('/dashboard/login', [
            'email' => 'remember@example.com',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertCookie(Auth::guard('web')->getRecallerName());
    }
}
