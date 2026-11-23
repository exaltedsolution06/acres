# Real Estate Marketplace – Milestone 1 (core architecture)

This is an **overlay** for a fresh Laravel 12 project. Drop it over a new install; it adds the
roles, subscription engine, permission layer, usage tracking, contact access and tests.

## Install (fresh)
```bash
composer create-project laravel/laravel realestate_app "^12.0"
cd realestate_app
unzip -o ../realestate-m1.zip -d .        # overlay; overwrites User.php, filesystems.php, routes/console.php, DatabaseSeeder.php
```
Add to `.env`:
```
APP_NAME="Real Estate Marketplace"
APP_URL=https://example.com
ASSET_URL=https://example.com
UPLOADS_PATH=/home/account/public_html/uploads   # local dev: leave unset (uses public/uploads)
MARKETPLACE_TIMEZONE=Asia/Kolkata
QUEUE_CONNECTION=database
```
```bash
php artisan migrate
php artisan db:seed
php artisan marketplace:prepare-uploads
php artisan test
```

## Later deployments
`php artisan migrate --force && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache`
Seeders are only for the first install. Never run migrations on web requests.

## cPanel layout (document root = public_html)
```
/home/account/realestate_app/   <- whole Laravel project (private)
/home/account/public_html/      <- contents of Laravel's public/ + deploy/public_html/index.php
```
Replace `public_html/index.php` with `deploy/public_html/index.php` (it calls `usePublicPath`, so
`asset()` and uploads resolve to public_html). Cron (single entry):
`* * * * * php /home/account/realestate_app/artisan schedule:run >> /dev/null 2>&1`

## How to use the engine
```php
$perm->can($user, 'broker_crm');                       // boolean features
$perm->limit($user, 'active_listings');                // null = unlimited, 0 = none
$usage->consume($user, 'daily_interest');              // throws LimitExceededException
$usage->consume($user, 'daily_property_views', null, 'property', $id, uniqueToday: true);
$contacts->reveal($viewer, $ownerUser, $propertyId, hasInterest: $bool, ip: request()->ip());
$subs->subscribe($user, $package, $verifiedPaymentId); // only after server-side payment verification
Route::middleware([\App\Http\Middleware\RequireFeature::class.':map_search']);
```

## Design notes
- Limits and counters are per **account**; the active role only decides which package applies.
- Contact reveal: daily allowance first, then purchased credits. `contact_unique_counting` makes repeats free.
- Daily summary rows are locked (`lockForUpdate`) inside a transaction, so concurrent requests cannot both pass the limit.
- Private owner-verification documents use the `private_documents` disk (storage/), not `uploads`.
- Not in this milestone: user_profiles, OTP login, admin UI, properties, payments (Milestones 2–5).
