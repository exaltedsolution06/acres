# Milestone 4 – Properties, search & SEO URLs

Overlay on Milestones 1–3:

```bash
unzip -o realestate-m4.zip -d .
php artisan migrate
php artisan db:seed --class=CatalogSeeder      # categories, amenities, sample cities and localities (safe to re-run)
php artisan marketplace:prepare-uploads
php artisan marketplace:write-robots           # writes public/robots.txt with your APP_URL sitemap
php artisan test
```
Overwrites: `routes/web.php`, `routes/console.php`, `database/seeders/DatabaseSeeder.php`,
`resources/views/layouts/app.blade.php`, `layouts/admin.blade.php`, `home.blade.php`.
Needs the PHP **GD** extension (photo resizing, thumbnails and removal of EXIF/GPS data) and, for
video, `upload_max_filesize` and `post_max_size` of at least 32M in cPanel's PHP settings.

Optional `.env`: `LISTING_VALIDITY_DAYS=60`, `LISTING_MIN_PHOTOS=3`, `LISTING_REAPPROVE_ON_EDIT=false`,
`GUEST_PROPERTY_VIEWS=true`.

## Public site
- **Search** (`/search`, noindex) with city, locality, PIN, keyword, category, price, BHK, area, furnishing,
  age, floor, amenities, posted by, status and tenant preference; six sort orders. "Recommended" ranks by the
  poster's `search_priority` package feature, then newest. Similar and recently viewed listings included.
- **SEO URLs**: `/buy-property-in-kolkata`, `/rent-property-in-salt-lake-kolkata`,
  `/buy-property-in-kolkata/apartment-flat`, `/property/{title}-{id}`. Titles, descriptions, canonical URLs,
  Open Graph, breadcrumbs and RealEstateListing JSON-LD are rendered on the server. A title edit changes the
  slug and the old URL 301-redirects. Filtered, sorted and empty landing pages are `noindex`. Sold, rented
  and expired pages stay up with `noindex` and similar listings.
- **Sitemap** `/sitemap.xml` (cached 1 hour): live properties, plus landing pages with enough listings
  (areas: 1, city + category: 3; see `config/property.php`).
- **Contact reveal** `POST /property/{id}/contact` goes through the Milestone 1 `ContactAccessService`
  (plan rules, daily allowance, credits, rate limited). Phone numbers, full addresses and coordinates never
  appear in page HTML.

## Listings (owner / broker)
- Add the Owner or Broker role yourself, then post at `/listings/create`. The draft gets photos, floor plans
  and an optional video, then is submitted. The plan controls `active_listings`, `photo_limit` and
  `video_upload`; the poster is checked against the plan of the role they post as.
- Lifecycle: draft, pending approval, published, rejected, paused, sold, rented, expired, archived
  (enforced transition table plus a status history). Admin approval publishes immediately. Validity
  is `LISTING_VALIDITY_DAYS`, separate from subscriptions. Renewal opens 15 days before expiry or after it.
- Duplicates: the same user cannot have two live listings with the same details and address; the same
  details from different users are flagged for the moderator.

## Admin
Moderation queue with duplicate flags, approve, reject or take down with a reason (owner sees it), history,
audit log. Catalogue screens for categories, amenities and locations (deactivate, never delete).

## Decisions to know about
- **Guests can open property pages** (needed for SEO), so the `daily_property_views` limit applies to logged-in
  users only; a user could log out to browse. Contact numbers, the real revenue item, are always gated.
  Set `GUEST_PROPERTY_VIEWS=false` to require login.
- Over their daily view limit, a logged-in user sees an upgrade notice with no listing data, including meta tags.
- Category and location names are fixed after creation so URLs stay stable.
- Map search, saved searches, comparison, reports, interests and enquiries are in the next milestones.
  Latitude and longitude are already stored.
