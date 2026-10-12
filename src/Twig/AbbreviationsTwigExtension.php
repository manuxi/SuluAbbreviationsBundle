<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Twig;

use Manuxi\SuluAbbreviationsBundle\EventSubscriber\AbbreviationCacheInvalidationSubscriber;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Resolver\AbbreviationResolver;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AbbreviationsTwigExtension extends AbstractExtension
{
    private const DEFAULT_LOCALE = 'en';

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $cache = [];

    public function __construct(
        private readonly AbbreviationRepository $abbreviationRepository,
        private readonly AbbreviationResolver $resolver,
        private readonly RequestStack $requestStack,
        private readonly bool $autoAbbr = false,
        private readonly ?ReferenceStoreInterface $referenceStore = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sulu_resolve_abbreviation', [$this, 'resolveAbbreviation']),
            new TwigFunction('sulu_get_abbreviations', [$this, 'getAbbreviations']),
        ];
    }

    public function getFilters(): array
    {
        if (!$this->autoAbbr) {
            return [];
        }

        return [
            new TwigFilter('abbr', [$this, 'wrapAbbreviations'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Returns the live content of the abbreviation if it is published in the given locale
     * (default: locale of the current request).
     *
     * @return array<string, mixed>|null
     */
    public function resolveAbbreviation(string $id, ?string $locale = null): ?array
    {
        return $this->resolver->resolve($id, $this->resolveLocale($locale));
    }

    /**
     * Returns the live content of the published abbreviations of the given locale
     * (default: locale of the current request).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAbbreviations(int $limit = 100, ?string $locale = null): array
    {
        $locale = $this->resolveLocale($locale);
        $ids    = $this->abbreviationRepository->findPublishedIds($locale, $limit);

        $this->referenceStore?->add(AbbreviationCacheInvalidationSubscriber::LIST_REFERENCE);

        return $this->resolver->resolveMany($ids, $locale);
    }

    /**
     * Wraps every known abbreviation of the text nodes in an abbr tag, tags like abbr, a and script are skipped.
     */
    public function wrapAbbreviations(?string $html, ?string $locale = null): string
    {
        if (null === $html || '' === $html) {
            return '';
        }

        $locale  = $this->resolveLocale($locale);
        $entries = $this->loadEntries($locale);
        if ([] === $entries) {
            return $html;
        }

        $titles = [];
        foreach ($entries as $entry) {
            $name        = $entry['name']        ?? null;
            $explanation = $entry['explanation'] ?? null;
            if (\is_string($name) && '' !== $name && \is_string($explanation) && '' !== $explanation) {
                $titles[$name] = $explanation;
            }
        }

        if ([] === $titles) {
            return $html;
        }

        uksort($titles, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $pattern = '/(?<![\p{L}\p{N}])(' . implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), array_keys($titles))) . ')(?![\p{L}\p{N}])/u';

        $parts = preg_split('/(<[^>]*>)/', $html, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
        if (false === $parts) {
            return $html;
        }

        $result = '';
        $skip   = 0;
        foreach ($parts as $part) {
            if (1 === preg_match('/^<\s*(abbr|a|script|style|code|pre)\b/i', $part)) {
                ++$skip;
                $result .= $part;

                continue;
            }

            if (1 === preg_match('/^<\s*\/\s*(abbr|a|script|style|code|pre)\b/i', $part)) {
                $skip = max(0, $skip - 1);
                $result .= $part;

                continue;
            }

            if ($skip > 0 || str_starts_with($part, '<')) {
                $result .= $part;

                continue;
            }

            $result .= preg_replace_callback(
                $pattern,
                static fn (array $match): string => \sprintf(
                    '<abbr title="%s">%s</abbr>',
                    htmlspecialchars($titles[$match[1]], \ENT_QUOTES),
                    $match[1]
                ),
                $part
            ) ?? $part;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadEntries(string $locale): array
    {
        return $this->cache[$locale] ??= $this->getAbbreviations(1000, $locale);
    }

    private function resolveLocale(?string $locale): string
    {
        if (null !== $locale && '' !== $locale) {
            return $locale;
        }

        return $this->requestStack->getCurrentRequest()?->getLocale() ?? self::DEFAULT_LOCALE;
    }
}
