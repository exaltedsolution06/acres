# Milestone 5C – Verification badges and abuse reports

Overlay on Milestones 1–5B:

```bash
unzip -o realestate-m5c.zip -d .
php artisan migrate
php artisan db:seed --class=NotificationSettingSeeder     # two new notification events
php artisan test
```
Overwrites (patched earlier files): `User.php`, `Property.php`, `PropertySearch.php`, `PropertyController.php`,
admin `DashboardController.php`, `routes/web.php`, `routes/console.php`, `config/engagement.php`, the search and property
pages, `property-card`, both layouts, the admin dashboard view, and `tests/Concerns/MakesProperties.php`
(the admin helper no longer fails if called twice).

Optional `.env`: `VERIFICATION_VALIDITY_DAYS=365`, `VERIFICATION_DOCUMENT_RETENTION_DAYS=90`.
Wording, limits and document lists are in `config/trust.php`.

## Verification (`/verification`)
- **Verified owner**: government photo ID (required), address proof, optional ownership document.
- **Verified broker**: photo ID and business registration or RERA certificate (both required).
- **Documents reviewed** (per listing): sale deed or ownership document (required), utility or tax bill, other.
- Badge text states what was checked, for example "Ownership papers for this property were reviewed by our team. This is not a legal
  title verification." Nothing implies legal title checking. Mobile verified and Email verified come from the existing
  OTP and email checks. "Premium advertiser" appears only for customers with a paid plan (admin-granted plans do not
  count) and says it does not mean the listing was checked.
- Documents (PDF, JPG, PNG, 5 MB each) are stored on the **private** disk (`storage/app/private/documents`) under random
  names, never under `public/uploads`. They download as attachments with `nosniff`, only for the applicant or an admin
  (admin views are written to the audit log). Files are deleted 90 days after the decision; pending requests keep theirs.
- Admin queue at `/admin/verifications` (staff and super admins): approve (badge valid 365 days), reject with a reason,
  or remove an approved badge with a reason. Lapsed badges stop showing on their end date; a daily job marks them expired.
- **Search**: "Verified listings only" shows listings with reviewed documents, or whose poster is verified for the role
  they posted as (an owner badge does not make a broker-posted listing "verified"). Cards and listing pages show the specific badge.

## Reports and the fraud dashboard
- Logged-in users report a live listing: fake property, wrong price, duplicate, incorrect phone number, suspicious user,
  misleading photographs, or something else (needs details). One open report per user and listing, 10 a day.
- `/admin/reports`: filter by status and reason, open-report counts per listing, and a banner for posters with 3 or more
  reports in 30 days. For each report: dismiss, or record action with optional **take the listing down** and
  **suspend the poster**. Taking action closes all open reports on that listing together. Reporters get a neutral
  "we reviewed it" message and the poster is never told who reported. Nothing is automatic, so mass reporting cannot hide a
  listing by itself.
- Admin dashboard and sidebar show waiting verifications and open reports.

## Scheduler
`marketplace:maintain-verifications` runs daily at 03:00 IST (expire lapsed badges, purge old documents).

## Not in this part
CMS (homepage content, banners, static pages, blog, FAQs, testimonials, SEO settings, footer), then the final hardening
pass and deployment guide. Verification review is manual: a human has to judge the documents. Consider a short
written review checklist for your team and a privacy notice describing the retention period.
