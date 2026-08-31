<?php

namespace TransactionMonitor\Service;

use PDO;

class FeatureExtractor
{
    public function __construct(private PDO $pdo) {}

    public function extractAndStore(int $transactionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM transactions WHERE id = ?');
        $stmt->execute([$transactionId]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tx) throw new \RuntimeException('Transaction not found');

        $amount = (float)$tx['amount'];
        $hour = (int)date('G', strtotime($tx['created_at']));
        $dayOfWeek = (int)date('N', strtotime($tx['created_at'])); // 1 (Monday) to 7
        $fromUserId = (int)$tx['from_user_id'];
        $toUserId = (int)$tx['to_user_id'];

        // Sender avg amount last 7 days
        $stmt = $this->pdo->prepare(
            "SELECT AVG(amount) FROM transactions 
             WHERE from_user_id = ? 
               AND created_at > datetime(?, '-7 days')"
        );
        $stmt->execute([$fromUserId, $tx['created_at']]);
        $senderAvg = (float)($stmt->fetchColumn() ?: 0);

        // Sender transaction count last 1 hour
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM transactions 
             WHERE from_user_id = ? 
               AND created_at > datetime(?, '-1 hours')"
        );
        $stmt->execute([$fromUserId, $tx['created_at']]);
        $senderCount1h = (int)$stmt->fetchColumn();

        // Receiver avg amount last 7 days
        $stmt = $this->pdo->prepare(
            "SELECT AVG(amount) FROM transactions 
             WHERE to_user_id = ? 
               AND created_at > datetime(?, '-7 days')"
        );
        $stmt->execute([$toUserId, $tx['created_at']]);
        $receiverAvg = (float)($stmt->fetchColumn() ?: 0);

        $amountRatio = $senderAvg > 0 ? $amount / $senderAvg : 0;

        $features = [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'hour_of_day' => $hour,
            'day_of_week' => $dayOfWeek,
            'sender_avg_amount_7d' => $senderAvg,
            'sender_tx_count_1h' => $senderCount1h,
            'receiver_avg_amount_7d' => $receiverAvg,
            'amount_ratio_to_avg' => $amountRatio,
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO ml_features (transaction_id, amount, hour_of_day, day_of_week, sender_avg_amount_7d, 
             sender_tx_count_1h, receiver_avg_amount_7d, amount_ratio_to_avg)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array_values($features));

        return $features;
    }
}