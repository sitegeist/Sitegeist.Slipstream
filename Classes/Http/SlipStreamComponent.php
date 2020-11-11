<?php
namespace Sitegeist\Slipstream\Http;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Sitegeist\Slipstream\Service\SlipStreamService;

class SlipStreamComponent implements ComponentInterface
{

    /**
     * @var bool
     * @Flow\InjectConfiguration(path="debugMode")
     */
    protected $debugMode;

    /**
     * @var SlipStreamService
     * @Flow\Inject
     */
    protected $slipStramService;

    /**
     * Just call makeStandardsCompliant on the Response for now
     *
     * @param ComponentContext $componentContext
     * @return void
     */
    public function handle(ComponentContext $componentContext)
    {
        $response = $componentContext->getHttpResponse();
        if ($response->getHeaderLine('X-Slipstream') == 'enabled') {
            $response = $this->slipStramService->processResponse($response);
            $componentContext->replaceHttpResponse($response);
        }
    }
}
