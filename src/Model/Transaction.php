<?php

namespace TransactionMonitor\Model;

class Transaction
{
    public function __construct(
        public int $id,
        public int $fromUserId,
        public int $toUserId,
        public float $amount,
        public string $currency,
        public string $createdAt,
        public string $status = 'completed',
        public ?string $flaggedReason = null,
        public ?float $mlScore = null,
    ) {}
}