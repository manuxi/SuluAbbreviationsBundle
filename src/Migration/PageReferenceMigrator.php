<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Migration;

use Doctrine\DBAL\Connection;
use PHPCR\NodeInterface;
use PHPCR\PropertyInterface;
use PHPCR\PropertyType;
use PHPCR\SessionInterface;
use Sulu\Component\Content\Metadata\BlockMetadata;
use Sulu\Component\Content\Metadata\Factory\StructureMetadataFactoryInterface;
use Sulu\Component\Content\Metadata\ItemMetadata;
use Sulu\Component\Content\Metadata\PropertyMetadata;

/**
 * Rewrites the references to abbreviations in the PHPCR content (pages, snippets and everything below /cmf):
 * the integer ids of the selection content types and of text editor / link properties are replaced by the
 * uuids of the migrated abbreviations and the link provider "abbreviation" becomes "abbreviations".
 */
class PageReferenceMigrator
{
    private const MULTIPLE = 'abbreviation_selection';
    private const SINGLE   = 'single_abbreviation_selection';

    private const LEGACY_PROVIDER = 'abbreviation';
    private const PROVIDER        = 'abbreviations';

    public function __construct(
        private readonly Connection $connection,
        private readonly StructureMetadataFactoryInterface $structureMetadataFactory,
        private readonly SessionInterface $defaultSession,
        private readonly SessionInterface $liveSession,
    ) {
    }

    public function migrate(bool $dryRun = false): PageReferenceResult
    {
        $result = new PageReferenceResult();

        $idMap = $this->loadIdMap();
        if ([] === $idMap) {
            $result->mappingFound = false;

            return $result;
        }

        $selectionProperties = $this->collectSelectionProperties();

        $sessions = [$this->defaultSession];
        if ($this->liveSession->getWorkspace()->getName() !== $this->defaultSession->getWorkspace()->getName()) {
            $sessions[] = $this->liveSession;
        }

        foreach ($sessions as $session) {
            $this->migrateSession($session, $idMap, $selectionProperties, $result);

            if ($dryRun) {
                $session->refresh(false);
            } else {
                $session->save();
            }
        }

        return $result;
    }

    /**
     * @param array<int, string>    $idMap
     * @param array<string, string> $selectionProperties
     */
    private function migrateSession(SessionInterface $session, array $idMap, array $selectionProperties, PageReferenceResult $result): void
    {
        $queryManager = $session->getWorkspace()->getQueryManager();
        $query        = $queryManager->createQuery("SELECT * FROM [nt:unstructured] AS node WHERE ISDESCENDANTNODE(node, '/cmf')", 'JCR-SQL2');

        foreach ($query->execute()->getNodes() as $node) {
            ++$result->nodes;

            /** @var PropertyInterface $property */
            foreach ($node->getProperties('i18n:*') as $property) {
                $this->migrateProperty($node, $property, $idMap, $selectionProperties, $result);
            }
        }
    }

    /**
     * @param array<int, string>    $idMap
     * @param array<string, string> $selectionProperties
     */
    private function migrateProperty(NodeInterface $node, PropertyInterface $property, array $idMap, array $selectionProperties, PageReferenceResult $result): void
    {
        $name  = $property->getName();
        $value = $property->getValue();

        $kind = $this->getSelectionKind($name, $selectionProperties);
        if (null !== $kind && PropertyType::LONG === $property->getType()) {
            $converted = $this->convertIds($value, $idMap, $result);

            if (null !== $converted) {
                $node->setProperty($name, $converted, PropertyType::STRING);
                ++$result->selections;
            }

            return;
        }

        if (PropertyType::STRING !== $property->getType()) {
            return;
        }

        if (\is_array($value)) {
            $changed = false;
            foreach ($value as $key => $item) {
                if (\is_string($item)) {
                    $rewritten = $this->rewriteString($item, $idMap, $selectionProperties, $result);
                    if (null !== $rewritten) {
                        $value[$key] = $rewritten;
                        $changed     = true;
                    }
                }
            }

            if ($changed) {
                $node->setProperty($name, $value, PropertyType::STRING);
            }

            return;
        }

        if (\is_string($value)) {
            $rewritten = $this->rewriteString($value, $idMap, $selectionProperties, $result);
            if (null !== $rewritten) {
                $node->setProperty($name, $rewritten, PropertyType::STRING);
            }
        }
    }

    /**
     * @param array<string, string> $selectionProperties
     */
    private function getSelectionKind(string $propertyName, array $selectionProperties): ?string
    {
        if (1 !== preg_match('/^i18n:[A-Za-z]{2,3}(?:_[A-Za-z0-9]+)?-(.+)$/', $propertyName, $matches)) {
            return null;
        }

        $segments = explode('-', $matches[1]);
        $last     = (string) preg_replace('/(#\d+)+$/', '', end($segments));

        return $selectionProperties[$last] ?? null;
    }

    /**
     * @param array<int, string> $idMap
     *
     * @return string|string[]|null
     */
    private function convertIds(mixed $value, array $idMap, PageReferenceResult $result): string|array|null
    {
        if (\is_array($value)) {
            $changed   = false;
            $converted = [];
            foreach ($value as $id) {
                $mapped = $this->mapId($id, $idMap, $result);
                if (null !== $mapped) {
                    $changed = true;
                }

                $converted[] = $mapped ?? (string) $id;
            }

            return $changed ? $converted : null;
        }

        return $this->mapId($value, $idMap, $result);
    }

    /**
     * @param array<int, string> $idMap
     */
    private function mapId(mixed $id, array $idMap, PageReferenceResult $result): ?string
    {
        if (!\is_int($id) && !(\is_string($id) && ctype_digit($id))) {
            return null;
        }

        $mapped = $idMap[(int) $id] ?? null;
        if (null === $mapped) {
            ++$result->unmapped;
        }

        return $mapped;
    }

    /**
     * @param array<int, string>    $idMap
     * @param array<string, string> $selectionProperties
     */
    private function rewriteString(string $value, array $idMap, array $selectionProperties, PageReferenceResult $result): ?string
    {
        if (!str_contains($value, self::LEGACY_PROVIDER) && !$this->containsSelectionKey($value, $selectionProperties)) {
            return null;
        }

        $trimmed = ltrim($value);
        if ('' !== $trimmed && \in_array($trimmed[0], ['{', '['], true)) {
            $decoded = json_decode($value, true);

            if (\is_array($decoded)) {
                $changed = $this->rewriteJson($decoded, $idMap, $selectionProperties, $result);

                return $changed ? (string) json_encode($decoded, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) : null;
            }
        }

        $count     = 0;
        $rewritten = preg_replace_callback(
            '/<sulu-link\b[^>]*>/i',
            static function (array $match) use ($idMap, $result, &$count): string {
                $tag = $match[0];

                if (1    !== preg_match('/\bprovider="' . self::LEGACY_PROVIDER . '"/', $tag)
                    || 1 !== preg_match('/\bhref="(\d+)"/', $tag, $href)) {
                    return $tag;
                }

                $uuid = $idMap[(int) $href[1]] ?? null;
                if (null === $uuid) {
                    ++$result->unmapped;

                    return $tag;
                }

                ++$count;

                $tag = (string) preg_replace('/\bprovider="' . self::LEGACY_PROVIDER . '"/', 'provider="' . self::PROVIDER . '"', $tag);

                return (string) preg_replace('/\bhref="\d+"/', 'href="' . $uuid . '"', $tag);
            },
            $value
        );

        if (0 === $count || null === $rewritten) {
            return null;
        }

        $result->links += $count;

        return $rewritten;
    }

    /**
     * @param array<string, string> $selectionProperties
     */
    private function containsSelectionKey(string $value, array $selectionProperties): bool
    {
        foreach (array_keys($selectionProperties) as $name) {
            if (str_contains($value, '"' . $name . '"')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int|string, mixed> $data
     * @param array<int, string>       $idMap
     * @param array<string, string>    $selectionProperties
     */
    private function rewriteJson(array &$data, array $idMap, array $selectionProperties, PageReferenceResult $result): bool
    {
        $changed = false;

        if (self::LEGACY_PROVIDER === ($data['provider'] ?? null) && isset($data['href'])) {
            $uuid = \is_scalar($data['href']) && ctype_digit((string) $data['href']) ? ($idMap[(int) $data['href']] ?? null) : null;

            if (null === $uuid) {
                ++$result->unmapped;
            } else {
                $data['provider'] = self::PROVIDER;
                $data['href']     = $uuid;
                ++$result->links;
                $changed = true;
            }
        }

        foreach ($data as $key => &$item) {
            if (\is_string($key) && isset($selectionProperties[$key]) && (\is_int($item) || \is_array($item))) {
                $converted = $this->convertIds($item, $idMap, $result);

                if (null !== $converted) {
                    $item = $converted;
                    ++$result->selections;
                    $changed = true;
                }

                continue;
            }

            if (\is_array($item) && $this->rewriteJson($item, $idMap, $selectionProperties, $result)) {
                $changed = true;
            }
        }
        unset($item);

        return $changed;
    }

    /**
     * @return array<int, string>
     */
    private function loadIdMap(): array
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['abbr_abbreviations'])) {
            return [];
        }

        $map = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, legacy_id FROM abbr_abbreviations WHERE legacy_id IS NOT NULL') as $row) {
            $map[(int) $row['legacy_id']] = (string) $row['id'];
        }

        return $map;
    }

    /**
     * @return array<int|string, string> property name => content type
     */
    private function collectSelectionProperties(): array
    {
        $properties = [];

        foreach ($this->structureMetadataFactory->getStructureTypes() as $type) {
            foreach ($this->structureMetadataFactory->getStructures($type) as $structure) {
                $this->collect($structure->getChildren(), $properties);
            }
        }

        return $properties;
    }

    /**
     * @param ItemMetadata[]        $items
     * @param array<int|string, string> $properties
     */
    private function collect(array $items, array &$properties): void
    {
        foreach ($items as $item) {
            if ($item instanceof BlockMetadata) {
                foreach ($item->getComponents() as $component) {
                    $this->collect($component->getChildren(), $properties);
                }

                continue;
            }

            if ($item instanceof PropertyMetadata && \in_array($item->getType(), [self::MULTIPLE, self::SINGLE], true)) {
                $properties[$item->getName()] ??= $item->getType();

                continue;
            }

            $this->collect($item->getChildren(), $properties);
        }
    }
}
