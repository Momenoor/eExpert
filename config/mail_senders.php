<?php

return [
    'senders' => [
        'main' => [
            'name' => env('MAIL_FROM_NAME_MAIN', 'JPAE Main'),
            'address' => env('MAIL_FROM_ADDRESS_MAIN', 'main@jpaemirates.com'),
            'signature' => <<<HTML
                <p>Best regards,<br><strong>JPAE Main Office</strong><br>
                Tel: +971-X-XXXXXXX | www.jpaemirates.com</p>
            HTML,
        ],
        'legal' => [
            'name' => env('MAIL_FROM_NAME_LEGAL', 'JPAE Legal'),
            'address' => env('MAIL_FROM_ADDRESS_LEGAL', 'legal@jpaemirates.com'),
            'signature' => <<<HTML
                <p>Best regards,<br><strong>JPAE Legal Department</strong><br>
                Tel: +971-X-XXXXXXX</p>
            HTML,
        ],
        // Add more senders as needed
    ],
    'default_daily_limit' => 60,
    'retry_attempts' => 3,
    'retry_delay_minutes' => 30,
];
