<?php

namespace Tests\Integration;

use App\Core\Enums\ListingStatus;
use App\Core\Enums\StaffRole;
use App\Modules\Category\Models\Category;
use App\Modules\Listing\Models\Listing;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesStaffUsers;
use Tests\Concerns\UploadsListingDocuments;
use Tests\TestCase;

class ListingPhase13Test extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;
    use UploadsListingDocuments;

    public function test_public_listing_index_favorites_and_share_hide_private_fields(): void
    {
        $reviewer = $this->makeStaff(StaffRole::Reviewer, ['name' => 'Omar Reviewer']);
        $listing = Listing::factory()->published()->create([
            'unit_code' => 'SECRET-UNIT-99',
            'phase' => 'المرحلة 3',
            'parking' => 2,
            'offer_expires_at' => '2026-12-31 18:00:00',
            'assigned_reviewer_id' => $reviewer->id,
            'commission_policy_code' => 'ND-STD-01',
        ]);

        $response = $this->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.phase', 'المرحلة 3')
            ->assertJsonPath('data.parking', 2)
            ->assertJsonMissingPath('data.unit_code')
            ->assertJsonMissingPath('data.assigned_reviewer_id')
            ->assertJsonMissingPath('data.assigned_reviewer')
            ->assertJsonMissingPath('data.commission_policy_code');
        $this->assertStringStartsWith('2026-12-31', (string) $response->json('data.offer_expires_at'));
        $this->assertPublicPayloadHidesSecrets((string) json_encode($response->json()));

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonMissingPath('data.0.unit_code')
            ->assertJsonMissingPath('data.0.assigned_reviewer_id')
            ->assertJsonMissingPath('data.0.commission_policy_code');
        $this->assertPublicPayloadHidesSecrets((string) json_encode($this->getJson('/api/v1/listings')->json()));

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/favorites/'.$listing->id)->assertCreated();
        $favorites = $this->getJson('/api/v1/favorites')->assertOk();
        $this->assertPublicPayloadHidesSecrets((string) json_encode($favorites->json()));
        $favorites->assertJsonMissingPath('data.0.unit_code')
            ->assertJsonMissingPath('data.0.commission_policy_code');

        $this->get('/property/'.$listing->public_code)
            ->assertOk()
            ->assertSee('المرحلة 3', false)
            ->assertSee(__('aqdar.share.parking'), false)
            ->assertSee('2026-12-31', false)
            ->assertDontSee('SECRET-UNIT-99')
            ->assertDontSee('Omar Reviewer')
            ->assertDontSee('ND-STD-01');
    }

    public function test_public_categories_do_not_expose_commission_policy_code(): void
    {
        Category::factory()->create([
            'slug' => 'policy-public',
            'commission_policy_code' => 'ND-STD-01',
        ]);

        $tree = $this->getJson('/api/v1/categories')->assertOk();
        $this->assertStringNotContainsString('commission_policy_code', (string) json_encode($tree->json()));
        $this->assertStringNotContainsString('ND-STD-01', (string) json_encode($tree->json()));

        $slug = $this->getJson('/api/v1/categories/slug/policy-public')->assertOk();
        $this->assertStringNotContainsString('commission_policy_code', (string) json_encode($slug->json()));
        $this->assertStringNotContainsString('ND-STD-01', (string) json_encode($slug->json()));
    }

    public function test_owner_can_save_phase_parking_unit_code_and_offer_expiry(): void
    {
        $listing = Listing::factory()->create();
        Sanctum::actingAs($listing->owner);

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/specs', [
            'phase' => 'Phase B',
            'parking' => 1,
            'unit_code' => 'B-12',
        ])->assertOk()
            ->assertJsonPath('data.phase', 'Phase B')
            ->assertJsonPath('data.parking', 1)
            ->assertJsonPath('data.unit_code', 'B-12');

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/specs', [
            'parking' => 51,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('parking');

        $financials = $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/financials', [
            'offer_expires_at' => '2026-11-30',
        ])->assertOk();
        $this->assertStringStartsWith('2026-11-30', (string) $financials->json('data.offer_expires_at'));
        $this->assertSame('2026-11-30', $listing->fresh()->offer_expires_at?->toDateString());
    }

    public function test_seller_cannot_set_assigned_reviewer_on_a_step_save(): void
    {
        $reviewer = $this->makeStaff(StaffRole::Reviewer);
        $listing = Listing::factory()->create(['assigned_reviewer_id' => null]);
        Sanctum::actingAs($listing->owner);

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/specs', [
            'phase' => 'A',
            'assigned_reviewer_id' => $reviewer->id,
        ])->assertOk();

        $this->assertNull($listing->fresh()->assigned_reviewer_id);
    }

    public function test_published_phase_parking_or_offer_expiry_edit_returns_to_review(): void
    {
        $listing = Listing::factory()->published()->create([
            'phase' => 'المرحلة 1',
            'parking' => 1,
            'offer_expires_at' => '2026-10-01 00:00:00',
        ]);
        Sanctum::actingAs($listing->owner);

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/specs', [
            'phase' => 'المرحلة 2',
            'parking' => 3,
        ])->assertOk()
            ->assertJsonPath('data.status', 'in_review');
        $this->assertSame(ListingStatus::InReview, $listing->fresh()->status);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
    }

    public function test_installment_paid_and_evidence_round_trip_on_same_listing_only(): void
    {
        $listing = Listing::factory()->create();
        $other = Listing::factory()->create();
        Sanctum::actingAs($listing->owner);
        $this->seedRequiredDocuments($listing);
        $this->seedRequiredDocuments($other);
        $evidenceId = (int) $listing->documents()->value('id');
        $foreignId = (int) $other->documents()->value('id');
        $originalName = (string) $listing->documents()->value('original_name');

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/financials', [
            'installment_items' => [
                [
                    'sequence' => 1,
                    'due_date' => '2026-11-01',
                    'amount' => 15000,
                    'paid' => true,
                    'evidence_document_id' => $evidenceId,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.installment_items.0.paid', true)
            ->assertJsonPath('data.installment_items.0.evidence_document_id', $evidenceId);

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/financials', [
            'installment_items' => [
                [
                    'sequence' => 1,
                    'amount' => 15000,
                    'paid' => true,
                    'evidence_document_id' => $foreignId,
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('installment_items');

        $this->actingAsStaff();
        $this->get('/dashboard/listings/'.$listing->id)
            ->assertOk()
            ->assertSee($originalName, false)
            ->assertSee(route('dashboard.documents.show', $listing), false);
    }

    public function test_category_commission_policy_is_copied_not_computed_as_payout(): void
    {
        $listing = Listing::factory()->create(['commission_policy_code' => null]);
        $category = Category::factory()->create(['commission_policy_code' => 'ND-STD-01']);
        Sanctum::actingAs($listing->owner);

        $this->putJson('/api/v1/my/listings/'.$listing->id.'/step/category', [
            'category_id' => $category->id,
        ])->assertOk()
            ->assertJsonPath('data.commission_policy_code', 'ND-STD-01')
            ->assertJsonMissingPath('data.commission_amount')
            ->assertJsonMissingPath('data.commission_rate');

        $this->actingAsStaff();
        $this->put(route('dashboard.categories.update', $category), [
            'name_ar' => $category->name_ar,
            'slug' => $category->slug,
            'commission_policy_code' => 'ND-STD-02',
        ])->assertRedirect();
        $this->assertSame('ND-STD-02', $category->fresh()->commission_policy_code);

        $this->post(route('dashboard.categories.store'), [
            'name_ar' => 'فئة سياسة',
            'name_en' => 'Policy category',
            'slug' => 'policy-created',
            'commission_policy_code' => 'ND-STD-03',
        ])->assertRedirect(route('dashboard.categories.index'));
        $this->assertSame('ND-STD-03', Category::query()->where('slug', 'policy-created')->value('commission_policy_code'));
        $this->get(route('dashboard.categories.index'))
            ->assertOk()
            ->assertSee('ND-STD-03', false);
    }

    public function test_staff_can_assign_unassign_reviewer_and_sales_is_rejected(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        $reviewer = $this->makeStaff(StaffRole::Reviewer, ['name' => 'Assigned Reviewer']);
        $inactive = $this->makeStaff(StaffRole::Reviewer, ['name' => 'Inactive Reviewer', 'is_active' => false]);
        $sales = $this->makeStaff(StaffRole::Sales);
        $listing = Listing::factory()->create(['status' => ListingStatus::InReview]);

        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/assign-reviewer', [
                'assigned_reviewer_id' => $reviewer->id,
            ])->assertRedirect();
        $this->assertSame($reviewer->id, $listing->fresh()->assigned_reviewer_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'listing.reviewer_assigned']);
        $this->get('/dashboard/listings/'.$listing->id)
            ->assertOk()
            ->assertSee('Assigned Reviewer', false);

        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/assign-reviewer', [
                'assigned_reviewer_id' => $sales->id,
            ])->assertRedirect()
            ->assertSessionHasErrors('assigned_reviewer_id');
        $this->assertSame($reviewer->id, $listing->fresh()->assigned_reviewer_id);

        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/assign-reviewer', [
                'assigned_reviewer_id' => $inactive->id,
            ])->assertRedirect()
            ->assertSessionHasErrors('assigned_reviewer_id');

        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/assign-reviewer', [
                'assigned_reviewer_id' => null,
            ])->assertRedirect()
            ->assertSessionHasErrors('reason');
        $this->assertSame($reviewer->id, $listing->fresh()->assigned_reviewer_id);

        $this->from('/dashboard/listings/'.$listing->id)
            ->post('/dashboard/listings/'.$listing->id.'/assign-reviewer', [
                'assigned_reviewer_id' => null,
                'reason' => 'Reviewer on leave',
            ])->assertRedirect();
        $this->assertNull($listing->fresh()->assigned_reviewer_id);

        $this->actingAsStaff(StaffRole::Sales);
        $this->post('/dashboard/listings/'.$listing->id.'/assign-reviewer', [
            'assigned_reviewer_id' => $reviewer->id,
        ])->assertForbidden();
    }

    public function test_desk_search_finds_unit_code(): void
    {
        $this->actingAsStaff(StaffRole::Operations);
        Listing::factory()->create([
            'title_ar' => 'وحدة أخرى',
            'public_code' => 'AQ-NOCODE',
            'unit_code' => 'OTHER',
        ]);
        Listing::factory()->create([
            'title_ar' => 'وحدة بالكود',
            'public_code' => 'AQ-HASCODE',
            'unit_code' => 'UNIT-ZX-9',
        ]);

        $this->get('/dashboard/listings?q=UNIT-ZX-9')
            ->assertOk()
            ->assertSee('AQ-HASCODE', false)
            ->assertDontSee('AQ-NOCODE', false);
    }

    private function assertPublicPayloadHidesSecrets(string $encoded): void
    {
        $this->assertStringNotContainsString('SECRET-UNIT-99', $encoded);
        $this->assertStringNotContainsString('Omar Reviewer', $encoded);
        $this->assertStringNotContainsString('ND-STD-01', $encoded);
        $this->assertStringNotContainsString('assigned_reviewer', $encoded);
        $this->assertStringNotContainsString('unit_code', $encoded);
        $this->assertStringNotContainsString('commission_policy_code', $encoded);
    }
}
