<?php

namespace TransactionMonitor\Service;

use PDO;

class RuleEngine
{
    public function __construct(
        private PDO $pdo,
        private array $rules
    ) {}

    /**
     * @return array List of triggered rule descriptions (empty means clean)
     */
    public function evaluate(int $transactionId): array
    {
        // Fetch transaction
        $stmt = $this->pdo->prepare('SELECT * FROM transactions WHERE id = ?');
        $stmt->execute([$transactionId]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tx) return [];

        $reasons = [];

        // 1. Large amount
        if ((float)$tx['amount'] > $this->rules['large_amount_threshold']) {
            $reasons[] = 'Amount exceeds threshold';
        }

        // 2. Transactions fromt he same user within 60 sec
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM transactions 
             WHERE from_user_id = ? 
               AND created_at > datetime(?, '-60 seconds')"
        );
        $stmt->execute([$tx['from_user_id'], $tx['created_at']]);
        $count = (int)$stmt->fetchColumn();
        if ($count > $this->rules['max_tx_per_minute']) {
            $reasons[] = 'More than ' . $this->rules['max_tx_per_minute'] . ' transactions in a minute';
        }

        // 3. Suspicious hour
        $hour = (int)date('G', strtotime($tx['created_at']));
        if (in_array($hour, $this->rules['suspicious_hours'])) {
            $reasons[] = 'Transaction during suspicious hours';
        }

        return $reasons;
    }
}