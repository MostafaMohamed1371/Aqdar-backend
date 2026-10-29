<?php

namespace Tests\Unit;

use App\Core\Enums\CategoryKind;
use App\Core\Enums\MarketSegment;
use App\Modules\Category\Models\Category;
use Tests\TestCase;

class CategoryProtectionTest extends TestCase
{
    public function test_protected_slugs_cannot_be_treated_as_editable_topics(): void
    {
        foreach (Category::PROTECTED_SLUGS as $slug) {
            $category = new Category(['slug' => $slug]);

            $this->assertTrue($category->isProtected(), $slug.' should be protected');
        }
    }

    public function test_ordinary_topics_are_not_protected(): void
    {
        $category = new Category(['slug' => 'electronics', 'kind' => CategoryKind::Topic]);

        $this->assertFalse($category->isProtected());
        $this->assertFalse($category->isDistressedRoot());
    }

    public function test_distressed_root_is_the_segment_parent(): void
    {
        $root = new Category([
            'slug' => 'distressed',
            'kind' => CategoryKind::Segment,
            'segment' => MarketSegment::Distressed,
            'parent_id' => null,
        ]);

        $this->assertTrue($root->isDistressedRoot());

        $child = new Category([
            'slug' => 'distressed-a',
            'kind' => CategoryKind::DistressedChild,
            'segment' => MarketSegment::Distressed,
            'parent_id' => 1,
        ]);

        $this->assertFalse($child->isDistressedRoot());
    }
}
