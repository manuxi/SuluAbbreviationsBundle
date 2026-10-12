<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Migration;

class PageReferenceResult
{
    public int $nodes = 0;

    public int $selections = 0;

    public int $links = 0;

    public int $unmapped = 0;

    public bool $mappingFound = true;
}
