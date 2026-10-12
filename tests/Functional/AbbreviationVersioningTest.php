<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Functional;

use Manuxi\SuluAbbreviationsBundle\Controller\Admin\AbbreviationVersionController;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationVersionRepository;
use Manuxi\SuluAbbreviationsBundle\Translation\AbbreviationTranslationRemover;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\WorkflowInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AbbreviationVersioningTest extends AbbreviationKernelTestCase
{
    private ContentManagerInterface $contentManager;

    protected function setUp(): void
    {
        parent::setUp();

        $contentManager = $this->getService('sulu_content.content_manager');
        \assert($contentManager instanceof ContentManagerInterface);
        $this->contentManager = $contentManager;
    }

    public function testEveryPublishStoresAVersionOfTheDraft(): void
    {
        $abbreviation = $this->createAndPublish('HTML', 'First');
        $this->saveAndPublish($abbreviation, 'HTML', 'Second');
        $this->saveAndPublish($abbreviation, 'HTML', 'Third');

        $versions = $this->getVersionRepository()->findByAbbreviation($abbreviation, 'en');

        $this->assertCount(3, $versions);
        $this->assertSame(3, $this->getVersionRepository()->countByAbbreviation($abbreviation, 'en'));
        $this->assertSame(['Third', 'Second', 'First'], array_map(static fn ($version): mixed => $version->getData()['explanation'], $versions));
        $this->assertGreaterThan($versions[1]->getVersion(), $versions[0]->getVersion());
        $this->assertGreaterThan($versions[2]->getVersion(), $versions[1]->getVersion());
        $this->assertSame('en', $versions[0]->getLocale());
        $this->assertSame($versions[0]->getVersion(), $this->getVersionRepository()->findLatestVersionNumber($abbreviation, 'en'));
    }

    public function testVersionsAreSeparatedByLocale(): void
    {
        $abbreviation = $this->createAndPublish('HTML', 'English');
        $this->contentManager->persist($abbreviation, [
            'template'    => Abbreviation::DEFAULT_TEMPLATE,
            'name'        => 'HTML',
            'explanation' => 'Deutsch',
            'url'         => null,
        ], ['locale' => 'de']);
        $this->getEntityManager()->flush();
        $this->contentManager->applyTransition($abbreviation, ['locale' => 'de'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $this->getEntityManager()->flush();

        $this->assertSame(1, $this->getVersionRepository()->countByAbbreviation($abbreviation, 'en'));
        $this->assertSame(1, $this->getVersionRepository()->countByAbbreviation($abbreviation, 'de'));
        $this->assertNull($this->getVersionRepository()->findLatestVersionNumber($abbreviation, 'fr'));
    }

    public function testRestoreWritesTheStoredVersionIntoTheDraft(): void
    {
        $abbreviation = $this->createAndPublish('HTML', 'First');
        $this->saveAndPublish($abbreviation, 'HTML', 'Second');

        $versions = $this->getVersionRepository()->findByAbbreviation($abbreviation, 'en');
        $first    = $versions[1];
        $this->assertSame('First', $first->getData()['explanation']);

        $controller = $this->getService(AbbreviationVersionController::class);
        \assert($controller instanceof AbbreviationVersionController);

        $request = new Request(['action' => 'restore', 'locale' => 'en', '_format' => 'json'], [], ['_format' => 'json']);
        $this->pushRequest($request);

        $response = $controller->postTriggerAction($request, $abbreviation->getId(), $first->getVersion());
        $this->assertSame(200, $response->getStatusCode());

        $draft = $this->contentManager->resolve($abbreviation, ['locale' => 'en']);
        $this->assertInstanceOf(AbbreviationDimensionContent::class, $draft);
        $this->assertSame('First', $draft->getTemplateData()['explanation']);
        $this->assertSame(WorkflowInterface::WORKFLOW_PLACE_DRAFT, $draft->getWorkflowPlace());

        $live = $this->contentManager->resolve($abbreviation, ['locale' => 'en', 'stage' => 'live']);
        $this->assertSame('Second', $live->getTemplateData()['explanation']);
    }

    public function testDeletedTranslationCanBeRestoredFromItsVersions(): void
    {
        $abbreviation = $this->createAndPublish('HTML', 'English');
        $this->contentManager->persist($abbreviation, [
            'template'    => Abbreviation::DEFAULT_TEMPLATE,
            'name'        => 'HTML (de)',
            'explanation' => 'Deutsch',
            'url'         => null,
        ], ['locale' => 'de']);
        $this->getEntityManager()->flush();
        $this->contentManager->applyTransition($abbreviation, ['locale' => 'de'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $this->getEntityManager()->flush();

        $remover = $this->getService(AbbreviationTranslationRemover::class);
        \assert($remover instanceof AbbreviationTranslationRemover);
        $this->assertTrue($remover->remove($abbreviation, 'de'));
        $this->getEntityManager()->flush();
        $this->assertSame(['en'], $remover->getAvailableLocales($abbreviation));

        $version = $this->getVersionRepository()->findLatestVersionNumber($abbreviation, 'de');
        $this->assertNotNull($version);

        $controller = $this->getService(AbbreviationVersionController::class);
        \assert($controller instanceof AbbreviationVersionController);
        $request = new Request(['action' => 'restore', 'locale' => 'de', '_format' => 'json'], [], ['_format' => 'json']);
        $this->pushRequest($request);
        $controller->postTriggerAction($request, $abbreviation->getId(), $version);

        $this->assertEqualsCanonicalizing(['en', 'de'], $remover->getAvailableLocales($abbreviation));
        $draft = $this->contentManager->resolve($abbreviation, ['locale' => 'de']);
        $this->assertSame('Deutsch', $draft->getTemplateData()['explanation']);
        $this->assertSame('de', $draft->getLocale());
    }

    public function testListReturnsNewestVersionFirstAsRepresentation(): void
    {
        $abbreviation = $this->createAndPublish('HTML', 'First');
        $this->saveAndPublish($abbreviation, 'HTML', 'Second');

        $controller = $this->getService(AbbreviationVersionController::class);
        \assert($controller instanceof AbbreviationVersionController);

        $request = new Request(['locale' => 'en'], [], ['_format' => 'json']);
        $this->pushRequest($request);

        $response = $controller->cgetAction($request, $abbreviation->getId());
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['_embedded']['abbreviation_versions']);
        $this->assertGreaterThan(
            $data['_embedded']['abbreviation_versions'][1]['id'],
            $data['_embedded']['abbreviation_versions'][0]['id']
        );
    }

    private function pushRequest(Request $request): void
    {
        $requestStack = $this->getService('request_stack');
        \assert($requestStack instanceof RequestStack);
        $requestStack->push($request);
    }

    private function createAndPublish(string $name, string $explanation): Abbreviation
    {
        $abbreviation = new Abbreviation();
        $this->getEntityManager()->persist($abbreviation);
        $this->saveAndPublish($abbreviation, $name, $explanation);

        return $abbreviation;
    }

    private function saveAndPublish(Abbreviation $abbreviation, string $name, string $explanation): void
    {
        $this->contentManager->persist($abbreviation, [
            'template'    => Abbreviation::DEFAULT_TEMPLATE,
            'name'        => $name,
            'explanation' => $explanation,
            'url'         => null,
        ], ['locale' => 'en']);
        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        $draft = $this->contentManager->resolve($abbreviation, ['locale' => 'en']);
        if (WorkflowInterface::WORKFLOW_PLACE_PUBLISHED === $draft->getWorkflowPlace()) {
            $this->contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_CREATE_DRAFT);
        }

        $this->contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH);
        $entityManager->flush();
    }

    private function getVersionRepository(): AbbreviationVersionRepository
    {
        $repository = $this->getService(AbbreviationVersionRepository::class);
        \assert($repository instanceof AbbreviationVersionRepository);

        return $repository;
    }
}
