<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use TransactionMonitor\Worker\MonitoringWorker;
use TransactionMonitor\Service\MonitoringService;
use TransactionMonitor\Service\RuleEngine;
use TransactionMonitor\Service\FeatureExtractor;
use TransactionMonitor\Service\MockModel;
use TransactionMonitor\Service\TransactionService;
use TransactionMonitor\Service\BalanceService;

require __DIR__ . '/vendor/autoload.php';

$settings = require __DIR__ . '/config/settings.php';

$pdo = new PDO($settings['db']['dsn']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA foreign_keys = ON");

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    PDO::class   => $pdo,
    'settings'   => $settings,
    // Extract specific values from settings.php
    'rules'      => $settings['rules'],
    'mlThreshold'=> $settings['ml_threshold'],

    // Services
    BalanceService::class         => \DI\autowire(),
    TransactionService::class     => \DI\autowire(),
    RuleEngine::class             => \DI\autowire()
        ->constructorParameter('rules', \DI\get('rules')),
    FeatureExtractor::class       => \DI\autowire(),
    MockModel::class              => \DI\autowire(),
    MonitoringService::class      => \DI\autowire()
        ->constructorParameter('mlThreshold', \DI\get('mlThreshold')),
    MonitoringWorker::class       => \DI\autowire(),
]);

$container = $containerBuilder->build();

/** @var MonitoringWorker $worker */
$worker = $container->get(MonitoringWorker::class);
$worker->run();