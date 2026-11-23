<?php

return [
    'validity_days' => (int) env('VERIFICATION_VALIDITY_DAYS', 365),
    'renew_window_days' => 30,           // may re-apply this long before a verification ends
    'document_retention_days' => (int) env('VERIFICATION_DOCUMENT_RETENTION_DAYS', 90), // files are deleted this long after a decision
    'max_file_kb' => 5120,

    // Badge wording says exactly what was checked. None of these is a legal title verification.
    'types' => [
        'owner' => [
            'label' => 'Verified owner',
            'role' => 'owner',
            'explain' => 'Identity and address documents of this owner were reviewed by our team.',
            'documents' => ['id_proof' => 'Government photo ID', 'address_proof' => 'Address proof', 'ownership_document' => 'Ownership document (optional)'],
            'required' => ['id_proof'],
        ],
        'broker' => [
            'label' => 'Verified broker',
            'role' => 'broker',
            'explain' => 'Identity and business or RERA registration documents of this broker were reviewed by our team.',
            'documents' => ['id_proof' => 'Government photo ID', 'business_registration' => 'Business registration or RERA certificate', 'address_proof' => 'Office address proof (optional)'],
            'required' => ['id_proof', 'business_registration'],
        ],
        'property_documents' => [
            'label' => 'Documents reviewed',
            'role' => null,
            'explain' => 'Ownership papers for this property were reviewed by our team. This is not a legal title verification.',
            'documents' => ['ownership_document' => 'Sale deed or ownership document', 'utility_bill' => 'Recent utility or property tax bill', 'other' => 'Other supporting document (optional)'],
            'required' => ['ownership_document'],
        ],
    ],

    'report_reasons' => [
        'fake_property' => 'Fake or non-existent property',
        'wrong_price' => 'Wrong price',
        'duplicate' => 'Duplicate listing',
        'incorrect_phone' => 'Incorrect phone number',
        'suspicious_user' => 'Suspicious user',
        'misleading_photos' => 'Misleading photographs',
        'other' => 'Something else',
    ],
    'reports_per_user_per_day' => 10,
    'repeat_offender_threshold' => 3, // reports across a poster's listings in 30 days
];
