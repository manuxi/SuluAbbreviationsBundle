<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Support;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ArrayViewHandler implements ViewHandlerInterface
{
    public ?View $lastView = null;

    public function supports(string $format): bool
    {
        return true;
    }

    public function registerHandler(string $format, callable $callable): void
    {
    }

    public function createRedirectResponse(View $view, string $location, string $format): Response
    {
        return new Response('', 302, ['Location' => $location]);
    }

    public function createResponse(View $view, Request $request, string $format): Response
    {
        return $this->handle($view, $request);
    }

    public function handle(View $view, ?Request $request = null): Response
    {
        $this->lastView = $view;

        $data = $view->getData();

        return new Response(
            null === $data ? '' : (string) json_encode($data instanceof \JsonSerializable ? $data->jsonSerialize() : $data),
            $view->getStatusCode() ?? 200
        );
    }
}
