<?php

namespace App\Modules\Notification\Database\Seeders;

use App\Core\Enums\NotificationCategory;
use App\Modules\Notification\Models\NotificationTemplate;
use App\Modules\Setting\Models\Setting;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['listing_submitted', NotificationCategory::Listing, 'تم استلام إعلانك', 'Listing submitted', 'عقارك :listing_code قيد المراجعة.', 'Your listing :listing_code is in review.'],
            ['listing_approved', NotificationCategory::Listing, 'تم اعتماد العقار', 'Listing approved', 'تم اعتماد عقارك :listing_code وهو بانتظار النشر.', 'Your listing :listing_code was approved and is waiting to go live.'],
            ['listing_needs_changes', NotificationCategory::Listing, 'مطلوب تعديل على العقار', 'Listing needs changes', 'عقارك :listing_code يحتاج تعديلاً قبل النشر.', 'Your listing :listing_code needs changes before it can be published.'],
            ['listing_document_reviewed', NotificationCategory::Listing, 'تم مراجعة مستند العقار', 'Listing document reviewed', 'مستند عقارك :listing_code أصبح :status.', 'A document on listing :listing_code is now :status.'],
            ['listing_media_reviewed', NotificationCategory::Listing, 'تم مراجعة وسائط العقار', 'Listing media reviewed', 'وسائط عقارك :listing_code أصبحت :status.', 'Media on listing :listing_code is now :status.'],
            ['listing_published', NotificationCategory::Listing, 'تم نشر العقار', 'Listing published', 'تم نشر عقارك :listing_code وأصبح مرئياً للباحثين.', 'Your listing :listing_code is live and visible to buyers.'],
            ['listing_rejected', NotificationCategory::Listing, 'لم يُنشر العقار', 'Listing rejected', 'لم يتم نشر عقارك :listing_code.', 'Your listing :listing_code was not published.'],
            ['listing_held', NotificationCategory::Listing, 'تم إيقاف العقار', 'Listing on hold', 'عقارك :listing_code موقوف مؤقتاً.', 'Your listing :listing_code is on hold.'],
            ['listing_under_offer', NotificationCategory::Listing, 'العقار تحت العرض', 'Listing under offer', 'عقارك :listing_code أصبح تحت العرض.', 'Your listing :listing_code is now under offer.'],
            ['listing_closed', NotificationCategory::Listing, 'أُغلق العقار', 'Listing closed', 'أُغلق عقارك :listing_code.', 'Your listing :listing_code was closed.'],
            ['listing_suspended', NotificationCategory::Listing, 'أُزيل العقار من البحث', 'Listing suspended', 'عقارك :listing_code لم يعد ظاهراً في البحث العام.', 'Your listing :listing_code was removed from public search.'],
            ['staff_new_submission', NotificationCategory::System, 'إعلان بانتظار المراجعة', 'Listing awaiting review', 'العقار :listing_code بانتظار المراجعة.', 'Listing :listing_code is awaiting review.'],
            ['staff_unassigned_lead', NotificationCategory::System, 'عميل محتمل بلا مسؤول', 'Unassigned lead', 'العميل المحتمل :lead_code بلا مسؤول.', 'Lead :lead_code has no owner.'],
            ['staff_inquiry_assigned', NotificationCategory::System, 'استفسار جديد على عميلك', 'New inquiry on your lead', 'استفسار :public_code على :listing_code للعميل المحتمل :lead_code.', 'Inquiry :public_code on :listing_code for lead :lead_code.'],
            ['staff_new_appointment', NotificationCategory::System, 'طلب معاينة جديد', 'New viewing request', 'معاينة :public_code على :listing_code.', 'Viewing :public_code on listing :listing_code.'],
            ['staff_new_buyer_request', NotificationCategory::System, 'طلب شراء جديد', 'New buyer request', 'طلب :public_code وصل لطابور المبيعات.', 'Buyer request :public_code is in the sales queue.'],
            ['staff_support_message', NotificationCategory::System, 'رسالة دعم جديدة', 'New support message', 'رسالة جديدة على :public_code.', 'New message on :public_code.'],
            ['support_reply', NotificationCategory::Inquiry, 'رد من المبيعات', 'Sales replied', 'فريق المبيعات رد على :public_code.', 'Sales replied on :public_code.'],
            ['support_closed', NotificationCategory::Inquiry, 'أُغلقت محادثة الدعم', 'Support closed', 'أُغلقت محادثة :public_code.', 'Support thread :public_code was closed.'],
            ['inquiry_created', NotificationCategory::Inquiry, 'تم استلام استفسارك', 'Inquiry received', 'استفسارك عن :listing_code وصل لفريق المبيعات.', 'Your inquiry on :listing_code was received by the sales team.'],
            ['seller_interest', NotificationCategory::Inquiry, 'اهتمام جديد بعقارك', 'New interest in your listing', 'مشتري مهتم بعقارك :listing_code · فريق المبيعات يتولى التواصل.', 'A buyer is interested in :listing_code. The sales team will follow up.'],
            ['appointment_created', NotificationCategory::Inquiry, 'تم استلام طلب المعاينة', 'Viewing requested', 'طلب معاينة :public_code على :listing_code قيد المراجعة.', 'Your viewing request :public_code on :listing_code is in review.'],
            ['appointment_updated', NotificationCategory::Inquiry, 'تحديث على المعاينة', 'Viewing updated', 'تم تحديث حالة معاينة :public_code إلى :status.', 'Viewing :public_code is now :status.'],
            ['buyer_request_created', NotificationCategory::Case, 'تم استلام طلبك', 'Request received', 'طلبك :public_code قيد المراجعة.', 'Your request :public_code is in review.'],
            ['buyer_request_updated', NotificationCategory::Case, 'تحديث على طلبك', 'Request updated', 'تم تحديث حالة طلبك :public_code.', 'Your request :public_code was updated.'],
            ['case_stage_changed', NotificationCategory::Case, 'تحديث مرحلة الحالة', 'Case update', 'حالة :public_code: :client_label', 'Case :public_code: :client_label'],
            ['case_document_requested', NotificationCategory::Case, 'مطلوب مستند للحالة', 'Document requested', 'نحتاج مستنداً لإكمال حالة :public_code.', 'A document is needed to continue case :public_code.'],
            ['account_suspended', NotificationCategory::System, 'تم إيقاف حسابك', 'Account suspended', 'تم إيقاف حسابك على أقدار.', 'Your Aqdar account was suspended.'],
            ['account_reactivated', NotificationCategory::System, 'تم إعادة تفعيل حسابك', 'Account reactivated', 'حسابك على أقدار أصبح نشطاً مجدداً.', 'Your Aqdar account is active again.'],
            ['sales_followup_overdue', NotificationCategory::System, 'متابعة متأخرة', 'Follow-up overdue', 'العميل المحتمل :lead_code تجاوز مهلة المتابعة.', 'Lead :lead_code is past the follow-up SLA.'],
            ['staff_test', NotificationCategory::System, 'إرسال تجريبي', 'Staff test send', 'هذه رسالة تجريبية للموظفين.', 'This is a staff test notification.'],
            ['marketing_broadcast', NotificationCategory::Marketing, 'إعلان المنصة', 'Platform announcement', 'رسالة للمنضمّين للرسائل التسويقية.', 'A message for users who opted in to marketing.'],
            ['smart_saved_search', NotificationCategory::Smart, 'عقار يطابق بحثك', 'A listing matches your search', 'عقار :listing_code في :area يطابق بحثك المحفوظ.', 'Listing :listing_code in :area matches your saved search.'],
            ['smart_price_drop', NotificationCategory::Smart, 'انخفاض في السعر', 'Price drop', 'عقار :listing_code في :area · السعر الجديد :price جنيه.', 'Listing :listing_code in :area · new price :price EGP.'],
            ['smart_favorite_updated', NotificationCategory::Smart, 'تحديث على عقار في مفضلتك', 'Saved listing updated', 'عقار :listing_code في :area المحفوظ لديك · السعر الجديد :price جنيه.', 'Your saved listing :listing_code in :area · new price :price EGP.'],
        ];

        foreach ($templates as [$key, $category, $titleAr, $titleEn, $bodyAr, $bodyEn]) {
            NotificationTemplate::query()->updateOrCreate(
                ['key' => $key],
                [
                    'category' => $category,
                    'title_ar' => $titleAr,
                    'title_en' => $titleEn,
                    'body_ar' => $bodyAr,
                    'body_en' => $bodyEn,
                    'is_operational' => $category !== NotificationCategory::Marketing,
                    'is_active' => true,
                ],
            );
        }

        Setting::query()->updateOrCreate(
            ['key' => 'feature_smart_notifications'],
            ['value' => '0', 'group' => 'notifications'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'notification_quiet_hours'],
            ['value' => (string) config('aqdar.notifications.quiet_hours', ''), 'group' => 'notifications'],
        );
    }
}
