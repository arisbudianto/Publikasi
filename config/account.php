<?php

return [
    'email_enabled' => env('ACCOUNT_EMAIL_ENABLED', false) && ! in_array(env('MAIL_MAILER', 'log'), ['log', 'array'], true),
];
