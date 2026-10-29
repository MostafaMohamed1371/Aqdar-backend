<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardUiContractTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_arabic_and_english_translation_files_have_the_same_keys(): void
    {
        foreach (['aqdar', 'structer'] as $file) {
            $arabic = $this->flattenLang(lang_path("ar/{$file}.php"));
            $english = $this->flattenLang(lang_path("en/{$file}.php"));

            $this->assertSame(
                [],
                array_values(array_diff(array_keys($arabic), array_keys($english))),
                "{$file}.php keys missing in English",
            );
            $this->assertSame(
                [],
                array_values(array_diff(array_keys($english), array_keys($arabic))),
                "{$file}.php keys missing in Arabic",
            );
        }

        foreach (['group', 'menu'] as $section) {
            foreach (array_keys(__("aqdar.{$section}", locale: 'en')) as $key) {
                $arabic = __("aqdar.{$section}.{$key}", locale: 'ar');
                $english = __("aqdar.{$section}.{$key}", locale: 'en');

                $this->assertMatchesRegularExpression('/\p{Arabic}/u', $arabic, "Arabic missing for aqdar.{$section}.{$key}");
                $this->assertMatchesRegularExpression('/[A-Za-z]/', $english, "English missing for aqdar.{$section}.{$key}");
                $this->assertNotSame($arabic, $english, "Arabic and English are identical for aqdar.{$section}.{$key}");
            }
        }
    }

    public function test_login_chrome_switches_arabic_and_english(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('data-screen="staff-login"', false)
            ->assertSee(__('structer.sign_in_to_dashboard', locale: 'ar'))
            ->assertSee(__('structer.email', locale: 'ar'))
            ->assertSee(__('structer.password', locale: 'ar'))
            ->assertSee(__('structer.sign_in', locale: 'ar'))
            ->assertSee('العربية')
            ->assertDontSee(__('structer.sign_in_to_dashboard', locale: 'en'));

        $this->withSession(['locale' => 'en'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee(__('structer.sign_in_to_dashboard', locale: 'en'))
            ->assertSee(__('structer.email', locale: 'en'))
            ->assertSee(__('structer.password', locale: 'en'))
            ->assertSee(__('structer.sign_in', locale: 'en'))
            ->assertSee('English')
            ->assertDontSee(__('structer.sign_in_to_dashboard', locale: 'ar'));
    }

    public function test_every_desk_screen_matches_structure_style_and_bilingual_words(): void
    {
        $this->actingAsStaff();

        foreach (['ar', 'en'] as $locale) {
            foreach ($this->screens() as $screen) {
                $response = $this->withSession(['locale' => $locale])->get($screen['path']);
                $html = $response->getContent();
                $dir = $locale === 'ar' ? 'rtl' : 'ltr';
                $label = "{$screen['id']} ({$locale})";

                $response->assertOk();
                $this->assertStringContainsString("lang=\"{$locale}\"", $html, $label);
                $this->assertStringContainsString("dir=\"{$dir}\"", $html, $label);
                $this->assertStringContainsString("desk is-{$dir}", $html, $label);
                $this->assertStringContainsString('class="aqdar-desk"', $html, $label);
                $this->assertStringContainsString('class="dside"', $html, $label);
                $this->assertStringContainsString('class="dmain"', $html, $label);
                $this->assertStringContainsString('class="dhead"', $html, $label);
                $this->assertStringContainsString('class="dbody"', $html, $label);
                $this->assertStringContainsString('data-sidebar', $html, $label);
                $this->assertStringContainsString("data-screen=\"{$screen['id']}\"", $html, $label);
                $this->assertStringContainsString('name="color-scheme" content="light"', $html, $label);
                $this->assertStringContainsString('Cairo', $html, $label);

                $response->assertSee(__($screen['title'], locale: 'ar'))
                    ->assertSee(__($screen['title'], locale: 'en'))
                    ->assertSee(__($screen['menu'], locale: 'ar'))
                    ->assertSee(__($screen['menu'], locale: 'en'));

                foreach (array_keys(__('aqdar.menu', locale: 'en')) as $item) {
                    $response->assertSee(__("aqdar.menu.{$item}", locale: 'ar'))
                        ->assertSee(__("aqdar.menu.{$item}", locale: 'en'));
                }

                foreach (array_keys(__('aqdar.group', locale: 'en')) as $group) {
                    $response->assertSee(__("aqdar.group.{$group}", locale: 'ar'))
                        ->assertSee(__("aqdar.group.{$group}", locale: 'en'));
                }

                $response->assertSee(__('aqdar.search_placeholder', locale: $locale))
                    ->assertSee(__('structer.sign_out', locale: $locale))
                    ->assertSee(__('structer.open_menu', locale: $locale))
                    ->assertSee(__('structer.close_menu', locale: $locale))
                    ->assertSee('العربية')
                    ->assertSee('English');
                $this->assertSame([], $this->untranslatedKeys($html), "Untranslated key on {$label}");
            }
        }
    }

    public function test_adm_01_kpi_words_follow_active_locale(): void
    {
        $this->actingAsStaff();

        $arabic = $this->withSession(['locale' => 'ar'])->get('/dashboard');
        $arabic->assertOk()
            ->assertSee(__('aqdar.total_listings', locale: 'ar'))
            ->assertSee(__('aqdar.listings_in_review', locale: 'ar'))
            ->assertSee(__('aqdar.needs.title', locale: 'ar'))
            ->assertSee(__('aqdar.funnel.title', locale: 'ar'))
            ->assertSee(__('aqdar.presets.year', locale: 'ar'))
            ->assertSee(__('aqdar.presets.days_30', locale: 'ar'))
            ->assertDontSee(__('aqdar.total_listings', locale: 'en'));

        $english = $this->withSession(['locale' => 'en'])->get('/dashboard');
        $english->assertOk()
            ->assertSee(__('aqdar.total_listings', locale: 'en'))
            ->assertSee(__('aqdar.listings_in_review', locale: 'en'))
            ->assertSee(__('aqdar.needs.title', locale: 'en'))
            ->assertSee(__('aqdar.funnel.title', locale: 'en'))
            ->assertSee(__('aqdar.presets.year', locale: 'en'))
            ->assertSee(__('aqdar.presets.days_30', locale: 'en'))
            ->assertDontSee(__('aqdar.total_listings', locale: 'ar'));
    }

    /**
     * @return list<array{id: string, path: string, title: string, menu: string}>
     */
    private function screens(): array
    {
        return [
            ['id' => 'ADM-01', 'path' => '/dashboard', 'title' => 'aqdar.executive_dashboard', 'menu' => 'aqdar.menu.adm_01'],
            ['id' => 'ADM-02', 'path' => '/dashboard/listings', 'title' => 'aqdar.menu.adm_02', 'menu' => 'aqdar.menu.adm_02'],
            ['id' => 'ADM-03', 'path' => '/dashboard/documents', 'title' => 'aqdar.menu.adm_03', 'menu' => 'aqdar.menu.adm_03'],
            ['id' => 'ADM-04', 'path' => '/dashboard/users', 'title' => 'aqdar.users.title', 'menu' => 'aqdar.menu.adm_04'],
            ['id' => 'ADM-05', 'path' => '/dashboard/developers', 'title' => 'aqdar.menu.adm_05', 'menu' => 'aqdar.menu.adm_05'],
            ['id' => 'ADM-06', 'path' => '/dashboard/staff', 'title' => 'aqdar.staff.title', 'menu' => 'aqdar.menu.adm_06'],
            ['id' => 'ADM-07', 'path' => '/dashboard/leads', 'title' => 'aqdar.menu.adm_07', 'menu' => 'aqdar.menu.adm_07'],
            ['id' => 'ADM-08', 'path' => '/dashboard/requests', 'title' => 'aqdar.menu.adm_08', 'menu' => 'aqdar.menu.adm_08'],
            ['id' => 'ADM-09', 'path' => '/dashboard/cases', 'title' => 'aqdar.menu.adm_09', 'menu' => 'aqdar.menu.adm_09'],
            ['id' => 'ADM-10', 'path' => '/dashboard/commissions', 'title' => 'aqdar.menu.adm_10', 'menu' => 'aqdar.menu.adm_10'],
            ['id' => 'ADM-11', 'path' => '/dashboard/content', 'title' => 'aqdar.menu.adm_11', 'menu' => 'aqdar.menu.adm_11'],
            ['id' => 'ADM-12', 'path' => '/dashboard/reports', 'title' => 'aqdar.menu.adm_12', 'menu' => 'aqdar.menu.adm_12'],
            ['id' => 'ADM-13', 'path' => '/dashboard/settings', 'title' => 'aqdar.menu.adm_13', 'menu' => 'aqdar.menu.adm_13'],
            ['id' => 'ADM-14', 'path' => '/dashboard/audit', 'title' => 'aqdar.menu.adm_14', 'menu' => 'aqdar.menu.adm_14'],
            ['id' => 'ADM-15', 'path' => '/dashboard/categories', 'title' => 'aqdar.menu.adm_15', 'menu' => 'aqdar.menu.adm_15'],
        ];
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

    /** @return array<string, string> */
    private function flattenLang(string $path): array
    {
        $values = include $path;

        $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
            $out = [];

            foreach ($items as $key => $value) {
                $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;

                if (is_array($value)) {
                    $out += $flatten($value, $full);

                    continue;
                }

                $out[$full] = (string) $value;
            }

            return $out;
        };

        return $flatten(is_array($values) ? $values : []);
    }
}
