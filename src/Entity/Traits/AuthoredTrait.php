<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity\Traits;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

trait AuthoredTrait
{

    protected ?DateTime $authored = null;

    public function getAuthored(): ?DateTime
    {
        return $this->authored;
    }

    public function setAuthored(DateTime $authored): self
    {
        $this->authored = $authored;
        return $this;
    }


}
