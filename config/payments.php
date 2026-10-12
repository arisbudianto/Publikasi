<?php

return [
    // Manual ledger only. Enabling this does not create a gateway or charge a card.
    'enabled' => env('OPTIONAL_PAYMENTS_ENABLED', false),
    'instructions' => env('OPTIONAL_PAYMENT_INSTRUCTIONS', ''),
];
