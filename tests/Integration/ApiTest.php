<?php
declare(strict_types=1);

namespace TransactionMonitor\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use DI\ContainerBuilder;
use TransactionMonitor\Middleware\AuthMiddleware;
use TransactionMonitor\Controller\TransactionController;
use TransactionMonitor\Controller\MonitoringController;
use TransactionMonitor\Service\TransactionService;
use TransactionMonitor\Service\BalanceService;
use TransactionMonitor\Service\MonitoringService;
use TransactionMonitor\Service\RuleEngine;
use TransactionMonitor\Service\FeatureExtractor;
use TransactionMonitor\Service\MockModel;
use PDO;

class ApiTest extends TestCase
{
    private App $app;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("PRAGMA foreign_keys = ON");
        $schema = file_get_contents(__DIR__ . '/../../schema.sql');
        $this->pdo->exec($schema);

        // Seed two users so the receiver exists
        $this->pdo->exec("INSERT INTO users (username, api_key, balance) VALUES ('alice', 'test_key', 10000)");
        $this->pdo->exec("INSERT INTO users (username, api_key, balance) VALUES ('bob', 'bob_key', 2000)");

        $settings = require __DIR__ . '/../../config/settings.php';
        $settings['db']['dsn'] = 'sqlite::memory:';

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            PDO::class   => $this->pdo,
            'settings'   => $settings,
            'rules'      => $settings['rules'],
            'mlThreshold'=> $settings['ml_threshold'],
            BalanceService::class         => \DI\autowire(),
            TransactionService::class     => \DI\autowire(),
            RuleEngine::class             => \DI\autowire()->constructorParameter('rules', \DI\get('rules')),
            FeatureExtractor::class       => \DI\autowire(),
            MockModel::class              => \DI\autowire(),
            MonitoringService::class      => \DI\autowire()->constructorParameter('mlThreshold', \DI\get('mlThreshold')),
            TransactionController::class  => \DI\autowire(),
            MonitoringController::class   => \DI\autowire(),
        ]);
        $container = $containerBuilder->build();
        AppFactory::setContainer($container);
        $this->app = AppFactory::create();
        $this->app->add(new AuthMiddleware($this->pdo));
        $this->app->addBodyParsingMiddleware();
        $this->app->addRoutingMiddleware();
        $this->app->post('/transactions', [TransactionController::class, 'create']);
        $this->app->get('/transactions', [TransactionController::class, 'listByUser']);
        $this->app->get('/transactions/{id}', [TransactionController::class, 'get']);
        $this->app->post('/transactions/{id}/label', [TransactionController::class, 'label']);
        $this->app->get('/monitoring/flags', [MonitoringController::class, 'listFlagged']);
    }

    /** @test */
    public function create_transaction_success(): void
    {
        $request = $this->createRequest('POST', '/transactions')
            ->withHeader('X-API-Key', 'test_key')
            ->withHeader('Content-Type', 'application/json')         
            ->withParsedBody(['to_user_id' => 2, 'amount' => 150]);
        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('completed', $body['status']);
    }

    /** @test */
    public function unauthenticated_returns_401(): void
    {
        $request = $this->createRequest('GET', '/transactions');
        $response = $this->app->handle($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    private function createRequest(string $method, string $path): \Psr\Http\Message\ServerRequestInterface
    {
        $factory = new ServerRequestFactory();
        return $factory->createServerRequest($method, $path);
    }
}