<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity\Interfaces;

use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationSeo;
use Symfony\Component\HttpFoundation\Request;

interface AbbreviationSeoModelInterface
{
    public function updateAbbreviationSeo(AbbreviationSeo $abbreviationSeo, Request $request): AbbreviationSeo;
}
