<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Manuxi\SuluAbbreviationsBundle\Tests\App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class AbbreviationKernelTestCase extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = 'sqlite:///:memory:';

        return new Kernel('test', true, 'admin');
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $entityManager = $this->getEntityManager();
        $schemaTool    = new SchemaTool($entityManager);
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    protected function getService(string $id): object
    {
        return static::getContainer()->get($id);
    }
}
