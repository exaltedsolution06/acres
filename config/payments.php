<?php

return [
    // razorpay | fake. "fake" simulates a gateway for local development and automated tests; it is refused in production.
    'driver' => env('PAYMENT_DRIVER', 'razorpay'),
    'currency' => 'INR',

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'api_base' => 'https://api.razorpay.com/v1',
    ],

    // Printed on invoices. COMPANY_STATE decides CGST+SGST (same state) versus IGST (other states).
    'company' => [
        'name' => env('COMPANY_NAME', env('APP_NAME', 'Real Estate Marketplace')),
        'address' => env('COMPANY_ADDRESS', ''),
        'gstin' => env('COMPANY_GSTIN', ''),
        'state' => env('COMPANY_STATE'),
        'email' => env('COMPANY_EMAIL', ''),
    ],

    'refund_window_days' => 7,

    'states' => [
        'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh',
        'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha',
        'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
        'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Jammu and Kashmir',
        'Ladakh', 'Lakshadweep', 'Puducherry',
    ],
];
