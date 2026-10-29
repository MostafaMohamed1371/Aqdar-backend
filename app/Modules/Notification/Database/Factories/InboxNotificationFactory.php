<?php

namespace App\Modules\Notification\Database\Factories;

use App\Core\Enums\NotificationCategory;
use App\Modules\Notification\Models\InboxNotification;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InboxNotification> */
class InboxNotificationFactory extends Factory
{
    protected $model = InboxNotification::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'event_key' => 'listing_published',
            'template_key' => 'listing_published',
            'category' => NotificationCategory::Listing,
            'idempotency_key' => 'listing_published:'.$user->id.':'.fake()->unique()->numerify('####'),
            'title_ar' => 'تم نشر العقار',
            'title_en' => 'Listing published',
            'body_ar' => 'تم نشر عقارك AQ-1001',
            'body_en' => 'Your listing AQ-1001 is live',
            'data' => ['listing_code' => 'AQ-1001'],
            'read_at' => null,
        ];
    }
}
