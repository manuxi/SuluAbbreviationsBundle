<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class TranslationKeysTest extends TestCase
{
    private const SOURCE_DIR      = __DIR__ . '/../../../src';
    private const TRANSLATION_DIR = __DIR__ . '/../../../src/Resources/translations';

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideTranslatedKeys(): iterable
    {
        $seen    = [];
        $pattern = '/trans\(\s*[\'"](sulu_abbreviations?\.[A-Za-z0-9_.]+)[\'"]\s*(?:,\s*\[[^\]]*\]\s*,\s*[\'"](\w+)[\'"])?/';

        foreach ((new Finder())->files()->in(self::SOURCE_DIR)->name('*.php') as $file) {
            preg_match_all($pattern, $file->getContents(), $matches, \PREG_SET_ORDER);

            foreach ($matches as $match) {
                $domain = '' !== ($match[2] ?? '') ? $match[2] : 'messages';
                foreach (['de', 'en'] as $locale) {
                    $name = \sprintf('%s.%s %s', $domain, $locale, $match[1]);
                    if (isset($seen[$name])) {
                        continue;
                    }
                    $seen[$name] = true;

                    yield $name => [$match[1], $domain, $locale];
                }
            }
        }
    }

    #[DataProvider('provideTranslatedKeys')]
    public function testKeyExists(string $key, string $domain, string $locale): void
    {
        $catalogue = self::flatten(Yaml::parseFile(\sprintf('%s/%s.%s.yaml', self::TRANSLATION_DIR, $domain, $locale)));

        $this->assertArrayHasKey($key, $catalogue, \sprintf('Missing "%s" in %s.%s.yaml', $key, $domain, $locale));
    }

    /**
     * @param array<string, mixed> $tree
     *
     * @return array<string, mixed>
     */
    private static function flatten(array $tree, string $prefix = ''): array
    {
        $result = [];
        foreach ($tree as $key => $value) {
            $fullKey = '' === $prefix ? (string) $key : $prefix . '.' . $key;
            if (\is_array($value)) {
                $result += self::flatten($value, $fullKey);
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }
}
