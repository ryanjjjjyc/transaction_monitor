<?php
declare(strict_types=1);

namespace TransactionMonitor\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TransactionMonitor\Service\TransactionService;
use TransactionMonitor\Service\BalanceService;
use PDO;

class TransactionServiceTest extends TestCase
{
    private PDO $pdo;
    private TransactionService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("PRAGMA foreign_keys = ON");

        // Create tables exactly as in schema.sql
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                username TEXT NOT NULL UNIQUE,
                api_key TEXT NOT NULL UNIQUE,
                balance DECIMAL(10,2) DEFAULT 0
            );
            CREATE TABLE transactions (
                id INTEGER PRIMARY KEY,
                from_user_id INTEGER NOT NULL,
                to_user_id INTEGER NOT NULL,
                amount DECIMAL(10,2) DEFAULT 0,
                currency TEXT DEFAULT 'EUR',
                created_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'completed',
                reason_flagged TEXT,
                ml_score REAL,
                FOREIGN KEY (from_user_id) REFERENCES users(id),
                FOREIGN KEY (to_user_id) REFERENCES users(id)
            );
            CREATE TABLE monitoring_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                transaction_id INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                FOREIGN KEY (transaction_id) REFERENCES transactions(id)
            );
        ");

        // Seed two test users
        $this->pdo->exec("
            INSERT INTO users (username, api_key, balance) VALUES
                ('alice', 'key_alice', 5000),
                ('bob',   'key_bob',   2000)
        ");

        $balanceService = new BalanceService($this->pdo);
        $this->service = new TransactionService($this->pdo, $balanceService);
    }

    /** @test */
    public function create_transaction_updates_balances_and_returns_transaction(): void
    {
        $tx = $this->service->createTransaction(1, 2, 500, 'EUR');

        $this->assertEquals(1, $tx->fromUserId);
        $this->assertEquals(2, $tx->toUserId);
        $this->assertEquals(500, $tx->amount);
        $this->assertEquals('EUR', $tx->currency);
        $this->assertEquals('completed', $tx->status);

        // Verify balances
        $stmt = $this->pdo->query("SELECT balance FROM users WHERE id = 1");
        $this->assertEquals(4500.0, (float) $stmt->fetchColumn());

        $stmt = $this->pdo->query("SELECT balance FROM users WHERE id = 2");
        $this->assertEquals(2500.0, (float) $stmt->fetchColumn());

        // Verify monitoring queue entry
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM monitoring_queue WHERE transaction_id = {$tx->id} AND status = 'pending'");
        $this->assertEquals(1, (int) $stmt->fetchColumn());
    }

    /** @test */
    public function insufficient_funds_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient funds');

        $this->service->createTransaction(2, 1, 3000); // Bob only has 2000
    }
}