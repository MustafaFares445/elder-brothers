# عقد حماية الفيديو للأوفلاين

## الاستراتيجية

- يحفظ Laravel ملفات MP4 في التخزين الخاص.
- يتحقق Laravel من الاشتراك والجهاز قبل التشغيل أو التنزيل.
- يصدر Laravel روابط موقعة قصيرة العمر.
- ينشئ Laravel سجل تنزيل مرتبطًا بالمستخدم والجهاز والفيديو.
- ينزّل Flutter الملف المؤقت، يتحقق من SHA-256، يشفره محليًا، ثم يحذف MP4 غير المشفر.
- Laravel لا يعيد أي مفتاح تشفير محلي.

جميع استجابات JSON تحتوي على:

```json
{
  "server_time": "2026-08-02T13:00:00Z"
}
```

## السياسة الافتراضية

```env
SIGNED_URL_TTL_MINUTES=15
OFFLINE_LICENSE_DAYS=30
OFFLINE_REFRESH_AFTER_DAYS=20
OFFLINE_MAX_DEVICES_PER_USER=3
OFFLINE_MAX_DOWNLOADS_PER_VIDEO=2
```

## تسجيل الجهاز

### `POST /api/v1/devices/register`

```json
{
  "device_id": "stable-id-from-flutter",
  "platform": "android",
  "app_version": "1.0.0",
  "device_name": "Samsung S24",
  "fcm_token": null
}
```

```json
{
  "success": true,
  "code": "DEVICE_REGISTERED",
  "data": {
    "device_id": "stable-id-from-flutter",
    "platform": "android",
    "revoked": false,
    "last_seen_at": "2026-08-02T13:00:00Z"
  },
  "server_time": "2026-08-02T13:00:00Z"
}
```

## جلسة تشغيل أونلاين

### `POST /api/v1/videos/{video}/play-session`

يتطلب اشتراكًا فعالًا ويرجع رابط بث موقّع يدعم HTTP Range.

```json
{
  "success": true,
  "code": "PLAY_SESSION_CREATED",
  "data": {
    "video_id": 15,
    "playback_url": "https://example.com/api/v1/videos/15/stream?expires=...&signature=...",
    "expires_at": "2026-08-02T13:15:00Z"
  },
  "server_time": "2026-08-02T13:00:00Z"
}
```

يبقى المسار القديم `/videos/{video}/playback-url` متاحًا للتوافق.

## إنشاء تنزيل أوفلاين

### `POST /api/v1/videos/{video}/offline-downloads`

```json
{
  "device_id": "stable-id-from-flutter",
  "platform": "android",
  "app_version": "1.0.0"
}
```

```json
{
  "success": true,
  "code": "OFFLINE_DOWNLOAD_CREATED",
  "data": {
    "download_id": "0198...",
    "video": {
      "id": 15,
      "title": "المحاضرة الأولى",
      "duration_seconds": 640,
      "poster_url": null
    },
    "file": {
      "url": "https://example.com/api/v1/video-files/15/download?download=0198...&expires=...&signature=...",
      "size_bytes": 104857600,
      "sha256": "original-mp4-sha256",
      "mime": "video/mp4",
      "expires_at": "2026-08-02T13:15:00Z"
    },
    "license": {
      "offline_expires_at": "2026-09-01T13:00:00Z",
      "refresh_after": "2026-08-22T13:00:00Z",
      "can_play_offline": true
    }
  },
  "server_time": "2026-08-02T13:00:00Z"
}
```

خطوات Flutter:

1. تنزيل `file.url` إلى ملف مؤقت داخل sandbox.
2. حساب SHA-256 ومقارنته مع `file.sha256`.
3. تشفير الملف محليًا.
4. حذف MP4 المؤقت غير المشفر.
5. استدعاء complete.

## تأكيد إكمال التشفير

### `POST /api/v1/offline-downloads/{download}/complete`

```json
{
  "encrypted_size_bytes": 104858000,
  "encrypted_sha256": "64-hex-characters",
  "algorithm": "AES-256-CTR+HMAC-SHA256"
}
```

## تحديث الترخيص

### `POST /api/v1/offline-downloads/{download}/refresh`

```json
{
  "success": true,
  "code": "OFFLINE_LICENSE_REFRESHED",
  "data": {
    "download_id": "0198...",
    "offline_expires_at": "2026-09-15T09:00:00Z",
    "refresh_after": "2026-09-05T09:00:00Z",
    "revoked": false,
    "reason": null
  },
  "server_time": "2026-08-15T09:00:00Z"
}
```

عند انتهاء الاشتراك أو إلغاء الجهاز:

```json
{
  "data": {
    "revoked": true,
    "reason": "subscription_expired"
  }
}
```

على Flutter حذف الملف المحلي المشفر عند `revoked=true`.

## حذف التنزيل

### `DELETE /api/v1/offline-downloads/{download}`

يحوّل السجل إلى `deleted` ولا يحذفه فعليًا، للمراجعة الأمنية.

## الأخطاء المهمة

- `403 SUBSCRIPTION_REQUIRED`: لا يوجد اشتراك فعال.
- `422 DEVICE_NOT_REGISTERED`: يجب تسجيل الجهاز أولًا.
- `422 VALIDATION_ERROR`: جهاز ملغى أو تجاوز حد الأجهزة/التنزيلات.
- `403 OFFLINE_DOWNLOAD_NOT_FOUND`: السجل لا يخص المستخدم.
- `409 OFFLINE_DOWNLOAD_REVOKED`: الترخيص ملغى.
- `403 OFFLINE_LICENSE_EXPIRED`: انتهت نافذة الأوفلاين.

## التخزين والأمان

- الملف لا يوجد على public disk.
- الرابط الموقّع صالح افتراضيًا 15 دقيقة.
- `offline_downloads` مرتبط بـ user، video، وdevice.
- تعطيل حساب المستخدم أو حذف الجهاز يلغي تراخيص الأوفلاين.
- لا يرسل Laravel مفاتيح AES أو HMAC إلى Flutter.
- الحماية لا تمنع تسجيل الشاشة أو هجمات الأجهزة المروّتة؛ DRM مرحلة لاحقة.
