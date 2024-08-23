<?php

namespace Manuxi\SuluAbbreviationsBundle\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

trait LinkTrait
{

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $link = null;

    public function getLink(): ?array
    {
        return $this->link;
    }

    public function setLink(?array $link): self
    {
        $this->link = $link;
        return $this;
    }
}
