<?php

namespace Tests\Unit;

use Tests\TestCase;

class DashboardThemeContractTest extends TestCase
{
    public function test_ui_tokens_match_aqdar_desk_colors(): void
    {
        $css = (string) file_get_contents(base_path('resources/css/aqdar-ui.css'));

        $this->assertStringContainsString('--ink: #14283f;', $css);
        $this->assertStringContainsString('--navy: #14283f;', $css);
        $this->assertStringContainsString('--desk: #f7f5f0;', $css);
        $this->assertStringContainsString('--ink-2: #1d3b58;', $css);
        $this->assertStringContainsString('--teal: #087e82;', $css);
        $this->assertStringContainsString('--gold: #c5985f;', $css);
        $this->assertStringContainsString('--cream: #f6f1e8;', $css);
        $this->assertStringContainsString('--cream-2: #fbf8f2;', $css);
        $this->assertStringContainsString('--ok: #12805c;', $css);
        $this->assertStringContainsString('--bad: #c0392b;', $css);
        $this->assertStringContainsString('--warn: #c7811f;', $css);
        $this->assertStringContainsString('font-family: Cairo, Tahoma, sans-serif;', $css);
        $this->assertStringContainsString('font-family: Inter;', $css);
    }

    public function test_desk_shell_classes_and_scroll_structure_exist(): void
    {
        $ui = (string) file_get_contents(base_path('resources/css/aqdar-ui.css'));
        $admin = (string) file_get_contents(base_path('resources/css/aqdar-admin.css'));
        $app = (string) file_get_contents(base_path('resources/css/app.css'));

        foreach (['.desk', '.dside', '.dmain', '.dhead', '.dbody', '.kpi', '.btn', '.card'] as $selector) {
            $this->assertStringContainsString($selector, $ui, "ui.css missing {$selector}");
        }

        $this->assertStringContainsString("@import './aqdar-ui.css';", $app);
        $this->assertStringContainsString("@import './aqdar-admin.css';", $app);
        $this->assertStringContainsString('--font-sans: Cairo, Inter', $app);

        $this->assertStringContainsString('.dmain', $admin);
        $this->assertStringContainsString('min-height: 0', $admin);
        $this->assertStringContainsString('.dbody', $admin);
        $this->assertStringContainsString('overflow: auto', $admin);
        $this->assertStringContainsString('.dside.is-open', $admin);
        $this->assertStringContainsString('input::placeholder', $admin);
        $this->assertStringContainsString('opacity: 1', $admin);
        $this->assertStringContainsString('html.dark option', $admin);
        $this->assertStringContainsString('color-scheme: dark', $admin);
        $this->assertStringContainsString('html.dark .ctabs a em', $admin);
        $this->assertStringContainsString('.dhead a.who', $ui);
        $this->assertStringContainsString('background: var(--cream)', $ui);
    }

    public function test_layout_keeps_light_dark_desk_and_rtl_ltr_shell(): void
    {
        $layout = (string) file_get_contents(base_path('app/Modules/Dashboard/Resources/views/layouts/app.blade.php'));

        $this->assertStringContainsString('name="color-scheme" content="light dark"', $layout);
        $this->assertStringContainsString("dir=\"{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}\"", $layout);
        $this->assertStringContainsString("class=\"desk {{ app()->getLocale() === 'ar' ? 'is-rtl' : 'is-ltr' }}\"", $layout);
        $this->assertStringContainsString('class="aqdar-desk"', $layout);
        $this->assertStringContainsString('class="dside"', (string) file_get_contents(base_path('app/Modules/Dashboard/Resources/views/partials/sidebar.blade.php')));
        $header = (string) file_get_contents(base_path('app/Modules/Dashboard/Resources/views/partials/header.blade.php'));
        $icons = (string) file_get_contents(base_path('app/Modules/Dashboard/Resources/views/partials/icon.blade.php'));
        $this->assertStringContainsString('class="dhead"', $header);
        $this->assertStringContainsString("route('dashboard.profile.show')", $header);
        $this->assertStringContainsString("['name' => 'settings']", $header);
        $this->assertStringContainsString('1.65 1.65', $icons);
        $this->assertStringNotContainsString('M12 2.5v2.5M12 19v2.5', $icons);
        $this->assertStringContainsString('<x-bilingual', (string) file_get_contents(base_path('app/Modules/Dashboard/Resources/views/partials/sidebar.blade.php')));
    }
}
