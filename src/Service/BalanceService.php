<?php
declare(strict_types=1);

namespace TransactionMonitor\Service;

use PDO;

class BalanceService
{
    public function __construct(private PDO $pdo) {}

    public function transfer(int $fromUserId, int $toUserId, float $amount): void
    {
        $this->pdo->beginTransaction();
        try {
            // Debit sender
            $stmt = $this->pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?');
            $stmt->execute([$amount, $fromUserId, $amount]);
            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('Insufficient funds');
            }

            // Credit receiver
            $stmt = $this->pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
            $stmt->execute([$amount, $toUserId]);

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}