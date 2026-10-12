<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Traits;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;

trait AbbreviationFactoryTrait
{
    private function createAbbreviation(int $id, string $locale = 'en', bool $published = true, string $name = 'Name'): Abbreviation
    {
        $abbreviation = new Abbreviation();
        $abbreviation->setLocale($locale);
        $abbreviation->setName($name);
        $abbreviation->setPublished($published);

        $property = new \ReflectionProperty(Abbreviation::class, 'id');
        $property->setValue($abbreviation, $id);

        return $abbreviation;
    }
}
