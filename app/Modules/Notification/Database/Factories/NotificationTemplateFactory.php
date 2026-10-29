<?php

namespace App\Modules\Notification\Database\Factories;

use App\Core\Enums\NotificationCategory;
use App\Modules\Notification\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationTemplate> */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        $key = 'listing_published';

        return [
            'key' => $key.'-'.fake()->unique()->numerify('##'),
            'category' => NotificationCategory::Listing,
            'title_ar' => 'تم نشر العقار',
            'title_en' => 'Listing published',
            'body_ar' => 'تم نشر عقارك :listing_code',
            'body_en' => 'Your listing :listing_code is live',
            'is_operational' => true,
            'is_active' => true,
        ];
    }
}
