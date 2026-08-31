<?php

$pdo = new PDO('sqlite:db/database.sqlite');
$pdo->exec("PRAGMA foreign_keys = ON");

// Create tables using schema.sql
$pdo->exec(file_get_contents('schema.sql'));

$pdo->exec("INSERT OR IGNORE INTO users (username, api_key, balance) VALUES
    ('alice', 'key_alice', 50000),
    ('bob',   'key_bob',   2000),
    ('charlie', 'key_charlie', 100000)
");
echo "Seed data inserted.\n";