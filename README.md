# Elder Brothers API

Laravel backend for the Elder Brother Flutter learning application, including an Arabic RTL Filament v5 administration panel.

## Implemented scope

- Sanctum mobile authentication
- Phone registration with administrator account activation
- Login, logout, and OTP-based password reset
- User profile, preferences, avatar endpoint, and devices
- Academic years, subjects, courses, paid videos, and course PDFs
- Featured home feed and Arabic catalog search
- Single-use QR subscriptions with administrator-defined expiration
- Active, expired, and revoked subscriptions
- Protected private-local playback and download URLs
- Range-based video streaming
- Video progress synchronization for the mobile application
- Arabic API content with compatibility fallbacks
- Arabic RTL Filament v5 dashboard

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan test
php artisan serve
```

The default seeder creates the dashboard administrator and a small real-media course. The media seeder writes valid MP4 and PDF binary files into the private course-media disk instead of storing fake URLs.

## Account activation flow

New mobile registrations are created with:

```text
status = inactive
```

No registration OTP or phone-verification step is required. The user cannot log in until an administrator enables **الحساب فعال** from the Students page in Filament. Disabling the toggle revokes the user's active API tokens.

Password-reset OTP remains available through the forgot-password flow.

## Create a dashboard administrator

Run interactively:

```bash
php artisan admin:create
```

Or provide the values directly:

```bash
php artisan admin:create \
  --name="مدير النظام" \
  --email="admin@example.com" \
  --phone="+963900000000" \
  --password="StrongPassword123!"
```

The dashboard is available at:

```text
/admin
```

Only active users with `is_admin=true` can access the panel.

## Real course media seed

Run the media seeder without rebuilding the database:

```bash
php artisan db:seed --class=RealCourseMediaSeeder
```

It creates:

- One published Arabic course for media testing.
- Two valid private MP4 files.
- Two valid private PDF files.
- An active student with an active QR-source subscription.
- Repairs for existing course video or PDF records whose private files are missing.

Student credentials:

```text
Phone: +963900000100
Password: Password123!
```

The files are stored under:

```text
storage/app/private/courses/{course_id}/videos
storage/app/private/courses/{course_id}/pdfs
```

## Course and subscription rules

- Course slugs are generated automatically by the backend.
- Dashboard content is entered in Arabic only.
- All course videos are paid and require an active subscription.
- Videos and PDFs are always downloadable after authorization.
- Subscriptions are activated only through QR codes.
- Every QR code is single-use.
- The administrator selects the QR expiration date; the default is two days after creation.
- The full QR code is stored encrypted, can be copied, and can be displayed as a scannable QR barcode from the dashboard.

## API

All mobile routes are versioned under:

```text
/api/v1
```

Health check:

```text
GET /api/health
```

See [`docs/API_CONTRACT.md`](docs/API_CONTRACT.md) and
[`docs/PRIVATE_MEDIA_STREAMING_API_CONTRACT.md`](docs/PRIVATE_MEDIA_STREAMING_API_CONTRACT.md).

## Production deployment

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=RealCourseMediaSeeder --force
php artisan storage:link
php artisan optimize
php artisan filament:optimize
php artisan queue:restart
```

The queue worker and scheduler must remain active for background operations and subscription expiration.