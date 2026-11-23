# Milestone 2 – Admin panel & package management

Overlay on top of Milestone 1. Unzip over the same Laravel project:

```bash
unzip -o realestate-m2.zip -d .
php artisan migrate                       # adds admins + admin_audit_logs
php artisan marketplace:create-admin you@example.com --name="Your Name"   # prompts for password
php artisan test
```
Then open `/admin/login`. Overwrites: `config/auth.php` (adds the `admin` guard), `routes/web.php`
(adds `require admin.php`), `app/Services/RoleService.php` (adds `revoke`).

## What you get
- **Separate admin accounts** (`admins` table, own guard). `super_admin` manages packages and features;
  `staff` can manage users and view the audit log. Login is rate limited (5 attempts / 5 min).
- **Dashboard**: users by role, registrations, active/expired subscriptions, revenue from purchased subscriptions.
- **Packages**: create, edit, duplicate (copy starts inactive), enable/disable, delete (soft). Every PRD field,
  plus a limit/boolean/enum value for every registered feature. A category's default free package cannot be
  deleted or disabled, and packages with active subscribers cannot be deleted.
- **Feature registry**: add new feature keys without code changes; they show in every package form at once.
  Key and type are locked after creation. A key is enforced only when code calls `PermissionService` with it.
- **Users**: search/filter, suspend/restrict, add/remove roles, grant a subscription, grant bonus allowance,
  reset today's usage, add contact credits, view plan, usage and subscription history.
- **Audit log**: every change above is recorded with admin, IP and old/new values.

## Not in this milestone
Admin management screen (create staff via `marketplace:create-admin --staff`), property/enquiry/payment dashboard
stats, coupons and promotions (arrive with their modules). Bootstrap loads from the jsDelivr CDN.
