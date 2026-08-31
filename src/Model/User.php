<?php

namespace TransactionMonitor\Model;

class User
{
    public function __construct(
        public int $id,
        public string $username,
        public string $apiKey,
        public float $balance,
    ) {}
}