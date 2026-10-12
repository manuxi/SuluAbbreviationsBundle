<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity;

use Sulu\Bundle\ContentBundle\Content\Domain\Model\ContentRichEntityInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\ContentRichEntityTrait;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ContentRichEntityInterface<AbbreviationDimensionContent>
 */
class Abbreviation implements ContentRichEntityInterface
{
    /**
     * @phpstan-use ContentRichEntityTrait<AbbreviationDimensionContent>
     */
    use ContentRichEntityTrait;

    public const RESOURCE_KEY      = 'abbreviations';
    public const LIST_KEY          = 'abbreviations';
    public const FORM_KEY          = 'abbreviation_details';
    public const TEMPLATE_TYPE     = 'abbreviation';
    public const DEFAULT_TEMPLATE  = 'abbreviation_default';
    public const SECURITY_CONTEXT  = 'sulu.abbreviations.abbreviations';

    private string $id;

    private ?int $legacyId = null;

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? Uuid::v7()->toRfc4122();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLegacyId(): ?int
    {
        return $this->legacyId;
    }

    public function setLegacyId(?int $legacyId): self
    {
        $this->legacyId = $legacyId;

        return $this;
    }

    public function createDimensionContent(): DimensionContentInterface
    {
        return new AbbreviationDimensionContent($this);
    }
}
