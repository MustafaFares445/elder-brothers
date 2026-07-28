<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['platform_name', 'منصة الإخوة التعليمية', 'string', 'general', 'اسم المنصة'],
            ['support_contact', 'هاتف الدعم: +963900000099', 'string', 'general', 'بيانات التواصل مع الدعم'],
            ['video_completion_percentage', '90', 'integer', 'content', 'نسبة إكمال الفيديو'],
            ['signed_url_ttl_minutes', '15', 'integer', 'content', 'مدة صلاحية الرابط المؤقت بالدقائق'],
            ['default_qr_duration_days', '365', 'integer', 'subscriptions', 'مدة اشتراك رمز الاستجابة السريعة الافتراضية'],
            ['default_qr_max_redemptions', '1', 'integer', 'subscriptions', 'الحد الافتراضي لاستخدام رمز الاستجابة السريعة'],
            ['registration_enabled', '1', 'boolean', 'access', 'السماح بإنشاء حسابات جديدة'],
        ];

        foreach ($settings as [$key, $value, $type, $group, $label]) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                compact('value', 'type', 'group', 'label'),
            );
        }
    }
}
