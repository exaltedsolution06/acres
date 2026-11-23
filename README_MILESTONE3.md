# Milestone 3 – Registration, authentication & role dashboards

Overlay on Milestones 1 and 2:

```bash
unzip -o realestate-m3.zip -d .
php artisan migrate          # makes users.email nullable; adds user_profiles and otp_codes
php artisan test
```
Overwrites `routes/web.php` (adds the public/auth/dashboard routes; still loads `admin.php`) and `app/Models/User.php`.

## .env
```
SMS_DRIVER=log               # local dev: OTP is written to storage/logs/laravel.log. Refused in production.
# Production, any HTTP SMS API:
# SMS_DRIVER=http
# SMS_URL=https://provider.example/api/send
# SMS_HEADERS={"authkey":"YOUR_KEY"}
# SMS_TO_FIELD=mobile            # field name your provider expects (the number is sent as 91XXXXXXXXXX)
# SMS_MESSAGE_FIELD=message
# SMS_EXTRA={"sender":"RLTYAP","template_id":"..."}

MAIL_MAILER=smtp  MAIL_HOST=...  MAIL_PORT=587  MAIL_USERNAME=...  MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@example.com
```
India: transactional SMS needs a DLT-registered sender ID and template. The OTP text sent by
`OtpService::send()` must match your approved template, so adjust that one message to match it.

## What you get
- **Registration**: mobile + OTP, or email + password (optional mobile, verified later). The user picks one or
  more roles; the first becomes the active role.
- **Login**: mobile OTP, or email + password (5 attempts / 5 minutes), logout, password reset by email.
  Unknown mobile numbers get the same response as known ones, and no SMS is sent to them.
- **OTP safety**: 6 digits, stored as an HMAC (never plain), 5-minute expiry, 5 wrong guesses lock the code,
  3 sends per mobile per 10 minutes, 15 per IP per hour.
- **One mobile number = one account**: enforced when registering, when adding a number from the profile,
  and by the database unique index (race conditions included).
- **Email verification** via signed links (`verification.verify`), resend throttled.
- **Dashboards** `/dashboard/{owner|tenant|buyer|broker}`: role switcher, current plan and expiry, feature
  allowances from the subscription engine, today's usage and what is left, contact credits, verification badges.
  Usage is per account, so switching roles never resets it.
- **Profile**: name, city, preferred locations, photo (JPG/PNG/WebP, 2 MB, random file name, stored on the
  `uploads` disk under `profiles/`), broker business details, and mobile OTP verification.
- **Suspended accounts** cannot log in, and an existing session ends on the next request.

## Not in this milestone
Google login (needs Laravel Socialite and Google credentials; say if you want it next), owner/broker
document verification (`user_verifications`, with the Property Trust module), and the pages behind the
dashboard's feature list (properties, enquiries, requirements arrive in Milestones 4 and 5).
