<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Functional;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Resolver\AbbreviationResolver;
use Manuxi\SuluAbbreviationsBundle\Route\AbbreviationRouteRemover;
use Manuxi\SuluAbbreviationsBundle\Trash\AbbreviationTrashItemHandler;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\WorkflowInterface;
use Sulu\Bundle\RouteBundle\Entity\Route;

class AbbreviationTrashItemHandlerTest extends AbbreviationKernelTestCase
{
    public function testStoreAndRestoreRoundTrip(): void
    {
        $contentManager = $this->getService('sulu_content.content_manager');
        \assert($contentManager instanceof ContentManagerInterface);
        $handler = $this->getService(AbbreviationTrashItemHandler::class);
        \assert($handler instanceof AbbreviationTrashItemHandler);
        $resolver = $this->getService(AbbreviationResolver::class);
        \assert($resolver instanceof AbbreviationResolver);
        $entityManager = $this->getEntityManager();

        $abbreviation = new Abbreviation();
        $abbreviation->setLegacyId(7);
        $contentManager->persist($abbreviation, [
            'template'           => Abbreviation::DEFAULT_TEMPLATE,
            'name'               => 'HTML',
            'explanation'        => 'HyperText Markup Language',
            'url'                => null,
            'seoTitle'           => 'Seo title',
            'excerptDescription' => 'Excerpt',
        ], ['locale' => 'en']);
        $entityManager->persist($abbreviation);
        $entityManager->flush();
        $contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $contentManager->persist($abbreviation, [
            'template'    => Abbreviation::DEFAULT_TEMPLATE,
            'name'        => 'HTML (de)',
            'explanation' => 'Auszeichnungssprache',
            'url'         => null,
        ], ['locale' => 'de']);
        $entityManager->flush();

        $id = $abbreviation->getId();

        $trashItem = $handler->store($abbreviation);
        $this->assertSame($id, $trashItem->getResourceId());
        $this->assertSame('HTML', $trashItem->getResourceTitle());

        $routeRemover = $this->getService(AbbreviationRouteRemover::class);
        \assert($routeRemover instanceof AbbreviationRouteRemover);
        $routeRemover->remove($abbreviation);
        $entityManager->remove($abbreviation);
        $entityManager->flush();
        $entityManager->clear();
        $this->assertNull($entityManager->find(Abbreviation::class, $id));
        $this->assertCount(0, $entityManager->getRepository(Route::class)->findBy(['entityId' => $id]));

        $restored = $handler->restore($trashItem);
        $this->assertInstanceOf(Abbreviation::class, $restored);
        $this->assertSame($id, $restored->getId());
        $this->assertSame(7, $restored->getLegacyId());

        $entityManager->clear();
        $reloaded = $entityManager->find(Abbreviation::class, $id);
        $this->assertInstanceOf(Abbreviation::class, $reloaded);

        $english = $contentManager->resolve($reloaded, ['locale' => 'en']);
        $this->assertInstanceOf(AbbreviationDimensionContent::class, $english);
        $this->assertSame('HTML', $english->getName());
        $this->assertSame('Seo title', $english->getSeoTitle());
        $this->assertSame('Excerpt', $english->getExcerptDescription());

        $german = $contentManager->resolve($reloaded, ['locale' => 'de']);
        $this->assertInstanceOf(AbbreviationDimensionContent::class, $german);
        $this->assertSame('HTML (de)', $german->getName());

        $live = $resolver->resolve($id, 'en');
        $this->assertNotNull($live);
        $this->assertSame('HTML', $live['name']);
        $this->assertNull($resolver->resolve($id, 'de'));

        $routes = $entityManager->getRepository(Route::class)->findBy(['entityId' => $id]);
        $this->assertCount(1, $routes);
        $this->assertSame('/abbreviations/html', $routes[0]->getPath());

        $stages = [];
        foreach ($reloaded->getDimensionContents() as $dimensionContent) {
            $stages[] = $dimensionContent->getStage() . ':' . ($dimensionContent->getLocale() ?? '-');
        }
        sort($stages);
        $this->assertSame([
            DimensionContentInterface::STAGE_DRAFT . ':-',
            DimensionContentInterface::STAGE_DRAFT . ':de',
            DimensionContentInterface::STAGE_DRAFT . ':en',
            DimensionContentInterface::STAGE_LIVE . ':-',
            DimensionContentInterface::STAGE_LIVE . ':en',
        ], $stages);
    }

    public function testConfigurationAndResourceKey(): void
    {
        $handler = $this->getService(AbbreviationTrashItemHandler::class);
        \assert($handler instanceof AbbreviationTrashItemHandler);

        $this->assertSame(Abbreviation::RESOURCE_KEY, AbbreviationTrashItemHandler::getResourceKey());
        $this->assertSame(['id' => 'id'], $handler->getConfiguration()->getResultToView());
    }
}
