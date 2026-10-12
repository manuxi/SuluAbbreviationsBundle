<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Migration;

class MigrationResult
{
    public int $migrated = 0;

    public int $skipped = 0;

    public int $translations = 0;

    public int $published = 0;

    public int $routesUpdated = 0;

    public int $routesRemoved = 0;

    public int $routesCreated = 0;

    public bool $legacyTablesFound = true;

    /**
     * @var array<int, int|string> old id => new uuid
     */
    public array $idMap = [];
}
