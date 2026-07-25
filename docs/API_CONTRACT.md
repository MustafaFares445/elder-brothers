# Elder Brothers Mobile API Contract

## Response envelope

```json
{
  "success": true,
  "code": "OPERATION_COMPLETED",
  "message": "Operation completed successfully.",
  "data": {},
  "meta": null
}
```

Errors use:

```json
{
  "success": false,
  "code": "VALIDATION_ERROR",
  "message": "The submitted data is invalid.",
  "errors": {
    "field": ["Validation message"]
  }
}
```

## Authentication

| Method | Endpoint | Main request props |
|---|---|---|
| POST | `/api/v1/auth/register` | `full_name`, `phone`, `password`, `password_confirmation`, optional device props |
| POST | `/api/v1/auth/verify-otp` | `phone`, `otp`, optional device props |
| POST | `/api/v1/auth/resend-otp` | `phone`, `purpose` |
| POST | `/api/v1/auth/login` | `phone`, `password`, optional device props |
| POST | `/api/v1/auth/logout` | optional `device_id`, `remove_fcm_token` |
| POST | `/api/v1/auth/forgot-password` | `phone` |
| POST | `/api/v1/auth/verify-reset-otp` | `phone`, `otp` |
| POST | `/api/v1/auth/reset-password` | `phone`, `reset_token`, `password`, `password_confirmation` |

## Profile

| Method | Endpoint |
|---|---|
| GET | `/api/v1/me` |
| PATCH | `/api/v1/me` |
| POST | `/api/v1/me/avatar` |
| PUT | `/api/v1/me/password` |
| PUT | `/api/v1/me/preferences` |
| POST | `/api/v1/me/devices` |
| DELETE | `/api/v1/me/devices/{device}` |

## Catalog

| Method | Endpoint |
|---|---|
| GET | `/api/v1/home` |
| GET | `/api/v1/academic-years` |
| GET | `/api/v1/academic-years/{academicYear}/subjects` |
| GET | `/api/v1/subjects/{subject}/courses` |
| GET | `/api/v1/courses` |
| GET | `/api/v1/courses/{course}` |
| GET | `/api/v1/courses/{course}/content` |
| GET | `/api/v1/me/courses` |
| GET | `/api/v1/me/subscriptions` |
| GET | `/api/v1/me/subscriptions/{subscription}` |

## QR subscriptions

Preview:

```http
POST /api/v1/subscriptions/qr/preview
```

```json
{
  "code": "ELDER-PHYSICS-2026-GROUP"
}
```

Redeem:

```http
POST /api/v1/subscriptions/qr/redeem
```

```json
{
  "code": "ELDER-PHYSICS-2026-GROUP",
  "device_id": "device-uuid",
  "confirm": true
}
```

## Content

| Method | Endpoint |
|---|---|
| POST | `/api/v1/videos/{video}/playback-url` |
| PUT | `/api/v1/videos/{video}/progress` |
| POST | `/api/v1/videos/{video}/complete` |
| POST | `/api/v1/videos/{video}/download-url` |
| POST | `/api/v1/course-files/{courseFile}/download-url` |

Progress request:

```json
{
  "position_seconds": 2040,
  "duration_seconds": 2720,
  "watched_seconds": 2100,
  "completed": false,
  "event": "heartbeat",
  "device_id": "device-uuid"
}
```

## Notifications and support

| Method | Endpoint |
|---|---|
| GET | `/api/v1/notifications` |
| GET | `/api/v1/notifications/unread-count` |
| PATCH | `/api/v1/notifications/{notification}/read` |
| POST | `/api/v1/notifications/read-all` |
| GET | `/api/v1/content-pages/{slug}` |
| POST | `/api/v1/support-requests` |

## Seeded QR values

- `ELDER-MATH-2026-001`
- `ELDER-PHYSICS-2026-GROUP`
- `ELDER-CHEMISTRY-LIFETIME`
