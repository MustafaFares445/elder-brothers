# Elder Brothers API

Laravel backend for the Elder Brother Flutter learning application, including an Arabic-first Filament v5 administration panel.

## Implemented scope

- Sanctum mobile authentication
- Phone registration and OTP verification
- Login, logout, and password reset
- User profile, preferences, avatar endpoint, and devices
- Academic years, subjects, courses, videos, and files
- Featured home feed and catalog search
- QR preview and transactional redemption
- Active, expired, and revoked subscriptions
- Protected playback/download URL issuance
- Video progress and course progress
- Database notifications and support requests
- Arabic/English API localization
- Arabic RTL Filament v5 dashboard
- Student, subscription, QR, content, support, notification, and system management
- Dashboard KPIs, charts, shared filters, and audit resources
- Realistic Arabic and English seed data
- Feature tests for core API and dashboard rules

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DashboardSeeder
php artisan storage:link
php artisan test
php artisan serve
```

## Dashboard administrator

### Create an administrator with Artisan

Interactive mode:

```bash
php artisan admin:create
```

Non-interactive production mode:

```bash
php artisan admin:create \
  --name="مدير النظام" \
  --email="admin@example.com" \
  --phone="+963900000000" \
  --password="use-a-strong-password"
```

To promote or update an existing user with the same phone or email:

```bash
php artisan admin:create \
  --name="مدير النظام" \
  --email="admin@example.com" \
  --phone="+963900000000" \
  --password="use-a-strong-password" \
  --force
```

When `--password` is omitted in non-interactive mode, the command generates a strong password and displays it once.

### Create an administrator from environment variables

Set these variables before running `DashboardSeeder`:

```env
ADMIN_NAME="System Administrator"
ADMIN_EMAIL="admin@example.com"
ADMIN_PHONE="+963900000000"
ADMIN_PASSWORD="use-a-strong-password"
```

Then create or update the administrator:

```bash
php artisan db:seed --class=AdminUserSeeder
```

The dashboard is available at:

```text
/admin
```

Only active users with `is_admin=true` can access the panel. The panel uses Arabic and RTL by default.

## Seeded student accounts

The default development password is:

```text
Password123!
```

- `+963900000001` — active verified student
- `+963900000002` — active verified student
- `+963900000003` — suspended student
- `+963900000004` — unverified student

Development OTP delivery uses the log driver. The latest code is also written to the application log.

## API

All mobile routes are versioned under:

```text
/api/v1
```

Health check:

```text
GET /api/health
```

See [`docs/API_CONTRACT.md`](docs/API_CONTRACT.md) for endpoint props and response examples.

## Production deployment

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=DashboardSeeder --force
php artisan storage:link
php artisan optimize
php artisan filament:optimize
php artisan queue:restart
```

The queue worker and scheduler must remain active for notification campaigns and subscription expiration.
