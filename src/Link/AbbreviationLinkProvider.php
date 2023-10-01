<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Link;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkConfiguration;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkConfigurationBuilder;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkItem;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AbbreviationLinkProvider implements LinkProviderInterface
{
    private AbbreviationRepository $abbreviationRepository;
    private TranslatorInterface $translator;

    public function __construct(AbbreviationRepository $abbreviationRepository, TranslatorInterface $translator)
    {
        $this->abbreviationRepository = $abbreviationRepository;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): LinkConfiguration
    {
        return LinkConfigurationBuilder::create()
            ->setTitle($this->translator->trans('sulu_abbreviations.abbreviation',[],'admin'))
            ->setResourceKey(Abbreviation::RESOURCE_KEY) // the resourceKey of the entity that should be loaded
            ->setListAdapter('table')
            ->setDisplayProperties(['title'])
            ->setOverlayTitle($this->translator->trans('sulu_abbreviations.abbreviation',[],'admin'))
            ->setEmptyText($this->translator->trans('sulu_abbreviations.empty_abbreviationlist',[],'admin'))
            ->setIcon('su-enter')
            ->getLinkConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function preload(array $hrefs, $locale, $published = true): array
    {
        if (0 === count($hrefs)) {
            return [];
        }

        $result = [];
        $elements = $this->abbreviationRepository->findBy(['id' => $hrefs, 'locale' => $locale]); // load items by id
        foreach ($elements as $element) {
            $result[] = new LinkItem($element->getId(), $element->getName(), $element->getRoutePath(), $element->isPublished()); // create link-item foreach item
        }

        return $result;
    }
}
