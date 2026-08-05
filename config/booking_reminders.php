<?php

return [
    'enabled' => [
        '24h' => env('BOOKING_REMINDER_24H_ENABLED', false),
        '3h' => env('BOOKING_REMINDER_3H_ENABLED', false),
        '1h' => env('BOOKING_REMINDER_1H_ENABLED', true),
    ],
];
