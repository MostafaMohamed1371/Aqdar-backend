<?php

namespace Tests\Integration;

use App\Core\Enums\FormFieldType;
use App\Core\Enums\StaffRole;
use App\Modules\Category\Models\Category;
use App\Modules\Listing\Models\Listing;
use App\Modules\Listing\Services\DynamicFormService;
use App\Modules\Listing\Services\ListingService;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

class DynamicListingFormTest extends TestCase
{
    use CreatesStaffUsers;
    use RefreshDatabase;

    /** @param  ?list<string>  $options */
    private function fieldRow(string $key, FormFieldType $type, bool $required = false, ?array $options = null): array
    {
        return [
            'field_key' => $key,
            'label_ar' => 'حقل '.$key,
            'label_en' => $key,
            'type' => $type,
            'options' => $options,
            'is_required' => $required,
        ];
    }

    private function draftListingFor(Category $category, User $seller): Listing
    {
        return Listing::factory()->create([
            'user_id' => $seller->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_published_fields_are_served_for_the_category_with_their_version(): void
    {
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin);
        $category = Category::factory()->create();
        app(DynamicFormService::class)->publish($category, [$this->fieldRow('parking_spaces', FormFieldType::Number)], $staff);

        $this->getJson('/api/v1/categories/'.$category->id.'/form')
            ->assertOk()
            ->assertJsonPath('form_version', 1)
            ->assertJsonPath('data.0.key', 'parking_spaces')
            ->assertJsonPath('data.0.type', 'number');
    }

    public function test_custom_answers_are_typed_and_stored_with_the_category_version(): void
    {
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin);
        $category = Category::factory()->create();
        app(DynamicFormService::class)->publish($category, [
            $this->fieldRow('parking_spaces', FormFieldType::Number),
            $this->fieldRow('view', FormFieldType::Select, false, ['sea', 'garden']),
            $this->fieldRow('has_pool', FormFieldType::Boolean),
        ], $staff);
        $seller = User::factory()->seller()->create();
        $listing = $this->draftListingFor($category, $seller);

        $listing = app(ListingService::class)->saveStep($listing, 'specs', [
            'custom_attributes' => ['parking_spaces' => '2', 'view' => 'sea', 'has_pool' => '1'],
        ], $seller);

        $this->assertEqualsCanonicalizing(['parking_spaces' => 2, 'view' => 'sea', 'has_pool' => true], $listing->custom_attributes);
        $this->assertSame(1, (int) $listing->form_version);
    }

    public function test_unknown_custom_field_is_rejected(): void
    {
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin);
        $category = Category::factory()->create();
        app(DynamicFormService::class)->publish($category, [$this->fieldRow('view', FormFieldType::Text)], $staff);
        $seller = User::factory()->seller()->create();
        $listing = $this->draftListingFor($category, $seller);

        $this->expectException(ValidationException::class);

        app(ListingService::class)->saveStep($listing, 'specs', [
            'custom_attributes' => ['not_a_field' => 'x'],
        ], $seller);
    }

    public function test_select_answer_outside_the_options_is_rejected(): void
    {
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin);
        $category = Category::factory()->create();
        app(DynamicFormService::class)->publish($category, [
            $this->fieldRow('view', FormFieldType::Select, false, ['sea', 'garden']),
        ], $staff);
        $seller = User::factory()->seller()->create();
        $listing = $this->draftListingFor($category, $seller);

        $this->expectException(ValidationException::class);

        app(ListingService::class)->saveStep($listing, 'specs', [
            'custom_attributes' => ['view' => 'lake'],
        ], $seller);
    }

    public function test_required_field_blocks_review_until_it_is_answered(): void
    {
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin);
        $category = Category::factory()->create();
        app(DynamicFormService::class)->publish($category, [
            $this->fieldRow('parking_spaces', FormFieldType::Number, true),
        ], $staff);
        $seller = User::factory()->seller()->create();
        $listing = $this->draftListingFor($category, $seller);

        $this->expectException(ValidationException::class);

        app(DynamicFormService::class)->assertComplete($listing);
    }

    public function test_changing_the_category_clears_the_previous_custom_answers(): void
    {
        $staff = $this->actingAsStaff(StaffRole::SuperAdmin);
        $first = Category::factory()->create();
        $second = Category::factory()->create();
        app(DynamicFormService::class)->publish($first, [$this->fieldRow('view', FormFieldType::Text)], $staff);
        $seller = User::factory()->seller()->create();
        $listing = $this->draftListingFor($first, $seller);
        $listing = app(ListingService::class)->saveStep($listing, 'specs', [
            'custom_attributes' => ['view' => 'sea'],
        ], $seller);

        $listing = app(ListingService::class)->saveStep($listing, 'category', [
            'category_id' => $second->id,
        ], $seller);

        $this->assertNull($listing->custom_attributes);
        $this->assertNull($listing->form_version);
    }
}
