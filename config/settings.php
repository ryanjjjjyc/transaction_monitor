<?php
return [
    'db' => [
        'dsn' => 'sqlite:' . __DIR__ . '/../db/database.sqlite',
    ],
    'rules' => [
        'large_amount_threshold' => 10000,
        'max_tx_per_minute' => 3,
        'suspicious_hours' => [1, 2, 3, 4, 5],
    ],
    'ml_threshold' => 0.7,
];