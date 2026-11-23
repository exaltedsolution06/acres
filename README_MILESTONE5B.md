# Milestone 5B – Payments, invoices, coupons, refunds, credit packs, featured listings

Overlay on Milestones 1–5A:

```bash
unzip -o realestate-m5b.zip -d .
php artisan migrate
php artisan db:seed --class=NotificationSettingSeeder   # three new notification events
php artisan db:seed --class=BillingSeeder               # sample credit packs and promotion packages
php artisan test
```
Overwrites (patched earlier files): `routes/web.php`, `routes/console.php`, `config/engagement.php`, `DatabaseSeeder.php`,
`Property.php`, `PropertySearch.php`, `SubscriptionService.php` (adds `renew`), `ContactCreditService.php` (adds `reverse`),
`HomeController.php`, admin `DashboardController.php`, both layouts, `home`, `property-card`, `listings/index`, admin `dashboard` views.

## .env
```
PAYMENT_DRIVER=razorpay            # "fake" for local development: no real money, a Simulate button replaces Razorpay
RAZORPAY_KEY_ID=rzp_test_xxx
RAZORPAY_KEY_SECRET=xxx
RAZORPAY_WEBHOOK_SECRET=xxx        # the secret you set on the webhook in the Razorpay dashboard

COMPANY_NAME="Your Company Pvt Ltd"
COMPANY_ADDRESS="Street, City, PIN"
COMPANY_GSTIN=22AAAAA0000A1Z5
COMPANY_STATE="West Bengal"        # your GST state: same-state buyers get CGST+SGST, others IGST
COMPANY_EMAIL=billing@example.com
```
Razorpay dashboard: add a webhook to `https://your-domain/webhooks/payments/razorpay` with the events
`payment.captured`, `order.paid`, `payment.failed` and `refund.processed`. The webhook is what activates a purchase
when a customer pays but closes the tab before returning to your site.
`PAYMENT_DRIVER=fake` is refused when `APP_ENV=production`.

**I could not call Razorpay from here.** The gateway class follows the Orders API, Checkout signature, Payments
fetch and capture, Refunds and webhook signature as I know them. Run one test-mode purchase, one failed payment and one
refund against your Razorpay test keys, and check field names against the current Razorpay docs before going live.

## What you get
- **Checkout**: server-priced (the browser never sends an amount), GST added on top, billing name, state and optional
  GSTIN collected, then Razorpay Checkout. The browser result is verified by signature, the payment is fetched from the
  gateway, and the amount, currency and order are compared before anything activates. Fulfilment, invoice and coupon
  redemption happen in one database transaction, so a failure retries cleanly from the webhook. Duplicate callbacks and
  webhooks change nothing.
- **Purchases**: plan subscriptions (same-plan renewals extend the current period, renewal discount applies; a different
  plan replaces it), contact credit packs, and featured-listing promotions (search top and optional homepage, stacking).
  Free trials start from `/pricing` once per plan. A 100% coupon activates without the gateway.
- **Coupons**: percent or fixed, cap, minimum, applies-to, total and per-user limits, start and end dates.
- **Invoices**: GST tax invoices with gapless numbering per financial year (`INV/2026-27/000001`), CGST/SGST or IGST by
  billing state, printable at `/billing`, plus credit notes (`CN/...`) for refunds.
- **Refunds**: customers can request one on plans marked refund-eligible within 7 days; admin approves or declines, or
  refunds any payment directly. Money goes back through the gateway first; then the plan, credits or promotion is taken
  back and a credit note issued. A failed gateway call changes nothing. Credit packs that were partly spent are refused.
- **Admin**: payments list and detail with the gateway log, revenue summary with GST, CSV export for your accountant,
  refund queue, and screens for coupons, credit packs and promotion packages. The admin dashboard revenue now comes from payments.
- Plan-expiry reminders 7 days ahead (daily at 09:00 IST).

## Decisions and limits
- Listed prices exclude GST. Full refunds only; partial refunds, proration on upgrades and automatic recurring
  renewal (card mandates) are not built.
- Promotions can be bought by any owner or broker for their live listings; the `featured_listing` plan feature is not
  used to gate purchases.
- Gateway secrets live in `.env`, never in the database. Cashfree fits the same `PaymentGateway` interface and can be added later.
- Taxes and invoice wording vary; have your accountant review the invoice layout and GST treatment.

## Next (5C)
Owner and broker verification badges with document review, abuse and fraud reports with the admin dashboard, then CMS
(banners, pages, blog, FAQs, SEO settings) and the final hardening and deployment guide.
