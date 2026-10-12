<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Functional;

use Manuxi\SuluAbbreviationsBundle\Command\MigratePageReferencesCommand;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Migration\PageReferenceMigrator;
use PHPCR\NodeInterface;
use PHPCR\SessionInterface;
use Sulu\Bundle\DocumentManagerBundle\Initializer\Initializer;
use Sulu\Bundle\PageBundle\Document\PageDocument;
use Sulu\Component\DocumentManager\DocumentManagerInterface;
use Symfony\Component\Console\Tester\CommandTester;

class PageReferenceMigrationTest extends AbbreviationKernelTestCase
{
    /** @var array<int, string> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        $initializer = $this->getService('sulu_document_manager.initializer');
        \assert($initializer instanceof Initializer);
        $initializer->initialize(null, true);

        foreach ([1, 2] as $legacyId) {
            $abbreviation = (new Abbreviation())->setLegacyId($legacyId);
            $this->getEntityManager()->persist($abbreviation);
            $this->ids[$legacyId] = $abbreviation->getId();
        }
        $this->getEntityManager()->flush();
    }

    public function testSelectionsAndLinksAreRewritten(): void
    {
        $this->createPage();

        $result = $this->getMigrator()->migrate();

        $this->assertTrue($result->mappingFound);
        $this->assertGreaterThan(0, $result->nodes);

        foreach ([$this->getSession('doctrine_phpcr.session'), $this->getSession('doctrine_phpcr.live_session')] as $session) {
            $node = $this->getPageNode($session);

            $this->assertSame([$this->ids[1], $this->ids[2]], $this->read($node, 'i18n:en-abbreviations'));
            $this->assertSame($this->ids[1], $this->read($node, 'i18n:en-abbreviation'));
            $this->assertSame(
                '<p><sulu-link provider="abbreviations" href="' . $this->ids[1] . '">x</sulu-link></p>',
                $this->read($node, 'i18n:en-article')
            );
            $this->assertSame([$this->ids[2], $this->ids[1]], $this->read($node, 'i18n:en-blocks-blockAbbreviations#0'));
            $this->assertSame($this->ids[2], $this->read($node, 'i18n:en-blocks-blockAbbreviation#0'));
            $this->assertSame(
                '<sulu-link href="' . $this->ids[2] . '" provider="abbreviations">y</sulu-link>',
                $this->read($node, 'i18n:en-blocks-blockText#0')
            );
        }

        $this->assertSame(8, $result->selections);
        $this->assertSame(4, $result->links);
        $this->assertSame(0, $result->unmapped);
    }

    public function testUnknownIdsAreLeftUntouchedAndCounted(): void
    {
        $this->createPage(['abbreviations' => [1, 99], 'abbreviation' => 98, 'article' => '<sulu-link provider="abbreviation" href="97">x</sulu-link>', 'blocks' => []]);

        $result = $this->getMigrator()->migrate();

        $node = $this->getPageNode($this->getSession('doctrine_phpcr.session'));
        $this->assertSame([$this->ids[1], '99'], $this->read($node, 'i18n:en-abbreviations'));
        $this->assertSame(98, $this->read($node, 'i18n:en-abbreviation'));
        $this->assertSame('<sulu-link provider="abbreviation" href="97">x</sulu-link>', $this->read($node, 'i18n:en-article'));
        $this->assertSame(6, $result->unmapped);
    }

    public function testMigrationIsIdempotent(): void
    {
        $this->createPage();

        $first  = $this->getMigrator()->migrate();
        $second = $this->getMigrator()->migrate();

        $this->assertGreaterThan(0, $first->selections);
        $this->assertSame(0, $second->selections);
        $this->assertSame(0, $second->links);
        $this->assertSame(0, $second->unmapped);
    }

    public function testDryRunDoesNotChangeTheContent(): void
    {
        $this->createPage();

        $result = $this->getMigrator()->migrate(true);

        $this->assertGreaterThan(0, $result->selections);
        $node = $this->getPageNode($this->getSession('doctrine_phpcr.session'));
        $this->assertSame([1, 2], $this->read($node, 'i18n:en-abbreviations'));
        $this->assertSame(1, $this->read($node, 'i18n:en-abbreviation'));
        $this->assertStringContainsString('provider="abbreviation"', (string) $this->read($node, 'i18n:en-article'));

        $this->assertSame($result->selections, $this->getMigrator()->migrate()->selections);
    }

    public function testNothingHappensWithoutMigratedAbbreviations(): void
    {
        $this->getEntityManager()->createQuery('UPDATE ' . Abbreviation::class . ' a SET a.legacyId = NULL')->execute();

        $result = $this->getMigrator()->migrate();

        $this->assertFalse($result->mappingFound);
    }

    public function testCommandReportsTheResult(): void
    {
        $this->createPage();

        $command = $this->getService(MigratePageReferencesCommand::class);
        \assert($command instanceof MigratePageReferencesCommand);
        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute(['--dry-run' => true]));
        $this->assertStringContainsString('Dry run', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/Links rewritten\s+4/', $tester->getDisplay());

        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('Page references migrated', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function createPage(?array $data = null): void
    {
        $documentManager = $this->getService('sulu_document_manager.document_manager');
        \assert($documentManager instanceof DocumentManagerInterface);

        $document = new PageDocument();
        $document->setTitle('Home');
        $document->setStructureType('default');
        $document->setResourceSegment('/home');
        $document->getStructure()->bind($data ?? [
            'title'         => 'Home',
            'url'           => '/home',
            'abbreviations' => [1, 2],
            'abbreviation'  => 1,
            'article'       => '<p><sulu-link provider="abbreviation" href="1">x</sulu-link></p>',
            'blocks'        => [[
                'type'               => 'abbreviations',
                'blockAbbreviations' => [2, 1],
                'blockAbbreviation'  => 2,
                'blockText'          => '<sulu-link href="2" provider="abbreviation">y</sulu-link>',
            ]],
        ]);
        $documentManager->persist($document, 'en', ['parent_path' => '/cmf/test/contents', 'load_ghost_content' => false]);
        $documentManager->publish($document, 'en');
        $documentManager->flush();
    }

    private function getSession(string $id): SessionInterface
    {
        $session = $this->getService($id);
        \assert($session instanceof SessionInterface);

        return $session;
    }

    private function getPageNode(SessionInterface $session): NodeInterface
    {
        return $session->getNode('/cmf/test/contents/home');
    }

    private function read(NodeInterface $node, string $property): mixed
    {
        return $node->getPropertyValue($property);
    }

    private function getMigrator(): PageReferenceMigrator
    {
        $migrator = $this->getService(PageReferenceMigrator::class);
        \assert($migrator instanceof PageReferenceMigrator);

        return $migrator;
    }
}
