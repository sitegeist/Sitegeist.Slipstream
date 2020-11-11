<?php

namespace Sitegeist\Slipstream\Middleware;

use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sitegeist\Slipstream\Service\SlipStreamService;

class SlipstreamMiddleware implements MiddlewareInterface
{
    /**
     * @var SlipStreamService
     * @Flow\Inject
     */
    protected $slipStramService;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        $response = $next->handle($request);
        if ($response->getHeaderLine('X-Slipstream') == 'enabled') {
            return $this->slipStramService->processResponse($response);
        } else {
            return $response;
        }
    }
}
