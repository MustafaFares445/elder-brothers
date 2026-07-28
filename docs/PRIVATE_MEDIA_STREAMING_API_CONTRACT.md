# Elder Brothers Private Course Media & Streaming API Contract

## 1. Scope

This contract documents the private local media changes for:

- Course thumbnail images.
- Course hero images.
- Video thumbnail images.
- Course MP4 videos.
- Course PDF files.
- Signed URL response properties.
- The direct signed video streaming endpoint.
- Flutter integration rules.

All new course media uploaded from Filament is stored on the disk configured by:

```env
COURSE_MEDIA_DISK=local
```

The default `local` disk root is:

```text
storage/app/private
```

Course media is not exposed through the public `/storage` symlink. Access is granted using temporary signed URLs.

---

## 2. Authentication rules

All catalog, playback authorization, progress, and download authorization endpoints require:

```http
Authorization: Bearer {access_token}
Accept: application/json
```

The direct streaming URL does not require a Bearer token because the generated URL contains an expiring Laravel signature.

Do not remove, modify, reorder, decode, or rebuild the query parameters of signed URLs.

---

## 3. Signature properties

Whenever a returned media URL contains a signature, the API also returns the signature as a separate property.

Example:

```json
{
  "thumbnail_url": "https://api.example.com/storage/courses/thumbnails/course.jpg?expires=1785264000&signature=abc123",
  "thumbnail_signature": "abc123",
  "thumbnail_expires_at": "2026-07-28T10:00:00+00:00"
}
```

Rules:

- `signature` is nullable.
- Private local temporary URLs normally have a signature.
- Legacy external URLs may return `signature: null` and `expires_at: null`.
- Flutter must use the complete URL. The separate signature property is informational and can be used for logging, diagnostics, or cache identity.
- Flutter must never append the signature manually.

---

# 4. Catalog response changes

The request endpoints and authentication rules remain unchanged. Only media fields are extended.

## 4.1 Course list object

Returned by endpoints such as:

```http
GET /api/v1/home
GET /api/v1/courses
GET /api/v1/subjects/{subject}/courses
GET /api/v1/me/courses
GET /api/v1/me/subscriptions
```

Example course object:

```json
{
  "id": 12,
  "subject_id": 4,
  "slug": "advanced-mathematics",
  "title": "الرياضيات المتقدمة",
  "short_description": "شرح شامل للتفاضل والتكامل.",
  "thumbnail_url": "https://api.example.com/storage/courses/thumbnails/math.jpg?expires=1785264000&signature=63e8c7",
  "thumbnail_signature": "63e8c7",
  "thumbnail_expires_at": "2026-07-28T10:00:00+00:00",
  "is_featured": true,
  "is_subscribed": true,
  "subscription_status": "active",
  "progress_percentage": 35,
  "videos_count": 8,
  "files_count": 3,
  "total_duration_seconds": 14400,
  "published_at": "2026-07-01T08:00:00+00:00"
}
```

New properties:

| Property | Type | Description |
|---|---:|---|
| `thumbnail_signature` | `string|null` | Signature extracted from `thumbnail_url`. |
| `thumbnail_expires_at` | `string|null` | ISO-8601 expiration of the temporary thumbnail URL. |

## 4.2 Course details

```http
GET /api/v1/courses/{course}
```

Additional hero media fields:

```json
{
  "success": true,
  "code": "OPERATION_COMPLETED",
  "message": "Operation completed successfully.",
  "data": {
    "id": 12,
    "title": "الرياضيات المتقدمة",
    "thumbnail_url": "https://api.example.com/storage/courses/thumbnails/math.jpg?expires=1785264000&signature=63e8c7",
    "thumbnail_signature": "63e8c7",
    "thumbnail_expires_at": "2026-07-28T10:00:00+00:00",
    "hero_url": "https://api.example.com/storage/courses/heroes/math-hero.jpg?expires=1785264000&signature=91ab42",
    "hero_signature": "91ab42",
    "hero_expires_at": "2026-07-28T10:00:00+00:00",
    "description": "الوصف الكامل للدورة",
    "can_view_full_content": true
  },
  "meta": null
}
```

New properties:

| Property | Type |
|---|---:|
| `hero_signature` | `string|null` |
| `hero_expires_at` | `string|null` |

## 4.3 Video content object

Returned within:

```http
GET /api/v1/courses/{course}/content
GET /api/v1/courses/{course}
```

```json
{
  "id": 42,
  "course_id": 12,
  "title": "المحاضرة الأولى",
  "lesson_label": "الدرس 1",
  "thumbnail_url": "https://api.example.com/storage/courses/12/video-thumbnails/1.jpg?expires=1785264000&signature=21af11",
  "thumbnail_signature": "21af11",
  "thumbnail_expires_at": "2026-07-28T10:00:00+00:00",
  "duration_seconds": 1800,
  "watched_seconds": 600,
  "last_position_seconds": 580,
  "progress_percentage": 33,
  "is_completed": false,
  "is_locked": false,
  "is_preview": false,
  "is_downloadable": true,
  "sort_order": 1
}
```

## 4.4 Course PDF object

Returned within:

```http
GET /api/v1/courses/{course}/content?type=files
```

When the student has access:

```json
{
  "id": 15,
  "course_id": 12,
  "title": "ملخص الدورة",
  "original_name": "course-summary.pdf",
  "extension": "pdf",
  "mime_type": "application/pdf",
  "size_bytes": 2048000,
  "size_label": "2 MB",
  "is_downloadable": true,
  "is_locked": false,
  "download_url": "https://api.example.com/storage/courses/12/pdfs/course-summary.pdf?expires=1785264000&signature=a8250f",
  "signature": "a8250f",
  "expires_at": "2026-07-28T10:00:00+00:00",
  "storage": "private_local",
  "sort_order": 1
}
```

When the student does not have access:

```json
{
  "id": 15,
  "is_locked": true,
  "download_url": null,
  "signature": null,
  "expires_at": null,
  "storage": "private_local"
}
```

---

# 5. Request a signed playback URL

## Endpoint

```http
POST /api/v1/videos/{video}/playback-url
```

Authentication: required.

## Request body

```json
{
  "quality": "auto",
  "device_id": "android-36f412",
  "resume": true
}
```

| Property | Required | Allowed values |
|---|---:|---|
| `quality` | No | `auto`, `hd`, `sd` |
| `device_id` | No | String |
| `resume` | No | Boolean, defaults to `true` |

## Success response

```json
{
  "success": true,
  "code": "PLAYBACK_AUTHORIZED",
  "message": "Playback authorized.",
  "data": {
    "video_id": 42,
    "playback_url": "https://api.example.com/api/v1/videos/42/stream?expires=1785264000&signature=e9dd61",
    "stream_url": "https://api.example.com/api/v1/videos/42/stream?expires=1785264000&signature=e9dd61",
    "signature": "e9dd61",
    "format": "mp4",
    "storage": "private_local",
    "supports_range": true,
    "expires_at": "2026-07-28T10:00:00+00:00",
    "start_position_seconds": 580,
    "duration_seconds": 1800,
    "headers": {
      "Accept-Ranges": "bytes"
    }
  },
  "meta": null
}
```

The `playback_url` and `stream_url` currently have the same value. `playback_url` is retained for backward compatibility.

## Errors

| HTTP | Code / reason | Meaning |
|---:|---|---|
| 401 | Unauthenticated | Missing or invalid access token. |
| 403 | `SUBSCRIPTION_REQUIRED` | A non-preview video requires an active subscription. |
| 404 | `VIDEO_SOURCE_NOT_FOUND` | Video path is missing. |
| 409 | `VIDEO_NOT_READY` | Video status is not `ready`. |

---

# 6. Direct signed streaming endpoint

## Endpoint

```http
GET /api/v1/videos/{video}/stream?expires={unix_timestamp}&signature={signature}
```

Also supports:

```http
HEAD /api/v1/videos/{video}/stream?expires={unix_timestamp}&signature={signature}
```

Authentication: the signed URL itself is the authorization. Do not send the mobile Bearer token unless the player adds it automatically.

Clients should obtain this URL only from:

```http
POST /api/v1/videos/{video}/playback-url
```

## Range request

```http
GET /api/v1/videos/42/stream?expires=1785264000&signature=e9dd61
Range: bytes=1048576-2097151
```

## Partial response

```http
HTTP/1.1 206 Partial Content
Accept-Ranges: bytes
Content-Range: bytes 1048576-2097151/20971520
Content-Length: 1048576
Content-Type: video/mp4
Content-Disposition: inline; filename="lecture-1.mp4"
Cache-Control: private, no-store, max-age=0
```

The body contains the requested video bytes.

## Full response

When no `Range` header is supplied:

```http
HTTP/1.1 200 OK
Accept-Ranges: bytes
Content-Length: 20971520
Content-Type: video/mp4
```

## Stream errors

The stream endpoint returns HTTP status responses rather than the normal JSON API envelope.

| HTTP | Meaning |
|---:|---|
| 403 | Signature is missing, invalid, changed, or expired. |
| 404 | Video, source path, or private file does not exist. |
| 416 | Invalid or unsatisfiable byte range. |

For `416`, the response includes:

```http
Content-Range: bytes */20971520
```

---

# 7. Request a signed PDF download URL

## Endpoint

```http
POST /api/v1/course-files/{courseFile}/download-url
```

Authentication: required. An active subscription to the file's course is required.

Request body: empty.

```json
{}
```

## Success response

```json
{
  "success": true,
  "code": "DOWNLOAD_AUTHORIZED",
  "message": "Download authorized.",
  "data": {
    "file_id": 15,
    "download_url": "https://api.example.com/storage/courses/12/pdfs/course-summary.pdf?expires=1785264000&signature=a8250f",
    "signature": "a8250f",
    "filename": "course-summary.pdf",
    "mime_type": "application/pdf",
    "extension": "pdf",
    "size_bytes": 2048000,
    "checksum": null,
    "storage": "private_local",
    "expires_at": "2026-07-28T10:00:00+00:00",
    "headers": {}
  },
  "meta": null
}
```

For a legacy external file URL, `storage` is `external`, while `signature` and `expires_at` may be `null`.

## Errors

| HTTP | Reason |
|---:|---|
| 401 | Unauthenticated. |
| 403 | Subscription required or downloading is disabled. |
| 404 | File does not exist or has no usable URL. |

---

# 8. Request a signed video download URL

## Endpoint

```http
POST /api/v1/videos/{video}/download-url
```

Authentication: required.

Optional request:

```json
{
  "quality": "hd"
}
```

## Success response

```json
{
  "success": true,
  "code": "DOWNLOAD_AUTHORIZED",
  "message": "Download authorized.",
  "data": {
    "video_id": 42,
    "download_url": "https://api.example.com/storage/courses/12/videos/lecture-1.mp4?expires=1785264000&signature=77cb31",
    "signature": "77cb31",
    "filename": "video-42.mp4",
    "mime_type": "video/mp4",
    "size_bytes": null,
    "quality": "hd",
    "checksum": null,
    "storage": "private_local",
    "expires_at": "2026-07-28T10:00:00+00:00",
    "headers": {}
  },
  "meta": null
}
```

---

# 9. Flutter integration guide

## 9.1 Data model additions

```dart
class SignedMediaUrl {
  const SignedMediaUrl({
    required this.url,
    required this.signature,
    required this.expiresAt,
  });

  final String? url;
  final String? signature;
  final DateTime? expiresAt;

  bool get isAvailable => url != null && url!.isNotEmpty;
  bool get isExpired =>
      expiresAt != null && DateTime.now().isAfter(expiresAt!.toLocal());
}
```

Course model additions:

```dart
final String? thumbnailUrl;
final String? thumbnailSignature;
final DateTime? thumbnailExpiresAt;
final String? heroUrl;
final String? heroSignature;
final DateTime? heroExpiresAt;
```

Course file additions:

```dart
final String? downloadUrl;
final String? signature;
final DateTime? expiresAt;
final String storage;
```

Playback model:

```dart
class PlaybackAuthorization {
  const PlaybackAuthorization({
    required this.videoId,
    required this.streamUrl,
    required this.signature,
    required this.expiresAt,
    required this.startPositionSeconds,
    required this.durationSeconds,
    required this.supportsRange,
  });

  final int videoId;
  final String streamUrl;
  final String? signature;
  final DateTime expiresAt;
  final int startPositionSeconds;
  final int durationSeconds;
  final bool supportsRange;
}
```

## 9.2 Request playback authorization

```dart
Future<PlaybackAuthorization> authorizePlayback({
  required Dio dio,
  required int videoId,
  required String deviceId,
}) async {
  final response = await dio.post<Map<String, dynamic>>(
    '/api/v1/videos/$videoId/playback-url',
    data: {
      'quality': 'auto',
      'device_id': deviceId,
      'resume': true,
    },
  );

  final data = response.data!['data'] as Map<String, dynamic>;

  return PlaybackAuthorization(
    videoId: data['video_id'] as int,
    streamUrl: data['stream_url'] as String,
    signature: data['signature'] as String?,
    expiresAt: DateTime.parse(data['expires_at'] as String),
    startPositionSeconds: data['start_position_seconds'] as int,
    durationSeconds: data['duration_seconds'] as int,
    supportsRange: data['supports_range'] as bool,
  );
}
```

## 9.3 Open the video player

Use the full `stream_url` exactly as returned:

```dart
final controller = VideoPlayerController.networkUrl(
  Uri.parse(playback.streamUrl),
);

await controller.initialize();
await controller.seekTo(
  Duration(seconds: playback.startPositionSeconds),
);
await controller.play();
```

The player should send byte-range requests automatically. Do not strip the signed query parameters.

## 9.4 Refresh an expired playback URL

Before reopening or resuming a long-paused video:

```dart
final shouldRefresh = DateTime.now().isAfter(
  playback.expiresAt.subtract(const Duration(seconds: 30)),
);
```

When `shouldRefresh` is true, call the playback authorization endpoint again.

If the player receives HTTP `403`, request a new playback URL once and retry. Do not retry repeatedly with the same expired URL.

## 9.5 Open a PDF

The course content response can already contain a signed `download_url` for an unlocked PDF. It may also be refreshed through the dedicated endpoint.

```dart
Future<String> authorizePdfDownload(Dio dio, int fileId) async {
  final response = await dio.post<Map<String, dynamic>>(
    '/api/v1/course-files/$fileId/download-url',
  );

  return response.data!['data']['download_url'] as String;
}
```

Download the URL exactly as returned:

```dart
await Dio().download(
  signedPdfUrl,
  localPath,
  options: Options(
    followRedirects: true,
  ),
);
```

A separate Dio instance without the API base interceptor is recommended for absolute signed URLs. An interceptor must not replace the host, remove query parameters, or append a second authorization signature.

## 9.6 Image caching

Temporary image URLs change when regenerated. Suggested cache key:

```dart
final cacheKey = thumbnailSignature == null
    ? thumbnailUrl
    : 'course-thumbnail-$courseId-$thumbnailSignature';
```

Refresh the catalog or course details when the media expiration is near.

---

# 10. Storage and deployment

Required environment value:

```env
COURSE_MEDIA_DISK=local
```

Required writable directory:

```text
storage/app/private
```

Deployment commands:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan filament:optimize
php artisan queue:restart
```

Do not move private course files into `public/storage`.

Suggested Nginx/Apache settings:

- Allow PHP responses to stream for a long duration.
- Disable proxy buffering for the signed video stream route when the reverse proxy causes playback delays.
- Ensure request headers preserve `Range`.
- Ensure responses preserve `Content-Range`, `Content-Length`, and `Accept-Ranges`.

---

# 11. Backward compatibility

- Existing endpoint paths remain valid.
- `playback_url` remains available.
- New `stream_url`, `signature`, `storage`, and `supports_range` fields are additive.
- Course image URLs now use temporary private URLs when database values are local paths.
- Legacy absolute external URLs remain supported and may have a null signature.
- The old `hls_manifest_path` field remains in the database, but the new local streaming response uses the private MP4 `source_path` endpoint.
