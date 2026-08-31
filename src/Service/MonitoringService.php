<?php

namespace TransactionMonitor\Service;

use PDO;

class MonitoringService
{
    public function __construct(
        private PDO $pdo,
        private RuleEngine $ruleEngine,
        private FeatureExtractor $featureExtractor,
        private MockModel $model,
        private TransactionService $transactionService,
        private float $mlThreshold
    ) {}

    /**
     * Process a single transaction: rules + ML --> update if suspicious
     */
    public function processTransaction(int $transactionId): void
    {
        // Extract features and store for future training
        $features = $this->featureExtractor->extractAndStore($transactionId);

        // Rule-based checks
        $ruleReasons = $this->ruleEngine->evaluate($transactionId);

        // ML prediction
        $mlScore = $this->model->predict($features);
        $mlFlagged = $mlScore >= $this->mlThreshold;

        $finalReason = null;
        if (!empty($ruleReasons)) {
            $finalReason = 'Rules: ' . implode('; ', $ruleReasons);
        }
        if ($mlFlagged) {
            $finalReason = ($finalReason ? $finalReason . '; ' : '') . 'ML model flagged (score: ' . round($mlScore, 3) . ')';
        }

        if ($finalReason) {
            $this->transactionService->updateStatus($transactionId, 'flagged', $finalReason, $mlScore);
        } else {
            // Still store ML score for audit even if not flagged
            $stmt = $this->pdo->prepare('UPDATE transactions SET ml_score = ? WHERE id = ?');
            $stmt->execute([$mlScore, $transactionId]);
        }
    }
}