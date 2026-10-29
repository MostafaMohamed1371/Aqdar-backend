<?php

namespace Tests\Unit;

use App\Modules\Catalog\Models\ContentBlock;
use App\Modules\Catalog\Models\Developer;
use Tests\TestCase;

class HasLocalizedNameTest extends TestCase
{
    public function test_localized_name_follows_locale_with_arabic_fallback(): void
    {
        $developer = new Developer([
            'name_ar' => 'طلعت مصطفى',
            'name_en' => 'Talaat Moustafa Group',
        ]);

        $this->assertSame('طلعت مصطفى', $developer->localizedName('ar'));
        $this->assertSame('Talaat Moustafa Group', $developer->localizedName('en'));

        app()->setLocale('ar');
        $this->assertSame('طلعت مصطفى', $developer->localizedName());

        app()->setLocale('en');
        $this->assertSame('Talaat Moustafa Group', $developer->localizedName());
    }

    public function test_english_falls_back_to_arabic_when_english_is_empty(): void
    {
        $developer = new Developer([
            'name_ar' => 'أخرى',
            'name_en' => '',
        ]);

        $this->assertSame('أخرى', $developer->localizedName('en'));
    }

    public function test_localized_title_and_body_follow_locale(): void
    {
        $block = new ContentBlock([
            'title_ar' => 'ما هو السوق المتعثر؟',
            'title_en' => 'What is distressed?',
            'body_ar' => 'شرح عربي',
            'body_en' => 'English explanation',
        ]);

        $this->assertSame('ما هو السوق المتعثر؟', $block->localizedTitle('ar'));
        $this->assertSame('What is distressed?', $block->localizedTitle('en'));
        $this->assertSame('شرح عربي', $block->localizedBody('ar'));
        $this->assertSame('English explanation', $block->localizedBody('en'));
    }
}
