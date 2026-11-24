<?php

namespace App\Modules\Dashboard\Services;

/**
 * Step-by-step staff desk workflows (ADM-01 … ADM-15) plus end-to-end product flows.
 * Used by the in-app guide Blade page and the downloadable PDF.
 */
class StaffGuideService
{
    /**
     * @return list<array{
     *     id: string,
     *     screen: string|null,
     *     title: array{en: string, ar: string},
     *     summary: array{en: string, ar: string},
     *     permission: string|null,
     *     route: string|null,
     *     steps: list<array{en: string, ar: string}>
     * }>
     */
    public function sections(): array
    {
        return [
            [
                'id' => 'overview',
                'screen' => null,
                'title' => [
                    'en' => 'How this desk works',
                    'ar' => 'كيف تعمل لوحة التحكم',
                ],
                'summary' => [
                    'en' => 'AQDAR is an Egypt resale marketplace (EGP only). Flutter uses /api/v1; staff use this Blade desk. There is no public staff register — Super Admin creates staff and marketplace users.',
                    'ar' => 'عَقدار سوق إعادة بيع عقاري في مصر (جنيه فقط). تطبيق الجوال يستخدم /api/v1 وهذه اللوحة للموظفين. لا يوجد تسجيل عام للموظفين — ينشئهم المشرف الأعلى.',
                ],
                'permission' => null,
                'route' => 'dashboard',
                'steps' => [
                    ['en' => 'Open /dashboard and sign in with your staff email and password.', 'ar' => 'افتح /dashboard وسجّل الدخول ببريد الموظف وكلمة المرور.'],
                    ['en' => 'If staff 2FA is enabled, enter the emailed code on the two-factor screen.', 'ar' => 'إذا كانت المصادقة الثنائية مفعّلة، أدخل الرمز المرسل بالبريد.'],
                    ['en' => 'Switch Arabic (RTL, menu right) or English (LTR, menu left) from the header.', 'ar' => 'بدّل العربية (من اليمين) أو الإنجليزية (من اليسار) من الشريط العلوي.'],
                    ['en' => 'Menu items appear only when your role has the permission. Hidden buttons are not security — forbidden actions still return 403 and are audited.', 'ar' => 'تظهر عناصر القائمة حسب صلاحيتك. إخفاء الزر ليس حماية — الإجراءات الممنوعة ترجع 403 وتُسجَّل.'],
                    ['en' => 'Phones and emails stay masked until you use a logged Reveal action.', 'ar' => 'الهواتف والبريد تبقى مخفية حتى تستخدم إجراء الكشف المسجَّل.'],
                ],
            ],
            [
                'id' => 'flow_listing',
                'screen' => null,
                'title' => [
                    'en' => 'Workflow: listing publish',
                    'ar' => 'مسار العمل: نشر العقار',
                ],
                'summary' => [
                    'en' => 'Seller drafts on mobile → uploads private docs + photos → submits → staff review docs and listing → approve ≠ publish → Operations publish → buyers see published only.',
                    'ar' => 'البائع يجهّز على الجوال → يرفع مستندات وصور → يرسل للمراجعة → الموظفون يراجعون المستندات والعقار → الاعتماد لا يعني النشر → العمليات تنشر → المشترون يرون المنشور فقط.',
                ],
                'permission' => null,
                'route' => 'dashboard.listings.index',
                'steps' => [
                    ['en' => 'Seller completes the listing wizard and uploads required private documents (National ID, contract, payment proof; distressed also needs developer status letter).', 'ar' => 'يكمل البائع معالج الإدراج ويرفع المستندات المطلوبة (بطاقة، عقد، إيصالات؛ المتعثر يحتاج خطاب المطور).'],
                    ['en' => 'On submit, listing moves to in_review / submitted. Staff see it on ADM-02 queue tab In review.', 'ar' => 'عند الإرسال ينتقل العقار للمراجعة. يظهر في ADM-02 تحت تبويب قيد المراجعة.'],
                    ['en' => 'On ADM-03, open the listing checklist, download via signed URL (~10 min), approve or reject each document (watermarked; every open is logged).', 'ar' => 'في ADM-03 افتح قائمة المستندات، نزّل برابط موقّع (~10 دقائق)، اعتمد أو ارفض كل ملف (بعلامة مائية؛ كل فتح يُسجَّل).'],
                    ['en' => 'On ADM-02 detail: verify financials (verified_* beside declared), approve/reject photos, assign reviewer if needed.', 'ar' => 'في تفاصيل ADM-02: تحقق من الأرقام المالية، اعتمد/ارفض الصور، عيّن مراجعاً إن لزم.'],
                    ['en' => 'Reviewer/Operations: Approve, Request changes (reason), or Reject (reason). Approve does not publish.', 'ar' => 'المراجع/العمليات: اعتمد أو اطلب تعديلات (سبب) أو ارفض (سبب). الاعتماد لا ينشر.'],
                    ['en' => 'Operations/Super Admin with publish_listing: Publish, Hold, Close, Unpublish, Under offer, Archive (reason where required).', 'ar' => 'العمليات/المشرف بصلاحية النشر: انشر، علّق، أغلق، ألغِ النشر، تحت عرض، أرشف (مع سبب عند الحاجة).'],
                    ['en' => 'Buyers browse only published listings. Videos stay private even after media approve.', 'ar' => 'المشترون يرون المنشور فقط. الفيديو يبقى خاصاً حتى بعد اعتماد الوسائط.'],
                ],
            ],
            [
                'id' => 'flow_crm',
                'screen' => null,
                'title' => [
                    'en' => 'Workflow: inquiry → lead → case → commission',
                    'ar' => 'مسار العمل: استفسار → عميل → حالة → عمولة',
                ],
                'summary' => [
                    'en' => 'Buyer interest becomes a lead on ADM-07, may convert to a transfer case on ADM-09, then Finance records commission on ADM-10 after the case is completed.',
                    'ar' => 'اهتمام المشتري يصبح عميلاً في ADM-07 وقد يتحول لحالة نقل في ADM-09 ثم تسجّل المالية العمولة في ADM-10 بعد إكمال الحالة.',
                ],
                'permission' => null,
                'route' => 'dashboard.leads.index',
                'steps' => [
                    ['en' => 'Buyer sends inquiry / viewing request from the app. Staff get inbox alerts (unassigned lead, new request).', 'ar' => 'يرسل المشتري استفساراً أو طلب معاينة. يصل تنبيه لصندوق الوارد (عميل غير معيّن، طلب جديد).'],
                    ['en' => 'ADM-07: Operations assigns Sales. Sales works assigned leads only — notes, calls, tasks, stage changes, confirm/cancel viewing.', 'ar' => 'ADM-07: العمليات تعيّن المبيعات. المبيعات ترى المعيّنين فقط — ملاحظات، مكالمات، مهام، مراحل، تأكيد/إلغاء المعاينة.'],
                    ['en' => 'Reveal buyer/seller phone only when needed (logged). SLA badge shows overdue follow-ups.', 'ar' => 'اكشف الهاتف عند الحاجة فقط (مسجَّل). شارة SLA تظهر المتابعة المتأخرة.'],
                    ['en' => 'ADM-08: match buyer requests, send listings, open a lead from a match, or convert to a transfer case.', 'ar' => 'ADM-08: طابق طلبات المشترين، أرسل عقارات، افتح عميلاً من مطابقة، أو حوّل لحالة نقل.'],
                    ['en' => 'ADM-09: advance internal stage vs client-safe lifecycle (hold / waiting / cancelled / lost / completed). Upload developer letter and title checklist by hand.', 'ar' => 'ADM-09: تقدّم المرحلة الداخلية مقابل دورة العميل الآمنة. ارفع خطاب المطور وقائمة نقل الملكية يدوياً.'],
                    ['en' => 'Record offline escrow on the case (edit_commissions); release/refund needs approve_commissions + reason.', 'ar' => 'سجّل Escrow دون اتصال على الحالة؛ الإفراج/الاسترداد يحتاج موافقة + سبب.'],
                    ['en' => 'ADM-10: after case completed, Finance creates commission (pending). Operations approve. Sales see own assigned rows only. Platform does not collect money.', 'ar' => 'ADM-10: بعد إكمال الحالة تنشئ المالية عمولة (معلّقة). العمليات تعتمد. المبيعات ترى صفوفها فقط. المنصة لا تحصّل أموالاً.'],
                ],
            ],
            [
                'id' => 'adm_01',
                'screen' => 'ADM-01',
                'title' => ['en' => 'Executive dashboard', 'ar' => 'لوحة القيادة التنفيذية'],
                'summary' => [
                    'en' => 'Home KPIs, needs-action queues, funnel, charts, Egypt map. Any signed-in staff.',
                    'ar' => 'مؤشرات، طوابير تحتاج إجراء، مسار التحويل، رسوم، خريطة مصر. لأي موظف مسجّل.',
                ],
                'permission' => null,
                'route' => 'dashboard',
                'steps' => [
                    ['en' => 'Open Home. Date presets: this year, this month, last 30 days, last 6 months (or custom from/to).', 'ar' => 'افتح الرئيسية. الفترات: هذه السنة، هذا الشهر، آخر 30 يوماً، آخر 6 أشهر (أو من/إلى).'],
                    ['en' => 'Read period KPIs and % vs previous window. Queue counters (in review, overdue, unassigned) are current, not date-filtered.', 'ar' => 'اقرأ مؤشرات الفترة ونسب المقارنة. عدّادات الطوابير حالية وليست مقيّدة بالتاريخ.'],
                    ['en' => 'Use needs-action links (e.g. leads without owner → ADM-07 unassigned).', 'ar' => 'استخدم روابط الإجراءات المطلوبة (مثل عملاء بلا مالك ← ADM-07 غير معيّن).'],
                    ['en' => 'Check listing performance charts, status donut, Egypt governorate map, and ops health (failed jobs / pushes).', 'ar' => 'راجع رسوم الأداء ودائرة الحالة وخريطة المحافظات وصحة التشغيل.'],
                ],
            ],
            [
                'id' => 'adm_02',
                'screen' => 'ADM-02',
                'title' => ['en' => 'Listings moderation', 'ar' => 'مراجعة العقارات'],
                'summary' => [
                    'en' => 'Queue + detail. approve_listing to review; publish_listing to publish/hold/close.',
                    'ar' => 'الطابور والتفاصيل. صلاحية الاعتماد للمراجعة؛ صلاحية النشر للنشر/التعليق/الإغلاق.',
                ],
                'permission' => 'approve_listing',
                'route' => 'dashboard.listings.index',
                'steps' => [
                    ['en' => 'Filter tabs: all, in review, needs changes, approved, published, hold, rejected, draft. Search title or public code / unit code.', 'ar' => 'التبويبات: الكل، قيد المراجعة، يحتاج تعديلات، معتمد، منشور، معلّق، مرفوض، مسودة. ابحث بالعنوان أو الرمز.'],
                    ['en' => 'Open a listing. Compare declared vs verified money; save verify financials.', 'ar' => 'افتح العقار. قارن المعلن بالمتحقق؛ احفظ التحقق المالي.'],
                    ['en' => 'Approve or reject each photo. Assign reviewer when needed.', 'ar' => 'اعتمد أو ارفض كل صورة. عيّن مراجعاً عند الحاجة.'],
                    ['en' => 'Approve / request changes / reject / reopen (reason required on change/reject).', 'ar' => 'اعتمد / اطلب تعديلات / ارفض / أعد الفتح (السبب مطلوب عند التعديل/الرفض).'],
                    ['en' => 'If you have publish_listing: publish, unpublish, under offer, hold, close, archive.', 'ar' => 'إن كانت لديك صلاحية النشر: انشر، ألغِ النشر، تحت عرض، علّق، أغلق، أرشف.'],
                    ['en' => 'If publish is blocked for documents, open the documents desk link, approve files, retry.', 'ar' => 'إذا مُنع النشر لنقص اعتماد المستندات، افتح مراجعة المستندات، اعتمد الملفات، ثم أعد المحاولة.'],
                ],
            ],
            [
                'id' => 'adm_03',
                'screen' => 'ADM-03',
                'title' => ['en' => 'Private document review', 'ar' => 'مراجعة المستندات الخاصة'],
                'summary' => [
                    'en' => 'Required types by segment, versions, signed download. Permission: open_private_documents.',
                    'ar' => 'الأنواع المطلوبة حسب الشريحة، الإصدارات، تنزيل موقّع. الصلاحية: open_private_documents.',
                ],
                'permission' => 'open_private_documents',
                'route' => 'dashboard.documents.index',
                'steps' => [
                    ['en' => 'Open the documents queue, then a listing checklist.', 'ar' => 'افتح طابور المستندات ثم قائمة عقار.'],
                    ['en' => 'Use signed Open (≈10 minutes). Image/PDF downloads are watermarked with staff id.', 'ar' => 'استخدم فتح موقّع (~10 دقائق). التنزيلات تحمل علامة مائية برقم الموظف.'],
                    ['en' => 'Approve, reject, or request replacement per file. Prior versions stay visible.', 'ar' => 'اعتمد أو ارفض أو اطلب استبدالاً لكل ملف. الإصدارات السابقة تبقى ظاهرة.'],
                    ['en' => 'Sales/Finance opening this screen get 403 + permission_denied audit.', 'ar' => 'المبيعات/المالية يحصلون على 403 مع سجل رفض صلاحية.'],
                ],
            ],
            [
                'id' => 'adm_04',
                'screen' => 'ADM-04',
                'title' => ['en' => 'Users & identities', 'ar' => 'المستخدمون والهويات'],
                'summary' => [
                    'en' => 'Marketplace users. view_users to list; suspend_user to create/edit/suspend/closure.',
                    'ar' => 'مستخدمو السوق. عرض للقائمة؛ تعليق للإنشاء/التعديل/التعليق/الإغلاق.',
                ],
                'permission' => 'view_users',
                'route' => 'dashboard.users.index',
                'steps' => [
                    ['en' => 'List users — emails/phones masked until Reveal (reveal_phone, logged).', 'ar' => 'اعرض المستخدمين — البريد/الهاتف مخفيان حتى الكشف (مسجَّل).'],
                    ['en' => 'Create or edit (email or phone required). Sales may list but not mutate.', 'ar' => 'أنشئ أو عدّل (بريد أو هاتف مطلوب). المبيعات قد تعرض دون تعديل.'],
                    ['en' => 'Suspend with reason / reactivate.', 'ar' => 'علّق مع سبب / أعد التفعيل.'],
                    ['en' => 'Complete or decline user-requested account closure after legal review (anonymize, no hard delete).', 'ar' => 'أكمل أو ارفض طلب إغلاق الحساب بعد المراجعة القانونية (إخفاء هوية دون حذف قاسٍ).'],
                ],
            ],
            [
                'id' => 'adm_05',
                'screen' => 'ADM-05',
                'title' => ['en' => 'Developers & projects', 'ar' => 'المطورون والمشاريع'],
                'summary' => [
                    'en' => 'Catalog lookup + internal CRM fields on developers (never exposed to marketplace API). manage_catalog.',
                    'ar' => 'كتالوج + حقول CRM داخلية للمطورين (لا تظهر في واجهة السوق). manage_catalog.',
                ],
                'permission' => 'manage_catalog',
                'route' => 'dashboard.developers.index',
                'steps' => [
                    ['en' => 'Add/edit developers and projects. Retire rows (is_active=false); do not hard-delete protected «other».', 'ar' => 'أضف/عدّل المطورين والمشاريع. أوقف الصفوف دون حذف قاسٍ للعنصر المحمي «أخرى».'],
                    ['en' => 'Fill staff-only contact person, phone, email, website, internal notes.', 'ar' => 'املأ جهة الاتصال والهاتف والبريد والموقع والملاحظات الداخلية للموظفين فقط.'],
                ],
            ],
            [
                'id' => 'adm_06',
                'screen' => 'ADM-06',
                'title' => ['en' => 'Staff & roles', 'ar' => 'الموظفون والصلاحيات'],
                'summary' => [
                    'en' => 'Super Admin only (manage_staff). Roles: super_admin, operations, reviewer, sales, finance. Teams optional.',
                    'ar' => 'المشرف الأعلى فقط. الأدوار: مشرف، عمليات، مراجع، مبيعات، مالية. الفرق اختيارية.',
                ],
                'permission' => 'manage_staff',
                'route' => 'dashboard.staff.index',
                'steps' => [
                    ['en' => 'Create staff (name, email, password, role). Edit — blank password keeps current.', 'ar' => 'أنشئ موظفاً (اسم، بريد، كلمة مرور، دور). التعديل — كلمة فارغة تبقي الحالية.'],
                    ['en' => 'Revoke/restore access; cannot revoke self; cannot remove last super admin.', 'ar' => 'ألغِ/استعد الوصول؛ لا تلغِ نفسك؛ لا تحذف آخر مشرف أعلى.'],
                    ['en' => 'Manage staff teams: Sales number/hours on a team override platform sales contact for assigned records.', 'ar' => 'أدِر فرق الموظفين: رقم/ساعات الفريق تستبدل رقم المنصة للسجلات المعيّنة.'],
                ],
            ],
            [
                'id' => 'adm_07',
                'screen' => 'ADM-07',
                'title' => ['en' => 'Sales CRM & leads', 'ar' => 'المبيعات والعملاء'],
                'summary' => [
                    'en' => 'manage_crm. Sales: assigned only. Includes support chat at /dashboard/support.',
                    'ar' => 'manage_crm. المبيعات: المعيّنون فقط. يشمل دردشة الدعم /dashboard/support.',
                ],
                'permission' => 'manage_crm',
                'route' => 'dashboard.leads.index',
                'steps' => [
                    ['en' => 'Filter by stage; watch SLA Overdue badge.', 'ar' => 'صفِّ حسب المرحلة؛ راقب شارة التأخير.'],
                    ['en' => 'Ops/Admin assign or reassign. Add lead (campaign/referral + buyer id supported).', 'ar' => 'العمليات/المشرف يعيّنون. أضف عميلاً (حملة/إحالة + معرّف مشترٍ).'],
                    ['en' => 'On detail: activity note/call/task, status, open case, reveal phones, confirm/cancel viewing.', 'ar' => 'في التفاصيل: نشاط، حالة، افتح حالة، اكشف هواتف، أكّد/ألغِ المعاينة.'],
                    ['en' => 'Open Support desk to reply to marketplace Sales threads (SUP-). Polling every 3s if Pusher is down.', 'ar' => 'افتح الدعم للرد على محادثات المبيعات (SUP-). استطلاع كل 3 ثوانٍ إن تعطل Pusher.'],
                ],
            ],
            [
                'id' => 'adm_08',
                'screen' => 'ADM-08',
                'title' => ['en' => 'Buyer requests', 'ar' => 'طلبات المشترين'],
                'summary' => [
                    'en' => 'Custom buy requests: match, send, respond, convert. manage_crm; Sales assigned-only.',
                    'ar' => 'طلبات شراء مخصصة: مطابقة، إرسال، رد، تحويل. manage_crm؛ المبيعات للمعيّنين.',
                ],
                'permission' => 'manage_crm',
                'route' => 'dashboard.requests.index',
                'steps' => [
                    ['en' => 'Use type tabs (apartments / villas / land / investment) and pipeline chevrons.', 'ar' => 'استخدم تبويبات النوع وأسهم المسار.'],
                    ['en' => 'Assign (ops/admin). Refresh matches, attach, send, record response, open lead, convert to case.', 'ar' => 'عيّن (عمليات/مشرف). حدّث المطابقات، اربط، أرسل، سجّل الرد، افتح عميلاً، حوّل لحالة.'],
                ],
            ],
            [
                'id' => 'adm_09',
                'screen' => 'ADM-09',
                'title' => ['en' => 'Transfer cases', 'ar' => 'حالات النقل'],
                'summary' => [
                    'en' => 'Internal stage + lifecycle. manage_crm or Finance advance_finance_stages (finance stages only).',
                    'ar' => 'مرحلة داخلية + دورة حياة. manage_crm أو المالية لمراحل المالية فقط.',
                ],
                'permission' => 'manage_crm',
                'route' => 'dashboard.cases.index',
                'steps' => [
                    ['en' => 'Filter by lifecycle and stage. Open case detail.', 'ar' => 'صفِّ حسب الدورة والمرحلة. افتح التفاصيل.'],
                    ['en' => 'Advance stage (advance_case). Set lifecycle hold/cancel/lost/waiting/completed with reason (case_history).', 'ar' => 'قدّم المرحلة. عيّن الدورة مع سبب (يُكتب في التاريخ).'],
                    ['en' => 'Upload documents; signed download watermarked. Confirm/reject suggested extraction values.', 'ar' => 'ارفع مستندات؛ تنزيل موقّع بعلامة. أكّد/ارفض القيم المقترحة للاستخراج.'],
                    ['en' => 'Complete title-transfer checklist (8 steps). Record escrow; settle with reason when allowed.', 'ar' => 'أكمل قائمة نقل الملكية (8 خطوات). سجّل Escrow؛ سوّه مع سبب عند السماح.'],
                ],
            ],
            [
                'id' => 'adm_10',
                'screen' => 'ADM-10',
                'title' => ['en' => 'Commissions & revenue', 'ar' => 'العمولات والإيرادات'],
                'summary' => [
                    'en' => 'Finance edits, Operations approve, Sales view own. Default rate from settings.',
                    'ar' => 'المالية تعدّل، العمليات تعتمد، المبيعات تعرض الخاصة بها. النسبة الافتراضية من الإعدادات.',
                ],
                'permission' => 'view_commissions',
                'route' => 'dashboard.commissions.index',
                'steps' => [
                    ['en' => 'Filter by month. Read KPIs, bars, and donut (apartment/villa/land/investment).', 'ar' => 'صفِّ بالشهر. اقرأ المؤشرات والرسوم.'],
                    ['en' => 'Finance: create from completed case (unique). Update with reason (re-pends).', 'ar' => 'المالية: أنشئ من حالة مكتملة (فريدة). عدّل مع سبب (يعود للتعليق).'],
                    ['en' => 'Operations: approve pending rows. Export CSV when export_csv allowed (no phones).', 'ar' => 'العمليات: اعتمد المعلّق. صدّر CSV عند السماح (بدون هواتف).'],
                ],
            ],
            [
                'id' => 'adm_11',
                'screen' => 'ADM-11',
                'title' => ['en' => 'Content & notifications', 'ar' => 'المحتوى والإشعارات'],
                'summary' => [
                    'en' => 'Templates, banners, FAQs, broadcasts. manage_catalog; Operations may schedule/approve via approve_broadcasts.',
                    'ar' => 'قوالب، بانرات، أسئلة، بث. manage_catalog؛ العمليات قد تجدول/تعتمد عبر approve_broadcasts.',
                ],
                'permission' => 'manage_catalog',
                'route' => 'dashboard.content.index',
                'steps' => [
                    ['en' => 'Edit templates (no phones/emails/links in copy). Test-send to active staff.', 'ar' => 'عدّل القوالب (بدون هواتف/بريد/روابط). أرسل تجريبياً للموظفين النشطين.'],
                    ['en' => 'Manage banners, FAQs, category help, support copy, push checklist / FCM verify.', 'ar' => 'أدِر البانرات والأسئلة ونصوص الفئات والدعم وفحص FCM.'],
                    ['en' => 'Schedule broadcast to marketing opted-in users. Approve broadcast (not own row, except Super Admin).', 'ar' => 'جدول بثاً للموافقين على التسويق. اعتمد البث (ليس صفك إلا المشرف).'],
                    ['en' => 'Header bell → /dashboard/inbox for staff operational alerts; mark read deep-links to queues.', 'ar' => 'الجرس ← صندوق الوارد لتنبيهات التشغيل؛ تعليم كمقروء يوجّه للطوابير.'],
                ],
            ],
            [
                'id' => 'adm_12',
                'screen' => 'ADM-12',
                'title' => ['en' => 'Reports & exports', 'ar' => 'التقارير والتصدير'],
                'summary' => [
                    'en' => 'CSV/PDF exports. export_csv. Phones need reveal_phone + include_phones=1 (audited).',
                    'ar' => 'تصدير CSV/PDF. هواتف تحتاج reveal_phone و include_phones=1 (مسجَّل).',
                ],
                'permission' => 'export_csv',
                'route' => 'dashboard.reports.index',
                'steps' => [
                    ['en' => 'Set date range / owner / status. Review KPIs, funnels, ledger/forecast if you have commission permission.', 'ar' => 'عيّن الفترة/المالك/الحالة. راجع المؤشرات والمسارات والدفتر إن وُجدت صلاحية عمولة.'],
                    ['en' => 'Export type: listings, users, requests, revenue, sales, ledger. PDF uses same rows as CSV (ASCII/Helvetica).', 'ar' => 'صدّر النوع المطلوب. PDF بنفس صفوف CSV.'],
                    ['en' => 'Default files omit phones/email. Users export as USR-{id}.', 'ar' => 'الملفات الافتراضية بلا هواتف/بريد. المستخدمون كـ USR-{id}.'],
                ],
            ],
            [
                'id' => 'adm_13',
                'screen' => 'ADM-13',
                'title' => ['en' => 'Platform settings', 'ar' => 'إعدادات المنصة'],
                'summary' => [
                    'en' => 'General / Security / Audit tabs. manage_catalog (Super Admin). Sales contact applies to GET /config immediately.',
                    'ar' => 'تبويبات عامة/أمان/تدقيق. رقم المبيعات يطبَّق فوراً على GET /config.',
                ],
                'permission' => 'manage_catalog',
                'route' => 'dashboard.settings.index',
                'steps' => [
                    ['en' => 'Set sales name/phone/hours (AR/EN), assignment rule manual|round_robin, maintenance mode, smart-notification flag.', 'ar' => 'عيّن اسم/هاتف/ساعات المبيعات، قاعدة التعيين، وضع الصيانة، تنبيهات ذكية.'],
                    ['en' => 'Maintenance 503s marketplace API; this desk stays up. Currency locked to EGP. Buyer↔seller chat permanently disallowed.', 'ar' => 'الصيانة توقف واجهة السوق؛ اللوحة تبقى. العملة جنيه. دردشة مشترٍ↔بائع ممنوعة.'],
                    ['en' => 'Review push provider status. Staff 2FA / IP allowlist only when env flags are on.', 'ar' => 'راجع حالة الدفع. المصادقة الثنائية/قائمة IP عند تفعيل متغيرات البيئة فقط.'],
                ],
            ],
            [
                'id' => 'adm_14',
                'screen' => 'ADM-14',
                'title' => ['en' => 'Activity log', 'ar' => 'سجل النشاط'],
                'summary' => [
                    'en' => 'Immutable audit_logs / access_logs search. manage_staff. No delete. Default retention 24 months.',
                    'ar' => 'بحث سجلات التدقيق/الوصول غير القابلة للحذف. الاحتفاظ الافتراضي 24 شهراً.',
                ],
                'permission' => 'manage_staff',
                'route' => 'dashboard.audit.index',
                'steps' => [
                    ['en' => 'Filter by actor, action, date. Export when needed.', 'ar' => 'صفِّ حسب الفاعل والإجراء والتاريخ. صدّر عند الحاجة.'],
                    ['en' => 'There is no delete route — prune is via aqdar:prune-audit.', 'ar' => 'لا يوجد حذف من الواجهة — التنظيف عبر أمر aqdar:prune-audit.'],
                ],
            ],
            [
                'id' => 'adm_15',
                'screen' => 'ADM-15',
                'title' => ['en' => 'Reference data', 'ar' => 'القيم المرجعية'],
                'summary' => [
                    'en' => 'Categories, locations, unit types, amenities, tags, category form fields. manage_catalog.',
                    'ar' => 'فئات، مواقع، أنواع وحدات، مرافق، وسوم، حقول النموذج. manage_catalog.',
                ],
                'permission' => 'manage_catalog',
                'route' => 'dashboard.categories.index',
                'steps' => [
                    ['en' => 'Maintain bilingual names/help. Distressed A/B and protected slugs cannot be deleted/deactivated.', 'ar' => 'حافظ على الأسماء والنصوص ثنائية اللغة. شرائح المتعثر المحمية لا تُحذف.'],
                    ['en' => 'Retire reference rows (is_active=false) instead of hard delete.', 'ar' => 'أوقف الصفوف المرجعية بدل الحذف القاسي.'],
                    ['en' => 'Category form (FR-051): GET/PUT form publishes a new form version for seller wizard fields.', 'ar' => 'نموذج الفئة: الحفظ ينشر إصداراً جديداً لحقول معالج البائع.'],
                    ['en' => 'Commission policy code on category is reference for listings; not exposed on public GET /categories.', 'ar' => 'رمز سياسة العمولة مرجعي للإدراجات؛ لا يظهر في GET العام للفئات.'],
                ],
            ],
            [
                'id' => 'roles',
                'screen' => null,
                'title' => ['en' => 'Roles cheat sheet', 'ar' => 'ملخص الأدوار'],
                'summary' => [
                    'en' => 'Who can do what at a glance. Exact gates are staff.can middleware + policies.',
                    'ar' => 'من يفعل ماذا باختصار. التحكم الفعلي عبر middleware السياسات.',
                ],
                'permission' => null,
                'route' => null,
                'steps' => [
                    ['en' => 'Super Admin: full desk including staff, settings, audit, catalog.', 'ar' => 'المشرف الأعلى: اللوحة كاملة بما فيها الموظفون والإعدادات والتدقيق والكتالوج.'],
                    ['en' => 'Operations: listings publish, CRM assign, commissions approve, broadcasts approve, users suspend.', 'ar' => 'العمليات: نشر العقارات، تعيين CRM، اعتماد عمولات/بث، تعليق مستخدمين.'],
                    ['en' => 'Reviewer: approve listings + private docs; cannot publish.', 'ar' => 'المراجع: اعتماد عقارات ومستندات؛ لا ينشر.'],
                    ['en' => 'Sales: assigned CRM/requests/cases/support; view own commissions; list users (no mutate).', 'ar' => 'المبيعات: CRM المعيّن؛ عرض عمولاتها؛ عرض مستخدمين دون تعديل.'],
                    ['en' => 'Finance: commission edit + finance case stages; no listing moderation.', 'ar' => 'المالية: تعديل عمولات ومراحل مالية؛ بلا مراجعة عقارات.'],
                ],
            ],
            [
                'id' => 'mobile',
                'screen' => null,
                'title' => ['en' => 'Mobile API vs this desk', 'ar' => 'واجهة الجوال مقابل هذه اللوحة'],
                'summary' => [
                    'en' => 'Flutter never uses Blade routes. Staff never use marketplace Sanctum tokens for admin actions.',
                    'ar' => 'فلاتر لا يستخدم مسارات Blade. الموظفون لا يستخدمون توكن السوق لإجراءات الإدارة.',
                ],
                'permission' => null,
                'route' => null,
                'steps' => [
                    ['en' => 'Public/config, auth OTP, listings browse/map/favorites, seller my/listings, inquiries, buyer requests, notifications → /api/v1.', 'ar' => 'الإعدادات العامة، OTP، التصفح، إدراجات البائع، الاستفسارات، الطلبات، الإشعارات ← /api/v1.'],
                    ['en' => 'Marketplace writes to settings/users/catalog stay 403 — desk owns those.', 'ar' => 'كتابات السوق للإعدادات/المستخدمين/الكتالوج تبقى 403 — اللوحة تملكها.'],
                    ['en' => 'Local demo OTP is 1234 (never returned in JSON). Seeded staff: test@example.com / password.', 'ar' => 'OTP المحلي 1234 (لا يُعاد في JSON). موظف تجريبي: test@example.com / password.'],
                    ['en' => 'Download this guide as PDF anytime from the Guide screen for offline training.', 'ar' => 'نزّل هذا الدليل PDF من شاشة الدليل للتدريب دون اتصال.'],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function pdfLines(): array
    {
        $lines = [
            'AQDAR staff desk — workflow guide',
            'Egypt resale marketplace · EGP only · Blade admin at /dashboard',
            'Generated: '.now()->toDateTimeString(),
            '',
        ];

        foreach ($this->sections() as $section) {
            $title = $section['screen']
                ? $section['screen'].' — '.$section['title']['en']
                : $section['title']['en'];
            $lines[] = $title;
            $lines[] = $section['summary']['en'];
            if ($section['permission']) {
                $lines[] = 'Permission: '.$section['permission'];
            }
            if ($section['route']) {
                $lines[] = 'Route name: '.$section['route'];
            }
            foreach ($section['steps'] as $i => $step) {
                $lines[] = ($i + 1).'. '.$step['en'];
            }
            $lines[] = '';
        }

        return $lines;
    }

    public function pdfBinary(): string
    {
        return app(GuidePdfWriter::class)->document(
            'AQDAR Staff Desk Guide',
            $this->pdfLines(),
        );
    }
}
