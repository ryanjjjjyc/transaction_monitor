<?php

namespace TransactionMonitor\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use PDO;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private PDO $pdo) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $apiKey = $request->getHeaderLine('X-API-Key');
        if (empty($apiKey)) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'API key required']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE api_key = ?');
        $stmt->execute([$apiKey]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Invalid API key']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $request = $request->withAttribute('user', $user);
        return $handler->handle($request);
    }
}