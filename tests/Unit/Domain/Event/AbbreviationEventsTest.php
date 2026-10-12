<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Domain\Event;

use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationCopiedLanguageEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationCreatedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationDraftRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationModifiedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationPublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationRestoredEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationTranslationRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationUnpublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationVersionRestoredEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbstractAbbreviationEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AbbreviationEventsTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<AbstractAbbreviationEvent>, string}>
     */
    public static function provideEvents(): iterable
    {
        yield 'created' => [AbbreviationCreatedEvent::class, 'created'];
        yield 'modified' => [AbbreviationModifiedEvent::class, 'modified'];
        yield 'removed' => [AbbreviationRemovedEvent::class, 'removed'];
        yield 'restored' => [AbbreviationRestoredEvent::class, 'restored'];
        yield 'published' => [AbbreviationPublishedEvent::class, 'published'];
        yield 'unpublished' => [AbbreviationUnpublishedEvent::class, 'unpublished'];
        yield 'draft removed' => [AbbreviationDraftRemovedEvent::class, 'draft_removed'];
        yield 'copied language' => [AbbreviationCopiedLanguageEvent::class, 'copied_language'];
        yield 'translation removed' => [AbbreviationTranslationRemovedEvent::class, 'translation_removed'];
        yield 'version restored' => [AbbreviationVersionRestoredEvent::class, 'version_restored'];
    }

    /**
     * @param class-string<AbstractAbbreviationEvent> $class
     */
    #[DataProvider('provideEvents')]
    public function testEvent(string $class, string $type): void
    {
        $event = new $class('abbr-id', 'HTML', 'en', ['key' => 'value']);

        $this->assertSame($type, $event->getEventType());
        $this->assertSame('abbr-id', $event->getResourceId());
        $this->assertSame('abbr-id', $event->getAbbreviationId());
        $this->assertSame(Abbreviation::RESOURCE_KEY, $event->getResourceKey());
        $this->assertSame('HTML', $event->getResourceTitle());
        $this->assertSame('en', $event->getResourceLocale());
        $this->assertSame('en', $event->getResourceTitleLocale());
        $this->assertSame('en', $event->getLocale());
        $this->assertSame(['key' => 'value'], $event->getEventPayload());
        $this->assertSame(Abbreviation::SECURITY_CONTEXT, $event->getResourceSecurityContext());
    }

    public function testOptionalArguments(): void
    {
        $event = new AbbreviationRemovedEvent('abbr-id');

        $this->assertNull($event->getResourceTitle());
        $this->assertNull($event->getResourceLocale());
        $this->assertSame([], $event->getEventPayload());
    }
}
