# Milestone 5A – Interests, enquiries, requirements, saved items, broker CRM, notifications

Overlay on Milestones 1–4:

```bash
unzip -o realestate-m5a.zip -d .
php artisan migrate                              # 10 tables incl. Laravel's notifications table
php artisan db:seed --class=NotificationSettingSeeder
php artisan test
```
Overwrites (M4 files, patched): `routes/web.php`, `routes/console.php`, `DatabaseSeeder.php`, `PropertySearch.php`,
`PropertyService.php`, `SearchController.php`, `PropertyController.php`, `PropertyModerationController.php`,
`public/property.blade.php`, `public/search.blade.php`, both layouts.

Needs: `QUEUE_CONNECTION=database` with the cron entry from M3 (notification emails are queued), and
working `MAIL_*` settings. In-app notifications work without email.

## What you get
- **Show interest** (costs `daily_interest`, once per property) and **send enquiry** (costs `daily_enquiries`,
  once per property). Both feed one lead per visitor and property. Interest unlocks the `after_interest`
  contact mode from the subscription engine.
- **Visitor side** (`/enquiries`): status (Sent / In progress / Closed), close an enquiry, request a site visit,
  cancel visits.
- **Poster side** (`/leads`): every enquiry on your listings, filter by stage, follow-ups due. All plans can
  view, close and reveal the visitor's number (under their own contact allowance). The `lead_management`
  feature unlocks the full pipeline (New, Contacted, Interested, Site visit scheduled, Negotiation,
  Converted, Not interested, Closed), private notes and follow-up reminders. Posters confirm, decline or
  complete visits, or propose one. The `broker_crm` feature unlocks `/leads/performance` (stages, conversion
  rate, most enquired listings).
- **Requirements marketplace**: tenants and buyers post requirements (plan feature `requirement_posting`,
  5 active at a time, 30 days). New listings are matched to waiting requirements and new requirements to
  existing listings (same purpose, city, and any locality, type, BHK, furnishing and budget given), with a
  notification each time. Owners and brokers with `requirement_posting` browse requirements and respond
  (costs `daily_enquiries`), optionally offering one of their live listings; each side can then reveal the
  other's number under their own plan.
- **Saved properties** (limit = the plan's `saved_properties`) and **saved searches** with a daily alert
  (08:00 IST) for listings published since the last check.
- **Notifications**: in-app inbox with unread badge, and email. Admin can switch every event on or off per
  channel at `/admin/notification-settings`. Events: new enquiry, visit changes, requirement match,
  requirement response, saved-search alert, listing approved or rejected, follow-up due.
- Scheduler additions: search alerts daily, follow-up reminders every 15 minutes.

## Decisions to know about
- The PRD's `interests` and `enquiries` tables are merged into one `enquiries` row per visitor and
  property (interest time, message, pipeline status), which keeps the lead list free of duplicates.
- Browsing and answering requirements reuses `requirement_posting`, so one plan switch controls both sides.
  Saved searches reuse `saved_properties` (a plan with 0 saved properties cannot save searches).
- Notification text is fixed for now; editable templates, SMS and WhatsApp channels come later.
- The seed packages decide who gets what: free owners and brokers can view and close leads but not run
  the pipeline; Basic and above can. Adjust in the admin Packages screen.

## Not in this part
Payments, invoices, coupons and refunds, owner/broker verification badges, and abuse/fraud reports
(5B). Team lead assignment, CSV bulk import and property comparison come after that.
