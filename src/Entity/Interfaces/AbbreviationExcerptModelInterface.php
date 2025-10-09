<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity\Interfaces;

use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationExcerpt;
use Symfony\Component\HttpFoundation\Request;

interface AbbreviationExcerptModelInterface
{
    public function updateAbbreviationExcerpt(AbbreviationExcerpt $abbreviationExcerpt, Request $request): AbbreviationExcerpt;
}
