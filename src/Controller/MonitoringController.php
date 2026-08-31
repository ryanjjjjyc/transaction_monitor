<?php

namespace TransactionMonitor\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class MonitoringController
{
    public function __construct(private PDO $pdo) {}

    public function listFlagged(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $stmt = $this->pdo->prepare(
            'SELECT * FROM transactions WHERE status = ? AND (from_user_id = ? OR to_user_id = ?) ORDER BY created_at DESC'
        );
        $stmt->execute(['flagged', $user['id'], $user['id']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $response->getBody()->write(json_encode($rows));
        return $response->withHeader('Content-Type', 'application/json');
    }
}