<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Versioning;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationVersion;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationVersionRepository;
use Manuxi\SuluAbbreviationsBundle\Versioning\VersionRecorder;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

class VersionRecorderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AbbreviationVersionRepository> */
    private ObjectProphecy $repository;

    /** @var ObjectProphecy<ContentManagerInterface> */
    private ObjectProphecy $contentManager;

    private TokenStorage $tokenStorage;

    protected function setUp(): void
    {
        $this->repository     = $this->prophesize(AbbreviationVersionRepository::class);
        $this->contentManager = $this->prophesize(ContentManagerInterface::class);
        $this->tokenStorage   = new TokenStorage();

        $this->repository->add(Argument::any())->will(static function (): void {
        });
        $this->repository->removeOldVersions(Argument::cetera())->willReturn(0);
    }

    private function createRecorder(int $maxVersions = 20): VersionRecorder
    {
        return new VersionRecorder($this->repository->reveal(), $this->contentManager->reveal(), $this->tokenStorage, $maxVersions);
    }

    private function createEvent(object $subject): CompletedEvent
    {
        return new CompletedEvent($subject, new Marking(), new Transition('publish', 'draft', 'published'));
    }

    private function createDraft(?string $locale = 'en'): AbbreviationDimensionContent
    {
        $draft = new AbbreviationDimensionContent(new Abbreviation());
        $draft->setLocale($locale);

        return $draft;
    }

    public function testStoresSnapshotOfTheDraft(): void
    {
        $draft = $this->createDraft();
        $this->repository->findLatestVersionNumber($draft->getResource(), 'en')->willReturn(null);
        $this->contentManager->normalize($draft)->willReturn(['name' => 'HTML']);

        $before = time();
        $this->createRecorder()->onPublish($this->createEvent($draft));

        $this->repository->add(Argument::that(static function (AbbreviationVersion $version) use ($draft, $before): bool {
            return $version->getAbbreviation() === $draft->getResource()
                && 'en'                        === $version->getLocale()
                && ['name' => 'HTML']          === $version->getData()
                && $version->getVersion() >= $before
                && $version->getCreated()->getTimestamp() === $version->getVersion()
                && null                                   === $version->getCreator();
        }))->shouldHaveBeenCalledOnce();
    }

    public function testVersionNumbersAreStrictlyIncreasing(): void
    {
        $draft = $this->createDraft();
        $this->repository->findLatestVersionNumber($draft->getResource(), 'en')->willReturn(time() + 1000);
        $this->contentManager->normalize($draft)->willReturn([]);

        $this->createRecorder()->onPublish($this->createEvent($draft));

        $this->repository->add(Argument::that(static fn (AbbreviationVersion $version): bool => $version->getVersion() > time() + 1000))->shouldHaveBeenCalledOnce();
    }

    public function testRemovesVersionsBeyondTheRetention(): void
    {
        $draft = $this->createDraft();
        $this->repository->findLatestVersionNumber(Argument::cetera())->willReturn(null);
        $this->contentManager->normalize($draft)->willReturn([]);

        $this->createRecorder(5)->onPublish($this->createEvent($draft));

        $this->repository->removeOldVersions($draft->getResource(), 'en', 4)->shouldHaveBeenCalledOnce();
    }

    public function testDoesNothingWhenVersioningIsDisabled(): void
    {
        $this->createRecorder(0)->onPublish($this->createEvent($this->createDraft()));

        $this->repository->add(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function testIgnoresOtherSubjects(): void
    {
        $this->createRecorder()->onPublish($this->createEvent(new \stdClass()));

        $this->repository->add(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function testIgnoresUnlocalizedContent(): void
    {
        $this->createRecorder()->onPublish($this->createEvent($this->createDraft(null)));

        $this->repository->add(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function testStoresTheCurrentUserAsCreator(): void
    {
        $user = new User();
        $this->tokenStorage->setToken(new UsernamePasswordToken($user, 'admin', []));

        $draft = $this->createDraft();
        $this->repository->findLatestVersionNumber(Argument::cetera())->willReturn(null);
        $this->contentManager->normalize($draft)->willReturn([]);

        $this->createRecorder()->onPublish($this->createEvent($draft));

        $this->repository->add(Argument::that(static fn (AbbreviationVersion $version): bool => $version->getCreator() === $user))->shouldHaveBeenCalledOnce();
    }
}
