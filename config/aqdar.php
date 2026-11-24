<?php

return [

    'currency' => 'EGP',

    'otp' => [
        'length' => 4,
        'ttl_seconds' => (int) env('AQDAR_OTP_TTL', 300),
        'resend_after_seconds' => (int) env('AQDAR_OTP_RESEND_AFTER', 45),
        'max_attempts' => (int) env('AQDAR_OTP_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('AQDAR_OTP_DECAY_MINUTES', 10),
        'test_code' => env('AQDAR_OTP_TEST_CODE', '1234'),
    ],

    'sms' => [
        'driver' => env('AQDAR_SMS_DRIVER', 'log'),
        'http' => [
            'url' => env('AQDAR_SMS_HTTP_URL', ''),
            'token' => env('AQDAR_SMS_HTTP_TOKEN', ''),
            'to_field' => env('AQDAR_SMS_HTTP_TO_FIELD', 'to'),
            'message_field' => env('AQDAR_SMS_HTTP_MESSAGE_FIELD', 'message'),
        ],
    ],

    'consent' => [
        'version' => env('AQDAR_CONSENT_VERSION', '1'),
        'terms_url' => env('AQDAR_TERMS_URL', ''),
        'privacy_url' => env('AQDAR_PRIVACY_URL', ''),
    ],

    'auth' => [
        // When true, marketplace write APIs return 422 profile_incomplete until a real name is set.
        'require_profile' => (bool) env('AQDAR_REQUIRE_PROFILE', false),
    ],

    'locales' => ['ar', 'en'],

    'region' => [
        'code' => 'EG',
    ],

    'sales' => [
        'name' => env('SALES_TEAM_NAME', 'فريق مبيعات عَقدار'),
        'name_en' => env('SALES_TEAM_NAME_EN', 'AQDAR Sales Team'),
        'phone' => env('SALES_PHONE', ''),
        'hours' => env('SALES_HOURS', 'يومياً 10ص – 10م'),
        'hours_en' => env('SALES_HOURS_EN', 'Daily 10am – 10pm'),
    ],

    'staff' => [
        'two_factor' => (bool) env('AQDAR_STAFF_TWO_FACTOR', false),
        'ip_allowlist' => array_values(array_filter(array_map('trim', explode(',', (string) env('AQDAR_STAFF_IP_ALLOWLIST', ''))), 'strlen')),
    ],

    'crm' => [
        'sla_hours' => (int) env('AQDAR_CRM_SLA_HOURS', 2),
        'assignment_rule' => env('AQDAR_LEAD_ASSIGNMENT_RULE', 'manual'),
    ],

    'appointments' => [
        'slots' => array_values(array_filter(explode(',', (string) env('AQDAR_VIEWING_SLOTS', '09:00,10:30,12:00,13:30,15:00,16:30')))),
        'days_ahead' => (int) env('AQDAR_VIEWING_DAYS_AHEAD', 30),
        'closed_weekdays' => array_map('intval', array_filter(explode(',', (string) env('AQDAR_VIEWING_CLOSED_WEEKDAYS', '')), 'strlen')),
    ],

    'platform' => [
        'name' => env('AQDAR_PLATFORM_NAME', 'AQDAR'),
        'support_email' => env('AQDAR_SUPPORT_EMAIL', 'support@aqdar.eg'),
        'audit_retention_months' => (int) env('AQDAR_AUDIT_RETENTION_MONTHS', 24),
    ],

    'notifications' => [
        'push_driver' => env('AQDAR_PUSH_DRIVER', 'log'),
        'smart_enabled' => (bool) env('AQDAR_SMART_NOTIFICATIONS', false),
        'quiet_hours' => env('AQDAR_NOTIFICATION_QUIET_HOURS', ''),
        'fcm' => [
            'project_id' => env('AQDAR_FCM_PROJECT_ID', ''),
            'client_email' => env('AQDAR_FCM_CLIENT_EMAIL', ''),
            'private_key' => env('AQDAR_FCM_PRIVATE_KEY', ''),
            'credentials' => env('AQDAR_FCM_CREDENTIALS', ''),
        ],
    ],

    'commission' => [
        'default_rate' => env('AQDAR_COMMISSION_DEFAULT_RATE', '2.5'),
        'default_status' => env('AQDAR_COMMISSION_DEFAULT_STATUS', 'earned'),
        'tax_percent' => env('AQDAR_COMMISSION_TAX_PERCENT'),
    ],

    'map' => [
        'precision' => (int) env('AQDAR_MAP_PRECISION', 3),
    ],

    'backup' => [
        'path' => env('AQDAR_BACKUP_PATH', ''),
        'mysqldump' => env('AQDAR_MYSQLDUMP', ''),
        'dump' => filter_var(env('AQDAR_BACKUP_DUMP', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'files' => [
        'signed_url_minutes' => (int) env('AQDAR_SIGNED_URL_MINUTES', 10),
        'scan' => [
            'driver' => env('AQDAR_UPLOAD_SCAN_DRIVER', 'null'),
            'clamav_binary' => env('AQDAR_CLAMAV_BINARY', 'clamdscan'),
        ],
        'documents' => [
            'disk' => 'private_documents',
            'max_kb' => (int) env('AQDAR_DOCUMENT_MAX_KB', 10240),
            'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
            'types' => [
                'national_id' => ['required' => true],
                'contract' => ['required' => true],
                'payment_proof' => ['required' => true],
                'developer_status_letter' => ['required' => true, 'segments' => ['distressed']],
            ],
        ],
        'media' => [
            'original_disk' => 'private_media',
            'public_disk' => 'public_media',
            'max_kb' => (int) env('AQDAR_MEDIA_MAX_KB', 10240),
            'max_count' => (int) env('AQDAR_MEDIA_MAX_COUNT', 20),
            'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm'],
            'max_width' => 1600,
        ],
        'avatars' => [
            'disk' => 'public_media',
        ],
    ],

    'telephony' => [
        'driver' => env('AQDAR_TELEPHONY_DRIVER', 'log'),
        'calls_per_hour' => (int) env('AQDAR_CALLS_PER_HOUR', 5),
    ],

    'ocr' => [
        'driver' => env('AQDAR_OCR_DRIVER', 'manual'),
        'fields' => ['full_name', 'national_id', 'title_deed_number', 'amount'],
    ],

    'transfer_checklist' => [
        'buyer_identity_verified',
        'seller_identity_verified',
        'contract_signed',
        'developer_noc',
        'fees_paid',
        'title_deed_submitted',
        'title_transfer_filed',
        'title_transferred',
    ],

];
