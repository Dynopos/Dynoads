<?php

/**
 * Semua setting Meta duduk di sini, bukan bertabur dalam kod.
 *
 * Nombor dalam fail ini datang daripada 150+ campaign DynoPOS sebenar yang
 * menghasilkan kos/lead sekitar RM10–15. Ia bukan tekaan, jadi jangan ubah
 * tanpa data yang menunjukkan sesuatu yang lebih baik.
 */
return [

    'api_version' => env('META_API_VERSION', 'v21.0'),
    'access_token' => env('META_ACCESS_TOKEN'),
    'ad_account_id' => env('META_AD_ACCOUNT_ID'),
    'page_id' => env('META_PAGE_ID'),
    'wa_phone' => env('META_WA_PHONE'),

    'graph_url' => 'https://graph.facebook.com',

    'campaign' => [
        'objective' => 'OUTCOME_ENGAGEMENT',
        'bid_strategy' => 'LOWEST_COST_WITHOUT_CAP',
        'special_ad_categories' => [],
        'status' => 'PAUSED',
    ],

    'adset' => [
        'optimization_goal' => 'CONVERSATIONS',
        'destination_type' => 'WHATSAPP',
        'billing_event' => 'IMPRESSIONS',
        'status' => 'PAUSED',
    ],

    'targeting' => [
        'age_min' => 25,
        'age_max' => 65,
        'locales' => [41],
        'advantage_audience' => 1,
        'location_types' => ['home', 'recent'],
        'brand_safety_content_filter_levels' => ['FACEBOOK_RELAXED', 'AN_RELAXED'],
        'countries' => ['MY'],
        // Sarawak, Labuan, Sabah — dikecualikan kerana kos/lead jauh lebih tinggi
        // dan penghantaran lebih perlahan dalam data DynoPOS.
        'excluded_regions' => [
            ['key' => '2546', 'name' => 'Sarawak'],
            ['key' => '2550', 'name' => 'Labuan'],
            ['key' => '2551', 'name' => 'Sabah'],
        ],
    ],

    'ad' => [
        'call_to_action_type' => 'WHATSAPP_MESSAGE',
        'link' => 'https://api.whatsapp.com/send',
    ],

    'defaults' => [
        'daily_budget_sen' => 3700,
        'name_format' => 'DYNOADS-{setId}-{n}-{date}',
    ],

    'page_posts' => [
        'cache_minutes' => 10,
        'per_page' => 25,
        'min_selection' => 2,
        'max_selection' => 4,
    ],

    /**
     * Ralat Meta yang bermaksud "call_to_action tidak diterima di atas
     * object_story_id". Meta tidak mendokumenkan satu kod tunggal untuk ini,
     * jadi kita padankan gabungan kod/subkod yang diperhatikan dan juga teks
     * mesej sebagai jaring keselamatan.
     */
    'cta_rejection' => [
        'codes' => [100],
        'subcodes' => [1487831, 1885183, 2490408],
        'message_patterns' => [
            'call_to_action',
            'call to action',
            'object_story_id',
            'not supported for this creative',
        ],
    ],
];
