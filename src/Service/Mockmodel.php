<?php

namespace TransactionMonitor\Service;

class MockModel
{
    /**
     * Simulate a trained model that returns a probability score between 0 and 1 indicating fraud.
     * This is now returns an aritificial score based on the features.
     */
    public function predict(array $features): float
    {
        // High amount + odd hours --> higher risk
        $score = 0.0;
        $score += $features['amount'] > 5000 ? 0.4 : 0.0;
        $score += in_array($features['hour_of_day'], [1,2,3,4,5]) ? 0.3 : 0.0;
        $score += $features['sender_tx_count_1h'] > 5 ? 0.2 : 0.0;
        $score += $features['amount_ratio_to_avg'] > 3 ? 0.1 : 0.0;

        return min(max($score, 0), 1);
    }
}