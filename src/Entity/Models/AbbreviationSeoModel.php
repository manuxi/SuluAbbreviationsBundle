<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity\Models;

use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationSeo;
use Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\AbbreviationSeoModelInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\ArrayPropertyTrait;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationSeoRepository;
use Symfony\Component\HttpFoundation\Request;

class AbbreviationSeoModel implements AbbreviationSeoModelInterface
{
    use ArrayPropertyTrait;

    private AbbreviationSeoRepository $abbreviationSeoRepository;

    public function __construct(
        AbbreviationSeoRepository $abbreviationSeoRepository
    ) {
        $this->abbreviationSeoRepository = $abbreviationSeoRepository;
    }

    public function updateAbbreviationSeo(AbbreviationSeo $abbreviationSeo, Request $request): AbbreviationSeo
    {
        $abbreviationSeo = $this->mapDataToAbbreviationSeo($abbreviationSeo, $request->request->all()['ext']['seo']);
        return $this->abbreviationSeoRepository->save($abbreviationSeo);
    }

    private function mapDataToAbbreviationSeo(AbbreviationSeo $entity, array $data): AbbreviationSeo
    {
        $locale = $this->getProperty($data, 'locale');
        if ($locale) {
            $entity->setLocale($locale);
        }
        $title = $this->getProperty($data, 'title');
        if ($title) {
            $entity->setTitle($title);
        }
        $description = $this->getProperty($data, 'description');
        if ($description) {
            $entity->setDescription($description);
        }
        $keywords = $this->getProperty($data, 'keywords');
        if ($keywords) {
            $entity->setKeywords($keywords);
        }
        $canonicalUrl = $this->getProperty($data, 'canonicalUrl');
        if ($canonicalUrl) {
            $entity->setCanonicalUrl($canonicalUrl);
        }
        $noIndex = $this->getProperty($data, 'noIndex');
        if ($noIndex) {
            $entity->setNoIndex($noIndex);
        }
        $noFollow = $this->getProperty($data, 'noFollow');
        if ($noFollow) {
            $entity->setNoFollow($noFollow);
        }
        $hideInSitemap = $this->getProperty($data, 'hideInSitemap');
        if ($hideInSitemap) {
            $entity->setHideInSitemap($hideInSitemap);
        }
        return $entity;
    }
}
