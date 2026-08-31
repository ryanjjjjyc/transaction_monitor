<?php
declare(strict_types=1);

namespace TransactionMonitor\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TransactionMonitor\Service\RuleEngine;
use PDO;

class RuleEngineTest extends TestCase
{
    private PDO $pdo;
    private array $rules;

    protected function setUp(): void
    {
        // In‑memory SQLite database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("PRAGMA foreign_keys = ON");

        // Create transactions table
        $this->pdo->exec("
            CREATE TABLE transactions (
                id INTEGER PRIMARY KEY,
                from_user_id INTEGER NOT NULL,
                to_user_id INTEGER NOT NULL,
                amount DECIMAL(10,2) DEFAULT 0,
                currency TEXT DEFAULT 'EUR',
                created_at TEXT NOT NULL,
                status TEXT DEFAULT 'completed',
                reason_flagged TEXT,
                ml_score REAL
            )
        ");

        // Settings
        $this->rules = [
            'large_amount_threshold' => 10000,
            'max_tx_per_minute' => 3,
            'suspicious_hours' => [1, 2, 3, 4, 5],
        ];
    }

    /** @test */
    public function large_amount_triggers_rule(): void
    {
        $engine = new RuleEngine($this->pdo, $this->rules);

        // Insert a transaction with amount > 10000
        $this->pdo->exec("
            INSERT INTO transactions (from_user_id, to_user_id, amount, created_at)
            VALUES (1, 2, 12000, '2026-07-12 14:00:00')
        ");
        $txId = (int) $this->pdo->lastInsertId();

        $reasons = $engine->evaluate($txId);
        $this->assertContains('Amount exceeds threshold', $reasons);
    }

    /** @test */
    public function normal_amount_does_not_trigger(): void
    {
        $engine = new RuleEngine($this->pdo, $this->rules);

        $this->pdo->exec("
            INSERT INTO transactions (from_user_id, to_user_id, amount, created_at)
            VALUES (1, 2, 500, '2026-07-12 14:00:00')
        ");
        $txId = (int) $this->pdo->lastInsertId();

        $reasons = $engine->evaluate($txId);
        $this->assertNotContains('Amount exceeds threshold', $reasons);
    }

    /** @test */
    public function rapid_transactions_trigger_rule(): void
    {
        $engine = new RuleEngine($this->pdo, $this->rules);
        $baseTime = '2026-07-12 14:00:00';

        // Simulate 4 rapid transactions within 60 seconds from the same user
        for ($i = 0; $i < 4; $i++) {
            $time = date('Y-m-d H:i:s', strtotime($baseTime) + $i * 10);
            $this->pdo->exec("
                INSERT INTO transactions (from_user_id, to_user_id, amount, created_at)
                VALUES (1, 2, 100, '$time')
            ");
        }

        // Check the last transaction (the one that should trigger)
        $txId = (int) $this->pdo->lastInsertId();
        $reasons = $engine->evaluate($txId);
        $this->assertContains('More than 3 transactions in a minute', $reasons);
    }

    /** @test */
    public function suspicious_hour_triggers_rule(): void
    {
        $engine = new RuleEngine($this->pdo, $this->rules);

        // Insert a transaction at 3:00 AM (suspicious)
        $this->pdo->exec("
            INSERT INTO transactions (from_user_id, to_user_id, amount, created_at)
            VALUES (1, 2, 200, '2026-07-12 03:30:00')
        ");
        $txId = (int) $this->pdo->lastInsertId();

        $reasons = $engine->evaluate($txId);
        $this->assertContains('Transaction during suspicious hours', $reasons);
    }
}