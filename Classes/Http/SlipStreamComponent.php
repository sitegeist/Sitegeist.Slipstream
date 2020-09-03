<?php
namespace Sitegeist\Slipstream\Http;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use function GuzzleHttp\Psr7\stream_for;

class SlipStreamComponent implements ComponentInterface
{

    /**
     * Just call makeStandardsCompliant on the Response for now
     *
     * @param ComponentContext $componentContext
     * @return void
     */
    public function handle(ComponentContext $componentContext)
    {
        $response = $componentContext->getHttpResponse();
        $body = $response->getBody();

        // magic happens here
        $domDocument = new \DOMDocument('1.0', 'UTF-8');

        // ignore parsing errors
        $useInternalErrorsBackup = libxml_use_internal_errors(true);
        $domDocument->loadHTML((substr($body, 0, 5) === '<?xml') ? $body : '<?xml encoding="UTF-8"?>' . $body);
        $xPath = new \DOMXPath($domDocument);

        if ($useInternalErrorsBackup !== true) {
            libxml_use_internal_errors($useInternalErrorsBackup);
        }

        $response = $response->withBody(stream_for($domDocument->saveHTML()));
        $response = $response->withAddedHeader("yolo", "yolo");

        $componentContext->replaceHttpResponse($response);
    }
}
