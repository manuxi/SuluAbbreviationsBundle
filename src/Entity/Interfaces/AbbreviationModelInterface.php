<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity\Interfaces;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Symfony\Component\HttpFoundation\Request;

interface AbbreviationModelInterface
{
    public function get(int $id, Request $request = null): Abbreviation;
    public function delete(Abbreviation $entity): void;
    public function create(Request $request): Abbreviation;
    public function update(int $id, Request $request): Abbreviation;
    public function publish(int $id, Request $request): Abbreviation;
    public function unpublish(int $id, Request $request): Abbreviation;
}
