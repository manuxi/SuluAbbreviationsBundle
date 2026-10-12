<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Content;

use Manuxi\SuluAbbreviationsBundle\EventSubscriber\AbbreviationCacheInvalidationSubscriber;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\SmartContent\Provider\ContentDataProvider;

/**
 * Smart content data provider which also tags the response as list of abbreviations, so that a newly published
 * abbreviation purges the cached pages which display a list.
 */
class AbbreviationsDataProvider extends ContentDataProvider
{
    protected function decorateResourceItems(array $data, $locale): array
    {
        $this->referenceStore?->add(AbbreviationCacheInvalidationSubscriber::LIST_REFERENCE);

        return parent::decorateResourceItems($data, $locale);
    }
}
