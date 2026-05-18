<?php
/**
 * Copy this file to config.php and edit the values for your hosting.
 * config.php is what the app actually reads.
 *
 * v2 — adds OAuth (Google / Facebook / GitHub) and WhatsApp settings.
 *      Phone-OTP login is kept as a fallback for back-compat but is no
 *      longer exposed in the UI.
 */

return [
    /* ------------------------------------------------------------------ */
    /*  Site                                                              */
    /* ------------------------------------------------------------------ */

    // Public site URL. Used to build OAuth redirect URIs and absolute links.
    // No trailing slash.
    'site_url' => 'https://institution.bd',

    // Page title / brand tagline shown in the navbar.
    'brand_name' => 'institution.bd',
    'brand_tagline' => 'Free verified subdomains for Bangladeshi institutions',

    /* ------------------------------------------------------------------ */
    /*  Database                                                          */
    /* ------------------------------------------------------------------ */

    // Pick the engine that suits your cPanel host:
    //   'sqlite' — zero-config, single file (api/data/app.db). Great for small / medium deployments.
    //   'mysql'  — recommended once you outgrow SQLite or need shared access from other tools.
    // The credentials below match the MySQL database you provisioned in cPanel.
    'db_driver' => 'mysql',
    'db_sqlite_path' => __DIR__ . '/data/app.db',

    'db_mysql_host' => 'localhost',
    'db_mysql_port' => '3306',
    'db_mysql_name' => 'zgruhjabaz_instituition',
    'db_mysql_user' => 'zgruhjabaz_instituition',
    'db_mysql_pass' => 'zgruhjabaz_instituition',
    'db_mysql_charset' => 'utf8mb4',

    /* ------------------------------------------------------------------ */
    /*  Auth                                                              */
    /* ------------------------------------------------------------------ */

    // CHANGE THIS to a long random string before going live.
    // php -r "echo bin2hex(random_bytes(32));"
    'jwt_secret' => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING_BEFORE_LAUNCH',

    // Phone-OTP demo mode (legacy login path). Kept for back-compat.
    'demo_mode' => true,

    // Admin bootstrap. The first admin is created from this email (preferred,
    // matches the OAuth identity) or phone (legacy OTP fallback). Sign in
    // with the matching Google/Facebook/GitHub account and you'll land in
    // admin straight away.
    'admin_email' => 'admin@institution.bd',
    'admin_phone' => '01700000000',

    /* ------------------------------------------------------------------ */
    /*  OAuth providers                                                    */
    /* ------------------------------------------------------------------ */
    //
    // Each provider's redirect URI is:
    //   <site_url>/api/auth/oauth/<provider>/callback
    //
    // e.g.  https://institution.bd/api/auth/oauth/google/callback
    //       https://institution.bd/api/auth/oauth/facebook/callback
    //       https://institution.bd/api/auth/oauth/github/callback
    //
    // Paste those into each provider's developer console. Leave a provider's
    // client_id blank to disable that button on the login modal — at least
    // one provider must be enabled.

    'oauth' => [
        'google' => [
            // https://console.cloud.google.com/apis/credentials
            'client_id'     => '',
            'client_secret' => '',
        ],
        'facebook' => [
            // https://developers.facebook.com/apps/
            'client_id'     => '',   // App ID
            'client_secret' => '',   // App Secret
        ],
        'github' => [
            // https://github.com/settings/developers
            'client_id'     => '',
            'client_secret' => '',
        ],
    ],

    /* ------------------------------------------------------------------ */
    /*  WhatsApp                                                          */
    /* ------------------------------------------------------------------ */

    'whatsapp' => [
        // Support: tapping the floating button opens a 1-to-1 chat with
        // this number. Use international form WITHOUT +, dashes or spaces.
        // e.g. '8801712345678'
        'support_number' => '8801700000000',
        'support_prefilled_message' => "Hi! I need help with institution.bd",

        // Community: link to a public WhatsApp Community / Group invite.
        // Paste the chat.whatsapp.com or wa.me/... URL here.
        'community_url'  => 'https://chat.whatsapp.com/REPLACE_WITH_INVITE',
        'community_title' => 'Join our WhatsApp community',
        'community_subtitle' => 'Get announcements, support and meet other institution owners.',
    ],

    /* ------------------------------------------------------------------ */
    /*  Brands & uploads                                                  */
    /* ------------------------------------------------------------------ */

    'brands' => ['institution.bd', 'smartschool.bd'],

    'uploads_dir' => __DIR__ . '/../uploads',
    'uploads_public_prefix' => '/uploads',

    // CORS — same-origin deployment can leave this as '*'.
    'cors_allow_origin' => '*',

    /* ------------------------------------------------------------------ */
    /*  Cloudflare auto-DNS (optional)                                    */
    /* ------------------------------------------------------------------ */
    'cloudflare' => [
        'api_token'   => '',
        'zones'       => [
            'institution.bd'  => '',
            'smartschool.bd'  => '',
        ],
        'target_type'  => 'A',
        'target_value' => '',
        'proxied'      => true,
    ],
];
