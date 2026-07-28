<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['platform_name', 'Elder Brothers', 'string', 'general', 'اسم المنصة'],
            ['support_contact', '', 'string', 'general', 'بيانات التواصل مع الدعم'],
            ['video_completion_percentage', '90', 'integer', 'content', 'نسبة إكمال الفيديو'],
            ['signed_url_ttl_minutes', '15', 'integer', 'content', 'مدة صلاحية الرابط المؤقت'],
            ['default_qr_duration_days', '365', 'integer', 'subscriptions', 'مدة اشتراك QR الافتراضية'],
            ['default_qr_max_redemptions', '1', 'integer', 'subscriptions', 'الحد الافتراضي لاستخدام QR'],
            ['registration_enabled', '1', 'boolean', 'access', 'تفعيل التسجيل'],
        ];

        foreach ($settings as [$key, $value, $type, $group, $label]) {
            PlatformSetting::query()->firstOrCreate(
                ['key' => $key],
                compact('value', 'type', 'group', 'label'),
            );
        }
    }
}
