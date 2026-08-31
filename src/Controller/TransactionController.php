<?php

namespace TransactionMonitor\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use TransactionMonitor\Service\TransactionService;
use PDO;

class TransactionController
{
    public function __construct(
        private TransactionService $transactionService,
        private PDO $pdo
    ) {}

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $body = $request->getParsedBody();

        $toUserId = (int)($body['to_user_id'] ?? 0);
        $amount = (float)($body['amount'] ?? 0);

        if ($toUserId <= 0 || $amount <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid parameters']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $tx = $this->transactionService->createTransaction($user['id'], $toUserId, $amount);
            $response->getBody()->write(json_encode($tx));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    public function listByUser(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $params = $request->getQueryParams();
        $page = max(1, (int)($params['page'] ?? 1));
        $transactions = $this->transactionService->listByUser($user['id'], $page);
        $payload = json_encode($transactions);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $tx = $this->transactionService->getById($id);
        if (!$tx) {
            $response->getBody()->write(json_encode(['error' => 'Not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write(json_encode($tx));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function label(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        $label = (int)($body['label'] ?? -1);
        if (!in_array($label, [0, 1])) {
            $response->getBody()->write(json_encode(['error' => 'Label must be 0 or 1']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Store ground truth label for training dataset
        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO labels (transaction_id, label, labeled_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$id, $label, date('Y-m-d H:i:s')]);
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}