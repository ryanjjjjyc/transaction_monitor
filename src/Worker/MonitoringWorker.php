<?php

namespace TransactionMonitor\Worker;

use PDO;
use TransactionMonitor\Service\MonitoringService;

class MonitoringWorker
{
    public function __construct(
        private PDO $pdo,
        private MonitoringService $monitoringService
    ) {}

    public function run(): void
    {
        echo "Monitoring worker started...\n";
        while (true) {
            $this->processBatch();
            sleep(2); // poll interval every 2 seconds
        }
    }

    private function processBatch(): void
    {
        // Begin transaction
        $this->pdo->beginTransaction();
        try {
            // 1. Find one pending job (no lock)
            $stmt = $this->pdo->prepare('SELECT id, transaction_id FROM monitoring_queue WHERE status = ? LIMIT 1');
            $stmt->execute(['pending']);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$job) {
                $this->pdo->commit();
                return;
            }

            // 2. Try to claim it, only if it's still pending
            $stmt = $this->pdo->prepare('UPDATE monitoring_queue SET status = ? WHERE id = ? AND status = ?');
            $stmt->execute(['processing', $job['id'], 'pending']);
            if ($stmt->rowCount() === 0) {
                // Another worker already claimed it
                $this->pdo->commit();
                return;
            }

            // 3. Commit the claim, then process outside the transaction
            $this->pdo->commit();

            // 4. Perform the monitoring
            try {
                $this->monitoringService->processTransaction((int)$job['transaction_id']);
                $stmt = $this->pdo->prepare('UPDATE monitoring_queue SET status = ? WHERE id = ?');
                $stmt->execute(['done', $job['id']]);
            } catch (\Exception $e) {
                // On error, revert to pending for retry
                $stmt = $this->pdo->prepare('UPDATE monitoring_queue SET status = ? WHERE id = ?');
                $stmt->execute(['pending', $job['id']]);
                echo "Error: " . $e->getMessage() . "\n";
            }
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            echo "Worker error: " . $e->getMessage() . "\n";
        }
    }
}