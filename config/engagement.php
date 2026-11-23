<?php

return [
    // Lead pipeline (PRD 4.4). 'new' and 'closed' are available to every poster; the rest need the lead_management feature.
    'statuses' => [
        'new' => 'New', 'contacted' => 'Contacted', 'interested' => 'Interested', 'site_visit' => 'Site visit scheduled',
        'negotiation' => 'Negotiation', 'converted' => 'Converted', 'not_interested' => 'Not interested', 'closed' => 'Closed',
    ],
    'free_statuses' => ['new', 'closed'],
    'closed_statuses' => ['converted', 'not_interested', 'closed'],

    'requirement_validity_days' => 30,
    'max_active_requirements' => 5,
    'max_saved_searches' => 10,
    'visit_max_days_ahead' => 60,

    // event key => label shown in the admin notification settings
    'events' => [
        'enquiry.received' => 'New enquiry or interest on a listing (to the poster)',
        'visit.requested' => 'Site visit requested or changed',
        'requirement.match' => 'A listing matches a posted requirement (to the requirement owner)',
        'requirement.response' => 'Someone responded to a requirement',
        'search.alert' => 'New listings for a saved search',
        'listing.reviewed' => 'Listing approved or rejected (to the poster)',
        'followup.due' => 'Lead follow-up reminder',
        'payment.success' => 'Payment received (receipt)',
        'refund.update' => 'Refund processed or declined',
        'subscription.expiring' => 'Plan ending soon',
    ],
];
