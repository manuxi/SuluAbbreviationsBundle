<?php

namespace Manuxi\SuluAbbreviationsBundle\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;

trait CategoryTrait
{
    /**
     * @ORM\ManyToOne(targetEntity=CategoryInterface::class)
     * @ORM\JoinColumn(nullable=false)
     * @Serializer\Expose()
     */
    private ?Category $category = null;

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?CategoryInterface $category): self
    {
        $this->category = $category;
        return $this;
    }
}
