<?php

return [
    'property_code' => env('VV_PROPERTY_CODE', 'VV'),
    'currency' => env('VV_CURRENCY', 'LKR'),
    'hold_minutes' => (int) env('VV_HOLD_MINUTES', 15),

    'security' => [
        'login_max_attempts' => (int) env('VV_LOGIN_MAX_ATTEMPTS', 5),
        'login_lock_minutes' => (int) env('VV_LOGIN_LOCK_MINUTES', 15),
        'password_min' => 10,
        'upload_max_kb' => 5120,
    ],

    'payments' => [
        'driver' => env('PAYMENT_DRIVER', 'sandbox'),
        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

    'channel' => [
        'driver' => env('CHANNEL_DRIVER', 'null'),
        'channex' => [
            'api_key' => env('CHANNEX_API_KEY'),
            'property_id' => env('CHANNEX_PROPERTY_ID'),
            'webhook_secret' => env('CHANNEX_WEBHOOK_SECRET'),
            'base_url' => env('CHANNEX_BASE_URL', 'https://staging.channex.io/api/v1'),
        ],
    ],

    'locks' => [
        'driver' => env('LOCK_DRIVER', 'simulator'),
        'bridge_token' => env('LOCK_BRIDGE_TOKEN'),
        'checkout_grace_minutes' => 60,
        // In-app RFID simulator (Access control → Simulator) and simulator-mode devices. Keep false in production.
        'rfid_simulator' => filter_var(env('RFID_SIMULATOR', true), FILTER_VALIDATE_BOOL),
        // Shared doors a valid guest card also opens (in addition to the guest's own villa).
        'guest_zones' => ['Main Gate', 'Lobby', 'Pool', 'Restaurant', 'Gym'],
        // Staff doors opened by an employee RFID badge: zone => department codes ('*' = every active employee).
        'staff_zones' => [
            'Main Gate' => '*', 'Staff Entrance' => '*', 'Back Office' => ['FO', 'ADM'], 'Kitchen' => ['KIT', 'FB'],
            'Stores' => ['STR', 'KIT'], 'Villa Area' => ['HK', 'MNT', 'FO'], 'Laundry' => ['HK'],
        ],
    ],

    'whatsapp' => [
        'number' => env('WHATSAPP_NUMBER', '94764413420'),
        'cloud_token' => env('WHATSAPP_CLOUD_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    ],

    'site' => [
        'maps_query' => env('GOOGLE_MAPS_EMBED_QUERY', 'Vaasal Villa, Jaffna, Sri Lanka'),
        'latitude' => (float) env('VV_LATITUDE', 9.6615),
        'longitude' => (float) env('VV_LONGITUDE', 80.0255),
        'weather_enabled' => filter_var(env('WEATHER_ENABLED', true), FILTER_VALIDATE_BOOL),
        'social' => [
            'facebook' => env('SOCIAL_FACEBOOK'),
            'instagram' => env('SOCIAL_INSTAGRAM'),
            'tiktok' => env('SOCIAL_TIKTOK'),
            'youtube' => env('SOCIAL_YOUTUBE'),
        ],
    ],

    // Business contact defaults. Live values are edited in Admin → Website content (settings table);
    // these are only used before the settings exist. Read them through contact(), never directly.
    'contact' => [
        'location' => 'Jaffna, Sri Lanka',
        'city' => 'Jaffna',
        'country' => 'Sri Lanka',
        'phone' => env('VV_CONTACT_PHONE', '0764413420'),
        'email' => env('VV_CONTACT_EMAIL', 'aflalal2004@gmail.com'),
    ],

    // Display currencies. The database, folios, invoices and payments are always in the base currency (LKR);
    // other currencies are display conversions only. Rates = units of the currency per 1 LKR.
    // Live rates come from EXCHANGE_RATE_API_KEY (exchangerate-api.com v6) cached for `cache_minutes`;
    // an admin override in Settings → Currencies wins over the API; `fallback_rates` are used when neither is available.
    'currency_display' => [
        'base' => 'LKR',
        'cookie' => 'vv_currency',
        'api_key' => env('EXCHANGE_RATE_API_KEY'),
        'api_url' => env('EXCHANGE_RATE_API_URL', 'https://v6.exchangerate-api.com/v6'),
        'cache_minutes' => (int) env('EXCHANGE_RATE_CACHE_MINUTES', 360),
        'currencies' => [
            'LKR' => ['name' => 'Sri Lankan Rupee', 'symbol' => 'Rs', 'decimals' => 0],
            'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2],
            'EUR' => ['name' => 'Euro', 'symbol' => '€', 'decimals' => 2],
            'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'decimals' => 2],
            'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'decimals' => 0],
            'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'decimals' => 2],
            'AED' => ['name' => 'UAE Dirham', 'symbol' => 'AED', 'decimals' => 2],
        ],
        // Conservative reference rates (per 1 LKR) used only when no API key and no admin override are set.
        'fallback_rates' => ['LKR' => 1, 'USD' => 0.00333, 'EUR' => 0.00285, 'GBP' => 0.00248, 'INR' => 0.2860, 'AUD' => 0.00505, 'AED' => 0.01223],
    ],

    // Stock photo / video search (server side only — keys never reach the browser).
    'media' => [
        'unsplash_key' => env('UNSPLASH_ACCESS_KEY'),
        'pexels_key' => env('PEXELS_API_KEY'),
        'cache_minutes' => (int) env('MEDIA_API_CACHE_MINUTES', 1440),
        'timeout' => 6,
    ],

    // Role quick-fill on the sign-in page. Only ever rendered when APP_ENV=local AND this flag is true.
    'demo_login' => filter_var(env('DEMO_LOGIN', true), FILTER_VALIDATE_BOOL),

    // Housekeeping status flow
    'hk_statuses' => ['dirty', 'cleaning', 'inspection', 'clean', 'ready'],

    // Folio departments (all services post to the same guest folio)
    'departments' => [
        'room' => 'Room',
        'restaurant' => 'Restaurant',
        'bar' => 'Bar',
        'room_service' => 'Room Service',
        'laundry' => 'Laundry',
        'spa' => 'Spa',
        'transport' => 'Transport',
        'airport_transfer' => 'Airport Transfer',
        'minibar' => 'Minibar',
        'excursion' => 'Excursion',
        'damage' => 'Damages',
        'misc' => 'Miscellaneous',
    ],
];
