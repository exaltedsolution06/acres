<?php

return [
    'purposes' => ['sell' => 'Sell', 'rent' => 'Rent', 'lease' => 'Lease', 'pg' => 'PG / Co-living'],

    // URL verb => purpose, e.g. /buy-property-in-kolkata
    'url_verbs' => ['buy' => 'sell', 'rent' => 'rent', 'lease' => 'lease', 'pg' => 'pg'],

    'ownership' => ['freehold' => 'Freehold', 'leasehold' => 'Leasehold', 'co_operative' => 'Co-operative society', 'power_of_attorney' => 'Power of attorney'],
    'availability' => ['ready_to_move' => 'Ready to move', 'under_construction' => 'Under construction'],
    'tenant_preference' => ['any' => 'Anyone', 'family' => 'Family', 'bachelor' => 'Bachelor', 'company' => 'Company'],
    'furnishing' => ['unfurnished' => 'Unfurnished', 'semi_furnished' => 'Semi-furnished', 'furnished' => 'Furnished'],
    'facing' => ['north' => 'North', 'south' => 'South', 'east' => 'East', 'west' => 'West', 'north_east' => 'North-East', 'north_west' => 'North-West', 'south_east' => 'South-East', 'south_west' => 'South-West'],
    'parking' => ['none' => 'None', 'open' => 'Open', 'covered' => 'Covered', 'both' => 'Open and covered'],
    'power_backup' => ['none' => 'None', 'partial' => 'Partial', 'full' => 'Full'],

    'sort' => [
        'recommended' => 'Recommended',
        'latest' => 'Latest',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'most_viewed' => 'Most viewed',
        'updated' => 'Recently updated',
    ],

    'page_size' => 12,

    // Listing rules (move into admin-editable settings later)
    'listing_validity_days' => (int) env('LISTING_VALIDITY_DAYS', 60),
    'renew_window_days' => 15,
    'max_drafts' => 20,
    'min_photos' => (int) env('LISTING_MIN_PHOTOS', 3),
    'reapprove_on_edit' => (bool) env('LISTING_REAPPROVE_ON_EDIT', false),

    // Guests can open property pages (needed for SEO). Logged-in users consume daily_property_views.
    'guest_property_views' => (bool) env('GUEST_PROPERTY_VIEWS', true),

    // Thin-page protection for the sitemap
    'min_listings_area_landing' => 1,
    'min_listings_category_landing' => 3,

    'photo_max_kb' => 5120,
    'video_max_kb' => 30720,
    'floor_plan_limit' => 3,
];
