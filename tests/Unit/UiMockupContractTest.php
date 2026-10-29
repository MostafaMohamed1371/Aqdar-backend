<?php

namespace Tests\Unit;

use Tests\TestCase;

class UiMockupContractTest extends TestCase
{
    public function test_contract_lists_all_mobile_and_dashboard_screens(): void
    {
        $contract = $this->contract();

        $this->assertCount(41, $contract['mobile']['ids']);
        $this->assertCount(15, $contract['dashboard']['ids']);
        $this->assertSame('APP-01', $contract['mobile']['ids'][0]);
        $this->assertSame('APP-41', $contract['mobile']['ids'][40]);
        $this->assertSame('ADM-01', $contract['dashboard']['ids'][0]);
        $this->assertSame('ADM-15', $contract['dashboard']['ids'][14]);
        $this->assertSame('ui.css', $contract['theme']['dashboard_css']);
        $this->assertSame('styles.css', $contract['theme']['hub_css']);
        $this->assertSame(
            ['APP-01', 'APP-02', 'APP-03', 'APP-21', 'APP-38'],
            $contract['phases']['1']['mobile'],
        );
        $this->assertSame(
            ['ADM-01', 'ADM-04', 'ADM-06'],
            $contract['phases']['1']['dashboard'],
        );
        $this->assertSame(
            ['APP-04', 'APP-05', 'APP-13', 'APP-22', 'APP-34'],
            $contract['phases']['2']['mobile'],
        );
        $this->assertSame(
            ['ADM-05', 'ADM-11', 'ADM-15'],
            $contract['phases']['2']['dashboard'],
        );
        $this->assertSame(
            ['APP-24', 'APP-25', 'APP-30', 'APP-36'],
            $contract['phases']['4']['mobile'],
        );
        $this->assertSame(
            ['ADM-02', 'ADM-03'],
            $contract['phases']['4']['dashboard'],
        );
        $this->assertSame(
            ['APP-15', 'APP-16', 'APP-18', 'APP-33', 'APP-41'],
            $contract['phases']['5']['mobile'],
        );
        $this->assertSame(
            ['ADM-07'],
            $contract['phases']['5']['dashboard'],
        );
        $this->assertSame(
            ['APP-17', 'APP-19', 'APP-20'],
            $contract['phases']['6']['mobile'],
        );
        $this->assertSame(
            ['ADM-08', 'ADM-09'],
            $contract['phases']['6']['dashboard'],
        );
        $this->assertSame(
            ['APP-32'],
            $contract['phases']['7']['mobile'],
        );
        $this->assertSame(
            ['ADM-10'],
            $contract['phases']['7']['dashboard'],
        );
        $this->assertSame(
            ['APP-14', 'APP-35', 'APP-37'],
            $contract['phases']['8']['mobile'],
        );
        $this->assertSame(
            ['ADM-11'],
            $contract['phases']['8']['dashboard'],
        );
        $this->assertSame(
            [],
            $contract['phases']['9']['mobile'],
        );
        $this->assertSame(
            ['ADM-01', 'ADM-12', 'ADM-13', 'ADM-14'],
            $contract['phases']['9']['dashboard'],
        );
    }

    public function test_mockup_html_contains_every_contract_screen_when_path_is_set(): void
    {
        $root = $this->mockupRoot();

        if ($root === null) {
            $this->markTestSkipped(
                'Set AQDAR_UI_MOCKUP_PATH to the aqdar-ui-v2 folder to match E2E against flutter.html and dashboard.html.',
            );
        }

        $contract = $this->contract();
        $flutter = file_get_contents($root.'/'.$contract['mobile']['file']);
        $dashboard = file_get_contents($root.'/'.$contract['dashboard']['file']);
        $uiCss = file_get_contents($root.'/'.$contract['theme']['dashboard_css']);

        $this->assertNotFalse($flutter);
        $this->assertNotFalse($dashboard);
        $this->assertNotFalse($uiCss);
        $this->assertStringContainsString('--teal', $uiCss);
        $this->assertStringContainsString('Cairo', $uiCss);

        foreach ($contract['mobile']['ids'] as $id) {
            $this->assertStringContainsString('id:"'.$id.'"', $flutter, "flutter.html missing {$id}");
        }

        foreach ($contract['dashboard']['ids'] as $id) {
            $this->assertStringContainsString('id:"'.$id.'"', $dashboard, "dashboard.html missing {$id}");
        }
    }

    /** @return array<string, mixed> */
    private function contract(): array
    {
        $path = base_path('tests/Fixtures/ui-screen-contract.json');
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function mockupRoot(): ?string
    {
        $configured = env('AQDAR_UI_MOCKUP_PATH');

        if (is_string($configured) && $configured !== '' && is_dir($configured)) {
            return $configured;
        }

        $fallback = '/Users/mostafa.osmananmat.sa/anaylsis project/aqdar-ui-v2';

        return is_dir($fallback) ? $fallback : null;
    }
}
