<?php

namespace Tests\Integration;

use App\Core\Audit\Models\AccessLog;
use App\Core\Audit\Models\AuditLog;
use App\Core\Enums\LeadStatus;
use App\Core\Enums\ListingStatus;
use App\Core\Enums\MarketSegment;
use App\Core\Enums\StaffRole;
use App\Modules\Catalog\Models\Governorate;
use App\Modules\Crm\Models\BuyerRequest;
use App\Modules\Crm\Models\Lead;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Dashboard\Services\ReportService;
use App\Modules\Finance\Models\Commission;
use App\Modules\Listing\Models\Listing;
use App\Modules\Setting\Services\SettingService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DashboardPhase9Test extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    public function test_adm_01_shows_kpis_funnel_needs_action_and_date_filter(): void
    {
        Listing::factory()->count(2)->create(['status' => ListingStatus::InReview]);
        $listing = Listing::factory()->published()->create();
        Lead::factory()->create([
            'status' => LeadStatus::New,
            'listing_id' => $listing->id,
            'seller_id' => $listing->user_id,
        ]);
        Lead::factory()->create([
            'status' => LeadStatus::Converted,
            'listing_id' => $listing->id,
            'seller_id' => $listing->user_id,
        ]);

        $overview = app(DashboardService::class)->overview(now()->startOfYear()->toDateString(), now()->toDateString());
        $this->assertSame(3, $overview['kpis']['total_listings']);
        $this->assertSame(2, $overview['kpis']['listings_in_review']);
        $this->assertSame(2, $overview['needs_action']['listings_in_review']);
        $this->assertNotEmpty($overview['funnel']);
        $this->assertSame($overview['funnel'][0]['count'], Lead::query()->count());

        $this->actingAsStaff();
        $this->get('/dashboard?from='.now()->startOfYear()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertSee('ADM-01', false)
            ->assertSee('name="from"', false)
            ->assertSee('name="to"', false)
            ->assertSee(__('aqdar.needs.title'))
            ->assertSee(__('aqdar.funnel.title'))
            ->assertSee('data-chart="listing-performance"', false)
            ->assertSee('data-chart="listing-status"', false)
            ->assertSee('data-chart="egypt-map"', false)
            ->assertSee(__('aqdar.presets.days_30'))
            ->assertSee(__('aqdar.presets.month'))
            ->assertSee('data-ops-health', false)
            ->assertSee(__('aqdar.inquiries_in_range'))
            ->assertSee('assigned_to=unassigned', false)
            ->assertSee((string) $overview['kpis']['total_listings'])
            ->assertSee('data-sidebar-backdrop', false)
            ->assertSee('data-sidebar-close', false)
            ->assertSee('cols3-dash', false)
            ->assertDontSee('grid-template-columns:1.5fr', false)
            ->assertSee('name="q"', false)
            ->assertSee('action="'.route('dashboard.listings.index').'"', false);
    }

    public function test_header_search_on_listings_uses_listing_query(): void
    {
        $this->actingAsStaff();
        $hit = Listing::factory()->published()->create();
        $miss = Listing::factory()->published()->create();

        $this->get('/dashboard/listings?q='.$hit->public_code)
            ->assertOk()
            ->assertSee($hit->public_code)
            ->assertDontSee($miss->public_code)
            ->assertSee('action="'.route('dashboard.listings.index').'"', false);
    }

    public function test_uat_17_csv_omits_phones_by_default_and_full_export_is_logged(): void
    {
        $this->actingAsStaff();
        $listing = Listing::factory()->published()->create();
        $listing->owner->forceFill(['phone' => '+201011112222'])->save();
        User::factory()->create(['phone' => '+201033334444']);

        $default = $this->get('/dashboard/reports/export?type=listings&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString());
        $default->assertOk();
        $this->assertStringNotContainsString('+201011112222', $default->streamedContent());
        $this->assertStringNotContainsString('owner_phone', $default->streamedContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);

        $full = $this->get('/dashboard/reports/export?type=listings&include_phones=1&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString());
        $full->assertOk();
        $this->assertStringContainsString('owner_phone', $full->streamedContent());
        $this->assertStringContainsString('+201011112222', $full->streamedContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported_with_phones']);

        $users = $this->get('/dashboard/reports/export?type=users&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString());
        $users->assertOk();
        $body = $users->streamedContent();
        $this->assertStringContainsString('USR-', $body);
        $this->assertStringNotContainsString('+201033334444', $body);
        $this->assertStringNotContainsString('email', explode("\n", $body)[0]);
        $this->assertStringNotContainsString($listing->owner->email, $body);
    }

    public function test_adm_12_pdf_matches_csv_privacy(): void
    {
        $listing = Listing::factory()->published()->create();
        $listing->owner->forceFill(['phone' => '+201044445555'])->save();

        $this->actingAsStaff();
        $pdf = $this->get('/dashboard/reports/export?type=listings&format=pdf&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString());
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringNotContainsString('+201044445555', $pdf->getContent());
        $this->assertStringContainsString((string) $listing->public_code, $pdf->getContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
    }

    public function test_reviewer_cannot_open_reports_and_finance_cannot_export_phones(): void
    {
        Listing::factory()->published()->create();

        $this->actingAsStaff(StaffRole::Reviewer);
        $this->get('/dashboard/reports')->assertForbidden();
        $this->get('/dashboard/reports/export?type=listings')->assertForbidden();

        $this->actingAsStaff(StaffRole::Finance);
        $this->get('/dashboard/reports')
            ->assertOk()
            ->assertSee('ADM-12', false)
            ->assertSee(__('aqdar.reports.csv'))
            ->assertSee(__('aqdar.reports.pdf'));
        $this->get('/dashboard/reports/export?type=listings&include_phones=1')->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'report.exported_with_phones']);
    }

    public function test_adm_13_saves_sales_contact_and_assignment_rule(): void
    {
        $this->actingAsStaff();

        $this->get('/dashboard/settings')
            ->assertOk()
            ->assertSee('ADM-13', false)
            ->assertSee(__('aqdar.settings.sales_phone'))
            ->assertSee(__('aqdar.settings.sales_name_en'))
            ->assertSee('AQDAR Sales Team')
            ->assertSee('Daily 10am – 10pm')
            ->assertSee(__('aqdar.settings.chat_forbidden'))
            ->assertSee(__('aqdar.settings.tab_general'))
            ->assertSee(__('aqdar.settings.tab_security'))
            ->assertSee(__('aqdar.settings.not_enabled'))
            ->assertDontSee('PDF', false);

        $this->put('/dashboard/settings', $this->settingsPayload([
            'sales_phone' => '16890',
            'lead_assignment_rule' => 'round_robin',
            'platform_name' => 'AQDAR EG',
        ]))->assertRedirect(route('dashboard.settings.index'));

        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.sales_contact.phone', '16890')
            ->assertJsonPath('data.platform_name', 'AQDAR EG')
            ->assertJsonPath('data.maintenance', false);

        $this->assertDatabaseHas('settings', ['key' => 'lead_assignment_rule', 'value' => 'round_robin']);
        $this->assertDatabaseHas('settings', ['key' => 'sales_name_en', 'value' => 'AQDAR Sales Team']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);

        $this->withSession(['locale' => 'en'])->withHeaders(['Accept-Language' => 'en'])->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.sales_contact.name', 'AQDAR Sales Team')
            ->assertJsonPath('data.sales_contact.hours', 'Daily 10am – 10pm');

        $this->withSession(['locale' => 'ar'])->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.sales_contact.name', 'فريق مبيعات عَقدار')
            ->assertJsonPath('data.sales_contact.hours', 'يومياً 10ص – 10م');
    }

    public function test_reviewer_cannot_open_settings_or_audit(): void
    {
        $this->actingAsStaff(StaffRole::Reviewer);
        $this->get('/dashboard/settings')->assertForbidden();
        $this->get('/dashboard/audit')->assertForbidden();
        $this->get('/dashboard/reports')->assertForbidden();
    }

    public function test_adm_14_is_searchable_and_has_no_delete(): void
    {
        $staff = $this->actingAsStaff();
        AuditLog::query()->create([
            'action' => 'listing.approved',
            'actor_type' => $staff->getMorphClass(),
            'actor_id' => $staff->id,
            'ip_address' => '10.0.0.8',
            'created_at' => now(),
        ]);

        $this->get('/dashboard/audit?q=listing.approved')
            ->assertOk()
            ->assertSee('ADM-14', false)
            ->assertSee('listing.approved', false)
            ->assertSee(__('aqdar.audit.immutable'))
            ->assertSee(__('aqdar.audit.actor_all'))
            ->assertSee(__('aqdar.audit.kind_all'))
            ->assertDontSee('dashboard.audit.destroy', false);

        $this->delete('/dashboard/audit/1')->assertNotFound();
    }

    public function test_maintenance_mode_returns_503_on_marketplace_and_leaves_desk_open(): void
    {
        $this->actingAsStaff();
        $this->put('/dashboard/settings', $this->settingsPayload([
            'maintenance_mode' => '1',
        ]))->assertRedirect(route('dashboard.settings.index'));

        $this->getJson('/api/v1/listings')
            ->assertStatus(503)
            ->assertJsonPath('message', __('aqdar.http.maintenance'));

        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.maintenance', true);

        $this->get('/dashboard')->assertOk();
        $this->get('/up')->assertOk();
    }

    public function test_round_robin_assigns_sales_only(): void
    {
        app(SettingService::class)->put('lead_assignment_rule', 'round_robin', 'crm');
        $first = $this->makeStaff(StaffRole::Sales, ['email' => 'sales-a@aqdar.test']);
        $second = $this->makeStaff(StaffRole::Sales, ['email' => 'sales-b@aqdar.test']);
        $listing = Listing::factory()->published()->create();

        $ids = [];
        foreach (range(1, 3) as $i) {
            $buyer = User::factory()->create(['is_buyer' => true, 'email' => "buyer{$i}@aqdar.test"]);
            Sanctum::actingAs($buyer);
            $this->postJson('/api/v1/inquiries', [
                'listing_id' => $listing->id,
                'contact_method' => 'phone',
                'consent' => true,
            ])->assertCreated();
            $ids[] = Lead::query()->latest('id')->value('assigned_to');
        }

        $this->assertSame([$first->id, $second->id, $first->id], $ids);
        $this->assertSame(0, Lead::query()->whereNotNull('assigned_to')->whereNotIn('assigned_to', [$first->id, $second->id])->count());
    }

    public function test_uat_20_health_config_and_dashboard_login(): void
    {
        $this->get('/up')->assertOk();
        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.currency', 'EGP')
            ->assertJsonStructure(['data' => [
                'platform_name',
                'maintenance',
                'sales_contact' => ['name', 'phone', 'hours'],
                'consent' => ['version', 'terms_url', 'privacy_url'],
                'otp' => ['resend_after', 'length'],
            ]]);

        $this->makeStaff(StaffRole::SuperAdmin, [
            'email' => 'handover@aqdar.test',
            'password' => 'password123',
        ]);
        $this->post('/dashboard/login', [
            'email' => 'handover@aqdar.test',
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk()->assertSee('ADM-01', false);
    }

    public function test_ops_commands_run(): void
    {
        AuditLog::query()->create([
            'action' => 'old.event',
            'created_at' => now()->subMonths(30),
        ]);
        app(SettingService::class)->put('audit_retention_months', '12', 'platform');

        $this->artisan('aqdar:prune-audit')->assertSuccessful();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'old.event']);

        $this->artisan('aqdar:ops-health')->assertSuccessful();
        $this->artisan('aqdar:backup-check')->assertSuccessful();
        $this->artisan('aqdar:backup')->assertSuccessful();
        $this->artisan('aqdar:openapi', ['--path' => 'storage/framework/testing/openapi-test.yaml'])->assertSuccessful();
        $yaml = file_get_contents(storage_path('framework/testing/openapi-test.yaml'));
        $this->assertIsString($yaml);
        $this->assertStringContainsString('openapi: 3.0.3', $yaml);
        $this->assertStringContainsString('/listings', $yaml);
        $this->assertStringContainsString('throttle:search', $yaml);
    }

    public function test_uat_01_one_identity_is_buyer_and_seller(): void
    {
        $this->postJson('/api/v1/auth/otp/send', $this->otpSendBody('01012345678'));
        $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '01012345678',
            'code' => '123456',
            ...$this->otpConsent(),
        ])->assertOk()
            ->assertJsonPath('data.user.is_buyer', true);

        $user = User::query()->firstOrFail();
        Sanctum::actingAs($user);
        $this->patchJson('/api/v1/me', ['is_seller' => true])
            ->assertOk()
            ->assertJsonPath('data.is_seller', true)
            ->assertJsonPath('data.is_buyer', true);
        $this->assertSame(1, User::query()->count());
    }

    public function test_adm_01_date_filter_excludes_leads_outside_range(): void
    {
        $listing = Listing::factory()->published()->create();
        Lead::factory()->create([
            'listing_id' => $listing->id,
            'seller_id' => $listing->user_id,
            'created_at' => now()->subYear(),
            'updated_at' => now()->subYear(),
        ]);
        Lead::factory()->create([
            'listing_id' => $listing->id,
            'seller_id' => $listing->user_id,
        ]);

        $overview = app(DashboardService::class)->overview(
            now()->startOfMonth()->toDateString(),
            now()->toDateString(),
        );

        $this->assertSame(1, $overview['funnel'][0]['count']);
        $this->assertSame(2, Lead::query()->count());
    }

    public function test_unassigned_leads_filter_and_needs_action_link(): void
    {
        $ops = $this->actingAsStaff(StaffRole::Operations);
        Lead::factory()->create([
            'assigned_to' => $ops->id,
            'public_code' => 'LD-2026-AS01',
        ]);
        Lead::factory()->create([
            'assigned_to' => null,
            'public_code' => 'LD-2026-UN02',
        ]);

        $this->get('/dashboard/leads?assigned_to=unassigned')
            ->assertOk()
            ->assertSee('LD-2026-UN02', false)
            ->assertDontSee('LD-2026-AS01', false);
    }

    public function test_sales_cannot_open_reports_and_operations_cannot_open_settings(): void
    {
        $this->actingAsStaff(StaffRole::Sales);
        $this->get('/dashboard/reports')->assertForbidden();
        $this->get('/dashboard/reports/export?type=listings')->assertForbidden();

        $this->actingAsStaff(StaffRole::Operations);
        $this->get('/dashboard/settings')->assertForbidden();
        $this->get('/dashboard/audit')->assertForbidden();
        $this->get('/dashboard/reports')->assertOk();
    }

    public function test_csv_types_omit_email_and_reports_show_first_response_chart(): void
    {
        $this->actingAsStaff();
        $user = User::factory()->create([
            'email' => 'hidden-mail@example.com',
            'phone' => '+201055556666',
        ]);
        BuyerRequest::factory()->create(['user_id' => $user->id]);
        Commission::factory()->create();
        $created = now()->subHours(4);
        Lead::factory()->create([
            'created_at' => $created,
            'last_contacted_at' => $created->copy()->addHours(2),
        ]);

        $this->get('/dashboard/reports')
            ->assertOk()
            ->assertSee(__('aqdar.reports.kpi.first_response'))
            ->assertSee('data-chart="platform-overview"', false)
            ->assertSee('name="owner"', false);

        $rates = app(ReportService::class)->rates([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]);
        $this->assertEqualsWithDelta(2.0, $rates['first_response_hours'], 0.2);

        foreach (['requests', 'revenue', 'sales', 'users'] as $type) {
            $response = $this->get('/dashboard/reports/export?type='.$type.'&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString());
            $response->assertOk();
            $body = $response->streamedContent();
            $this->assertStringNotContainsString('hidden-mail@example.com', $body);
            $this->assertStringNotContainsString('+201055556666', $body);
        }
    }

    public function test_search_listings_are_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/v1/listings')->assertOk();
        }

        $this->getJson('/api/v1/listings')
            ->assertStatus(429)
            ->assertJsonPath('message', __('aqdar.http.too_many_requests'));
    }

    public function test_adm_14_access_log_and_export(): void
    {
        $staff = $this->actingAsStaff();
        AccessLog::query()->create([
            'action' => 'lead.phone_revealed',
            'actor_type' => $staff->getMorphClass(),
            'actor_id' => $staff->id,
            'ip_address' => '10.0.0.9',
            'created_at' => now(),
        ]);

        $this->get('/dashboard/audit?kind=access&q=lead.phone_revealed')
            ->assertOk()
            ->assertSee('lead.phone_revealed', false);

        $export = $this->get('/dashboard/audit/export?kind=access');
        $export->assertOk();
        $this->assertStringContainsString('lead.phone_revealed', $export->streamedContent());
    }

    public function test_round_robin_assigns_sales_on_custom_request(): void
    {
        app(SettingService::class)->put('lead_assignment_rule', 'round_robin', 'crm');
        $first = $this->makeStaff(StaffRole::Sales, ['email' => 'sales-c@aqdar.test']);
        $this->makeStaff(StaffRole::Sales, ['email' => 'sales-d@aqdar.test']);
        $buyer = User::factory()->create(['is_buyer' => true, 'email' => 'rr-buyer@aqdar.test']);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/buyer-requests', [
            'intent' => 'living',
            'contact_method' => 'phone',
            'consent' => true,
        ])->assertCreated();

        $this->assertSame($first->id, Lead::query()->latest('id')->value('assigned_to'));
    }

    public function test_failed_jobs_count_on_adm_01(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'phase-9-test',
            'failed_at' => now(),
        ]);

        $overview = app(DashboardService::class)->overview();
        $this->assertSame(1, $overview['kpis']['failed_jobs']);

        $this->actingAsStaff();
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee(__('aqdar.ops.failed_jobs'));
    }

    public function test_settings_security_tab_and_last_change(): void
    {
        $staff = $this->actingAsStaff();
        $this->put('/dashboard/settings', $this->settingsPayload([
            'platform_name' => 'AQDAR EG',
        ]))->assertRedirect(route('dashboard.settings.index'));

        $this->get('/dashboard/settings?tab=security')
            ->assertOk()
            ->assertSee(__('aqdar.settings.ip_verify'))
            ->assertSee(__('aqdar.settings.provider_sms'))
            ->assertSee(__('aqdar.settings.not_enabled'));

        $this->get('/dashboard/settings?tab=audit')
            ->assertOk()
            ->assertSee($staff->name)
            ->assertDontSee(__('aqdar.settings.last_change_never'));
    }

    public function test_adm_01_preset_thirty_days_and_kpi_delta(): void
    {
        Listing::factory()->create(['created_at' => now()->subDays(40)]);
        Listing::factory()->count(2)->create();

        $this->actingAsStaff();
        $this->get('/dashboard?preset=30d')
            ->assertOk()
            ->assertSee('name="from"', false)
            ->assertSee(now()->subDays(29)->toDateString(), false);

        $overview = app(DashboardService::class)->overview(null, null, '30d');
        $this->assertNotNull($overview['deltas']['total_listings']);
        $this->assertFalse($overview['deltas']['total_listings']['down']);
        $this->assertSame('30d', $overview['preset']);
        $this->assertSame(now()->subDays(29)->toDateString(), $overview['from']);
    }

    public function test_adm_01_egypt_map_uses_governorate_counts(): void
    {
        $cairo = Governorate::factory()->create(['slug' => 'cairo']);
        Listing::factory()->count(2)->published()->create(['governorate_id' => $cairo->id]);

        $overview = app(DashboardService::class)->overview();
        $egypt = collect($overview['charts']['egypt'])->keyBy('key');
        $this->assertSame(2, $egypt['cairo']['count']);

        $this->actingAsStaff();
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee(__('aqdar.charts.egypt_distribution'))
            ->assertSee(__('aqdar.charts.egypt.cairo'));
    }

    public function test_map_and_listing_counts_are_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/v1/map')->assertOk();
        }

        $this->getJson('/api/v1/map')->assertStatus(429);
        $this->getJson('/api/v1/listings/counts')->assertStatus(429);
    }

    public function test_csv_segment_filter_omits_other_market(): void
    {
        $hit = Listing::factory()->published()->create([
            'segment' => MarketSegment::Distressed,
            'public_code' => 'AQ-DIST',
        ]);
        $miss = Listing::factory()->published()->create([
            'segment' => MarketSegment::NonDistressed,
            'public_code' => 'AQ-NOND',
        ]);

        $this->actingAsStaff();
        $body = $this->get('/dashboard/reports/export?type=listings&segment=distressed&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString((string) $hit->public_code, $body);
        $this->assertStringNotContainsString((string) $miss->public_code, $body);
    }

    public function test_adm_01_formats_commission_kpi_like_the_mockup(): void
    {
        Commission::factory()->create(['amount' => 12450000]);

        $this->actingAsStaff();
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('12.45M', false);
    }

    public function test_backup_copies_private_files_when_path_is_set(): void
    {
        $path = storage_path('framework/testing/backup-'.uniqid());
        File::ensureDirectoryExists(storage_path('app/private/documents'));
        File::put(storage_path('app/private/documents/phase9-sample.txt'), 'private-doc');
        config([
            'aqdar.backup.path' => $path,
            'aqdar.backup.dump' => false,
        ]);

        try {
            $this->artisan('aqdar:backup')->assertSuccessful();
            $this->assertFileExists($path.'/files-'.now()->format('Y-m-d').'/documents/phase9-sample.txt');
        } finally {
            File::delete(storage_path('app/private/documents/phase9-sample.txt'));
            File::deleteDirectory($path);
        }
    }

    public function test_adm_13_saves_default_locale_to_public_config(): void
    {
        $this->actingAsStaff();
        $this->put('/dashboard/settings', $this->settingsPayload([
            'default_locale' => 'en',
        ]))->assertRedirect(route('dashboard.settings.index'));

        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('data.default_locale', 'en');
    }

    public function test_adm_14_all_types_includes_access_rows(): void
    {
        $staff = $this->actingAsStaff();
        AccessLog::query()->create([
            'action' => 'document.opened',
            'actor_type' => $staff->getMorphClass(),
            'actor_id' => $staff->id,
            'ip_address' => '10.0.0.11',
            'created_at' => now(),
        ]);

        $this->get('/dashboard/audit')
            ->assertOk()
            ->assertSee('10.0.0.11', false)
            ->assertSee(__('aqdar.audit.kind_all'));
    }

    /** @param array<string, mixed> $overrides */
    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'platform_name' => 'AQDAR',
            'support_email' => 'support@aqdar.eg',
            'sales_name' => 'فريق مبيعات عَقدار',
            'sales_name_en' => 'AQDAR Sales Team',
            'sales_phone' => '',
            'sales_hours' => 'يومياً 10ص – 10م',
            'sales_hours_en' => 'Daily 10am – 10pm',
            'lead_assignment_rule' => 'manual',
            'maintenance_mode' => '0',
            'feature_smart_notifications' => '0',
            'commission_default_rate' => '2.5',
            'audit_retention_months' => '24',
            'notification_quiet_hours' => '',
            'default_locale' => 'ar',
        ], $overrides);
    }
}
