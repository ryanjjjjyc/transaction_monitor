<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use TransactionMonitor\Middleware\AuthMiddleware;
use TransactionMonitor\Controller\TransactionController;
use TransactionMonitor\Controller\MonitoringController;
use TransactionMonitor\Service\TransactionService;
use TransactionMonitor\Service\BalanceService;
use TransactionMonitor\Service\MonitoringService;
use TransactionMonitor\Service\RuleEngine;
use TransactionMonitor\Service\FeatureExtractor;
use TransactionMonitor\Service\MockModel;

require __DIR__ . '/../vendor/autoload.php';

$settings = require __DIR__ . '/../config/settings.php';

$pdo = new PDO($settings['db']['dsn']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA foreign_keys = ON");

// Create tables if they don't exist
$pdo->exec(file_get_contents(__DIR__ . '/../schema.sql'));

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    PDO::class   => $pdo,
    'settings'   => $settings,

    // Extract specific values from settings.php
    'rules'      => $settings['rules'],
    'mlThreshold'=> $settings['ml_threshold'],

    BalanceService::class         => \DI\autowire(),
    TransactionService::class     => \DI\autowire(),
    RuleEngine::class             => \DI\autowire()
        ->constructorParameter('rules', \DI\get('rules')),
    FeatureExtractor::class       => \DI\autowire(),
    MockModel::class              => \DI\autowire(),
    MonitoringService::class      => \DI\autowire()
        ->constructorParameter('mlThreshold', \DI\get('mlThreshold')),
    TransactionController::class  => \DI\autowire(),
    MonitoringController::class   => \DI\autowire(),
]);

$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$app->add(new AuthMiddleware($pdo));
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Routes
$app->post('/transactions', [TransactionController::class, 'create']);
$app->get('/transactions', [TransactionController::class, 'listByUser']);
$app->get('/transactions/{id}', [TransactionController::class, 'get']);
$app->post('/transactions/{id}/label', [TransactionController::class, 'label']);
$app->get('/monitoring/flags', [MonitoringController::class, 'listFlagged']);

$app->run();