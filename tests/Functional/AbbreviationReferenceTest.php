<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Functional;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Reference\AbbreviationReferenceRefresher;
use Manuxi\SuluAbbreviationsBundle\Reference\AbbreviationReferenceUpdater;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\WorkflowInterface;
use Sulu\Bundle\ReferenceBundle\Domain\Model\ReferenceInterface;

class AbbreviationReferenceTest extends AbbreviationKernelTestCase
{
    private ContentManagerInterface $contentManager;

    private AbbreviationReferenceUpdater $updater;

    protected function setUp(): void
    {
        parent::setUp();

        $contentManager = $this->getService('sulu_content.content_manager');
        \assert($contentManager instanceof ContentManagerInterface);
        $this->contentManager = $contentManager;

        $updater = $this->getService(AbbreviationReferenceUpdater::class);
        \assert($updater instanceof AbbreviationReferenceUpdater);
        $this->updater = $updater;
    }

    public function testDraftAndLiveReferencesAreStoredPerContext(): void
    {
        $abbreviation = $this->createAbbreviation();

        $draft = $this->contentManager->resolve($abbreviation, ['locale' => 'en']);
        $this->assertInstanceOf(AbbreviationDimensionContent::class, $draft);
        $this->updater->update($draft);
        $this->getEntityManager()->flush();

        $this->assertSame(
            ['excerptImage:media:8', 'image:media:5'],
            $this->describeReferences($abbreviation, 'admin')
        );
        $this->assertSame([], $this->describeReferences($abbreviation, 'website'));

        $this->contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $this->getEntityManager()->flush();

        $this->assertSame(
            ['excerptImage:media:8', 'image:media:5'],
            $this->describeReferences($abbreviation, 'website')
        );
    }

    public function testUnpublishRemovesTheWebsiteReferences(): void
    {
        $abbreviation = $this->createAbbreviation();
        $this->contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $this->getEntityManager()->flush();
        $this->assertNotSame([], $this->describeReferences($abbreviation, 'website'));

        $this->contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH);
        $this->getEntityManager()->flush();

        $this->assertSame([], $this->describeReferences($abbreviation, 'website'));
    }

    public function testRemoveDeletesAllReferencesOfTheAbbreviation(): void
    {
        $abbreviation = $this->createAbbreviation();
        $draft        = $this->contentManager->resolve($abbreviation, ['locale' => 'en']);
        $this->updater->update($draft);
        $this->contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $this->getEntityManager()->flush();

        $this->updater->remove($abbreviation);
        $this->getEntityManager()->flush();

        $this->assertSame([], $this->describeReferences($abbreviation, 'admin'));
        $this->assertSame([], $this->describeReferences($abbreviation, 'website'));
    }

    public function testRefresherRebuildsTheReferencesOfTheCurrentContext(): void
    {
        $abbreviation = $this->createAbbreviation();

        $refresher = $this->getService(AbbreviationReferenceRefresher::class);
        \assert($refresher instanceof AbbreviationReferenceRefresher);

        $refreshed = iterator_to_array($refresher->refresh(), false);
        $this->getEntityManager()->flush();

        $this->assertCount(1, $refreshed);
        $this->assertSame($abbreviation->getId(), $refreshed[0]->getId());
        $this->assertSame(['excerptImage:media:8', 'image:media:5'], $this->describeReferences($abbreviation, 'admin'));
        $this->assertSame('abbreviations', AbbreviationReferenceRefresher::getResourceKey());
    }

    public function testRefresherOfTheWebsiteContextUsesTheLiveContent(): void
    {
        $abbreviation = $this->createAbbreviation();
        $other        = $this->createAbbreviation();
        $this->contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->createQuery('DELETE FROM ' . ReferenceInterface::class)->execute();

        $refresher = new AbbreviationReferenceRefresher($this->getEntityManager(), $this->contentManager, $this->updater, 'website');
        iterator_to_array($refresher->refresh(), false);
        $this->getEntityManager()->flush();

        $this->assertSame(['excerptImage:media:8', 'image:media:5'], $this->describeReferences($abbreviation, 'website'));
        $this->assertSame([], $this->describeReferences($other, 'website'));
        $this->assertSame([], $this->describeReferences($abbreviation, 'admin'));
    }

    private function createAbbreviation(): Abbreviation
    {
        $abbreviation = new Abbreviation();
        $this->contentManager->persist($abbreviation, [
            'template'     => Abbreviation::DEFAULT_TEMPLATE,
            'name'         => 'HTML',
            'explanation'  => 'HyperText Markup Language',
            'url'          => null,
            'image'        => ['id' => 5],
            'excerptImage' => ['id' => 8],
        ], ['locale' => 'en']);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($abbreviation);
        $entityManager->flush();

        return $abbreviation;
    }

    /**
     * @return string[]
     */
    private function describeReferences(Abbreviation $abbreviation, string $context): array
    {
        $references = $this->getEntityManager()->getRepository(ReferenceInterface::class)->findBy([
            'referenceResourceKey' => Abbreviation::RESOURCE_KEY,
            'referenceResourceId'  => $abbreviation->getId(),
            'referenceContext'     => $context,
        ]);

        $described = array_map(
            static fn (ReferenceInterface $reference): string => \sprintf('%s:%s:%s', $reference->getReferenceProperty(), $reference->getResourceKey(), $reference->getResourceId()),
            $references
        );
        sort($described);

        return $described;
    }
}
