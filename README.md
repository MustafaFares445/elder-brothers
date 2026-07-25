# Elder Brothers API

Laravel backend for the Elder Brother Flutter learning application.

## Implemented scope

- Sanctum mobile authentication
- Phone registration and OTP verification
- Login, logout, password reset
- User profile, preferences, avatar endpoint, and devices
- Academic years, subjects, courses, videos, and files
- Featured home feed and catalog search
- QR preview and transactional redemption
- Active, expired, and revoked subscriptions
- Protected playback/download URL issuance
- Video progress and course progress
- Database notifications and support requests
- Arabic/English API localization
- Realistic Arabic and English seed data
- Feature tests for core flows

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

The default seeded password is:

```text
Password123!
```

Development OTP delivery uses the log driver. The latest code is also written to the application log.

## Seeded accounts

- `+963900000001` — active verified student
- `+963900000002` — active verified student
- `+963900000003` — suspended student
- `+963900000004` — unverified student

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
