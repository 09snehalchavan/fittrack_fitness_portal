<?php
/**
 * Site configuration.
 * Edit the values below before deploying. This folder is blocked from
 * direct web access by includes/.htaccess (Apache).
 */

return [
    // Where contact-form messages are delivered
    'mail_to'   => 'chavansanskar19@gmail.com',

    // The "From" address must belong to YOUR domain (e.g. no-reply@yourdomain.com)
    // so that mail() is not rejected or sent to spam. Change this after you choose a domain.
    'mail_from' => 'no-reply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost'),

    'site_name' => 'Sanskar Kuber Chavan — Portfolio',

    // Optional MySQL storage of messages (a backup in addition to the email).
    // Set 'enabled' => true and import database/database.sql to use it.
    'db' => [
        'enabled' => false,
        'host'    => 'localhost',
        'name'    => 'portfolio_db',
        'user'    => 'db_user',
        'pass'    => 'db_password',
        'charset' => 'utf8mb4',
    ],

    // Spam protection
    'min_fill_seconds' => 3,    // forms submitted faster than this are treated as bots
    'rate_limit_max'   => 3,    // max successful submissions ...
    'rate_limit_window'=> 600,  // ... per this many seconds, per visitor session and IP
];
