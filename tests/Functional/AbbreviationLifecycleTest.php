<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Functional;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Resolver\AbbreviationResolver;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\WorkflowInterface;

class AbbreviationLifecycleTest extends AbbreviationKernelTestCase
{
    private ContentManagerInterface $contentManager;

    protected function setUp(): void
    {
        parent::setUp();

        $contentManager = $this->getService('sulu_content.content_manager');
        \assert($contentManager instanceof ContentManagerInterface);
        $this->contentManager = $contentManager;
    }

    public function testCreateAssignsUuidAndStoresDraft(): void
    {
        $abbreviation = $this->createAbbreviation('HTML', 'HyperText Markup Language');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $abbreviation->getId());

        $draft = $this->contentManager->resolve($abbreviation, ['locale' => 'en']);
        $this->assertInstanceOf(AbbreviationDimensionContent::class, $draft);
        $this->assertSame('HTML', $draft->getName());
        $this->assertSame('HTML', $draft->getTitle());
        $this->assertSame(DimensionContentInterface::STAGE_DRAFT, $draft->getStage());
        $this->assertSame(Abbreviation::DEFAULT_TEMPLATE, $draft->getTemplateKey());
    }

    public function testDraftIsNotVisibleOnTheWebsiteUntilPublished(): void
    {
        $abbreviation = $this->createAbbreviation('CSS', 'Cascading Style Sheets');

        $resolver = $this->getResolver();
        $this->assertNull($resolver->resolve($abbreviation->getId(), 'en'));
        $this->assertSame([], $this->getRepository()->findPublishedIds('en'));

        $this->publish($abbreviation, 'en');

        $resolved = $resolver->resolve($abbreviation->getId(), 'en');
        $this->assertNotNull($resolved);
        $this->assertSame('CSS', $resolved['name']);
        $this->assertSame('Cascading Style Sheets', $resolved['explanation']);
        $this->assertSame('/abbreviations/css', $resolved['url']);
        $this->assertSame([$abbreviation->getId()], $this->getRepository()->findPublishedIds('en'));
    }

    public function testUnpublishedLocaleIsNotResolved(): void
    {
        $abbreviation = $this->createAbbreviation('PHP', 'PHP: Hypertext Preprocessor');
        $this->publish($abbreviation, 'en');

        $this->assertNull($this->getResolver()->resolve($abbreviation->getId(), 'de'));
    }

    public function testUnpublishRemovesLiveContent(): void
    {
        $abbreviation = $this->createAbbreviation('SQL', 'Structured Query Language');
        $this->publish($abbreviation, 'en');

        $this->contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH);
        $this->getEntityManager()->flush();

        $this->assertNull($this->getResolver()->resolve($abbreviation->getId(), 'en'));
        $this->assertSame([], $this->getRepository()->findPublishedIds('en'));
    }

    public function testCopyLocaleCreatesDraftInTargetLocale(): void
    {
        $abbreviation = $this->createAbbreviation('API', 'Application Programming Interface');

        $this->contentManager->copy(
            $abbreviation,
            ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => 'en'],
            $abbreviation,
            ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => 'de']
        );
        $this->getEntityManager()->flush();

        $german = $this->contentManager->resolve($abbreviation, ['locale' => 'de']);
        $this->assertInstanceOf(AbbreviationDimensionContent::class, $german);
        $this->assertSame('de', $german->getLocale());
        $this->assertSame('API', $german->getName());
    }

    public function testPublishedIdsAreOrderedByNameAndLimited(): void
    {
        foreach (['XML', 'AJAX', 'REST'] as $name) {
            $this->publish($this->createAbbreviation($name, $name . ' explanation'), 'en');
        }

        $resolved = $this->getResolver()->resolveMany($this->getRepository()->findPublishedIds('en'), 'en');
        $this->assertSame(['AJAX', 'REST', 'XML'], array_column($resolved, 'name'));
        $this->assertCount(2, $this->getRepository()->findPublishedIds('en', 2));
    }

    public function testRemoveCascadesToDimensionContents(): void
    {
        $abbreviation = $this->createAbbreviation('CLI', 'Command Line Interface');
        $this->publish($abbreviation, 'en');
        $id = $abbreviation->getId();

        $entityManager = $this->getEntityManager();
        $entityManager->remove($abbreviation);
        $entityManager->flush();
        $entityManager->clear();

        $this->assertNull($this->getRepository()->findById($id));
        $this->assertCount(0, $entityManager->getRepository(AbbreviationDimensionContent::class)->findAll());
    }

    private function createAbbreviation(string $name, string $explanation): Abbreviation
    {
        $abbreviation = new Abbreviation();
        $this->contentManager->persist($abbreviation, [
            'template'    => Abbreviation::DEFAULT_TEMPLATE,
            'name'        => $name,
            'explanation' => $explanation,
            'url'         => null,
        ], ['locale' => 'en']);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($abbreviation);
        $entityManager->flush();

        return $abbreviation;
    }

    private function publish(Abbreviation $abbreviation, string $locale): void
    {
        $this->contentManager->applyTransition($abbreviation, ['locale' => $locale], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $this->getEntityManager()->flush();
    }

    private function getResolver(): AbbreviationResolver
    {
        $resolver = $this->getService(AbbreviationResolver::class);
        \assert($resolver instanceof AbbreviationResolver);

        return $resolver;
    }

    private function getRepository(): AbbreviationRepository
    {
        $repository = $this->getService(AbbreviationRepository::class);
        \assert($repository instanceof AbbreviationRepository);

        return $repository;
    }
}
