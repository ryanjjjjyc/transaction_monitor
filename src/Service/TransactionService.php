<?php

namespace TransactionMonitor\Service;

use PDO;
use TransactionMonitor\Model\Transaction;

class TransactionService
{
    public function __construct(
        private PDO $pdo,
        private BalanceService $balanceService,
    ) {}

    public function createTransaction(int $fromUserId, int $toUserId, float $amount, string $currency = 'EUR'): Transaction
    {
        // Perform transfer
        $this->balanceService->transfer($fromUserId, $toUserId, $amount);

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO transactions (from_user_id, to_user_id, amount, currency, created_at, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$fromUserId, $toUserId, $amount, $currency, $now, 'completed']);
        $id = (int)$this->pdo->lastInsertId();

        // Enqueue for async monitoring (non-blocking)
        $stmt = $this->pdo->prepare(
            'INSERT INTO monitoring_queue (transaction_id, created_at) VALUES (?, ?)'
        );
        $stmt->execute([$id, $now]);

        return $this->getById($id);
    }

    public function getById(int $id): ?Transaction
    {
        $stmt = $this->pdo->prepare('SELECT * FROM transactions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function listByUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            'SELECT * FROM transactions WHERE from_user_id = ? OR to_user_id = ? 
             ORDER BY created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $userId, $perPage, $offset]);
        return array_map([$this, 'hydrate'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function updateStatus(int $id, string $status, ?string $reason = null, ?float $mlScore = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE transactions SET status = ?, reason_flagged = ?, ml_score = ? WHERE id = ?'
        );
        $stmt->execute([$status, $reason, $mlScore, $id]);
    }

    private function hydrate(array $row): Transaction
    {
        return new Transaction(
            (int)$row['id'],
            (int)$row['from_user_id'],
            (int)$row['to_user_id'],
            (float)$row['amount'],
            $row['currency'],
            $row['created_at'],
            $row['status'],
            $row['reason_flagged'],
            $row['ml_score'] !== null ? (float)$row['ml_score'] : null
        );
    }
}