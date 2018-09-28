<?php

declare(strict_types=1);

use Behat\Behat\Context\Context as BehatContext;
use RulerZ\Test\BaseContext;

class Context extends BaseContext implements BehatContext
{
    /** @var \Doctrine\ORM\EntityManager */
    private $entityManager;

    public function initialize()
    {
        $paths = [__DIR__.'/../../examples/entities']; // meh.
        $isDevMode = true;

        // the connection configuration
        $dbParams = [
            'driver' => 'pdo_sqlite',
            'path' => __DIR__.'/../../examples/rulerz.db', // meh.
        ];

        $config = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration($paths, $isDevMode);

        $this->entityManager = \Doctrine\ORM\EntityManager::create($dbParams, $config);
    }

    /**
     * {@inheritdoc}
     */
    protected function getCompilationTarget(): \RulerZ\Compiler\CompilationTarget
    {
        return new \RulerZ\DoctrineORM\Target\DoctrineORM();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultDataset()
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(Entity\Player::class, 'p');
    }
}
