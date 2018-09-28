<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

$paths = [__DIR__.'/entities'];
$isDevMode = true;

// the connection configuration
$dbParams = [
    'driver' => 'pdo_sqlite',
    'path' => __DIR__.'/rulerz.db',
];

$config = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration($paths, $isDevMode);

$entityManager = \Doctrine\ORM\EntityManager::create($dbParams, $config);

// compiler
$compiler = new \RulerZ\Compiler\Compiler(new \RulerZ\Compiler\EvalEvaluator());

// RulerZ engine
$rulerz = new \RulerZ\RulerZ(
    $compiler, [
        new \RulerZ\DoctrineORM\Target\DoctrineORM(),
    ]
);

return [$entityManager, $rulerz];
