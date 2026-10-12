<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use Sulu\Bundle\ContentBundle\Content\Domain\Model\AuthorInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\AuthorTrait;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\ContentRichEntityInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentTrait;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\ExcerptInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\ExcerptTrait;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\RoutableInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\RoutableTrait;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\SeoInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\SeoTrait;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\TemplateInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\TemplateTrait;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\WorkflowInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\WorkflowTrait;

/**
 * @implements DimensionContentInterface<Abbreviation>
 */
class AbbreviationDimensionContent implements DimensionContentInterface, TemplateInterface, RoutableInterface, SeoInterface, ExcerptInterface, AuthorInterface, WorkflowInterface
{
    use AuthorTrait;
    use DimensionContentTrait;
    use ExcerptTrait;
    use RoutableTrait;
    use SeoTrait;
    use TemplateTrait {
        setTemplateData as parentSetTemplateData;
    }
    use WorkflowTrait;

    protected ?int $id = null;

    protected Abbreviation $abbreviation;

    protected ?string $name = null;

    public function __construct(Abbreviation $abbreviation)
    {
        $this->abbreviation = $abbreviation;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResource(): ContentRichEntityInterface
    {
        return $this->abbreviation;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getTitle(): ?string
    {
        return $this->name;
    }

    /**
     * @param array<string, mixed> $templateData
     */
    public function setTemplateData(array $templateData): void
    {
        if (\array_key_exists('name', $templateData)) {
            $this->name = \is_string($templateData['name']) ? $templateData['name'] : null;
        }

        $this->parentSetTemplateData($templateData);
    }

    public static function getTemplateType(): string
    {
        return Abbreviation::TEMPLATE_TYPE;
    }

    public static function getResourceKey(): string
    {
        return Abbreviation::RESOURCE_KEY;
    }
}
