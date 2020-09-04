<?php
namespace Sitegeist\Slipstream\Http;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use function GuzzleHttp\Psr7\stream_for;

class SlipStreamComponent implements ComponentInterface
{

    protected $debugMode = true;

    /**
     * Just call makeStandardsCompliant on the Response for now
     *
     * @param ComponentContext $componentContext
     * @return void
     */
    public function handle(ComponentContext $componentContext)
    {
        $response = $componentContext->getHttpResponse();

        if ($response->getHeaderLine('X-Slipstream-Enabled') !== 'true') {
            return;
        }

        $html = $response->getBody()->getContents();

        // detect xml declaration
        $hasXmlDeclaration = (substr($html, 0, 5) === '<?xml');

        // ignore xml parsing errors
        $useInternalErrorsBackup = libxml_use_internal_errors(true);

        $domDocument = new \DOMDocument('1.0', 'UTF-8');
        $success = $domDocument->loadHTML($hasXmlDeclaration ? $html : '<?xml encoding="UTF-8"?>' . $html);

        // in case of parsing errors return original body
        if (!$success) {
            $response->getBody()->rewind();
            return;
        }

        $xPath = new \DOMXPath($domDocument);

        $slipstreamNodes = $xPath->query("//*[@data-slipstream-anchor]");
        $nodesToMove = [];
        foreach ($slipstreamNodes as $node) {
            /**
             * @var \DOMNode $node
             */
            $content = $domDocument->saveHTML($node);
            $contentHash = md5($content);
            $nodesToMove[$contentHash] = $node->cloneNode(true);

            // in debug mode leave a comment behind
            if ($this->debugMode) {
                $comment = $domDocument->createComment($content);
                $node->parentNode->insertBefore($comment, $node);
            }

            $node->parentNode->removeChild($node);
        }

        $anchorChildren = [];
        foreach ($nodesToMove as $node) {
            /**
             * @var \DOMNode $node
             */
            $anchorName = $node->getAttribute('data-slipstream-anchor');
            $node->removeAttribute('data-slipstreamAnchor');
            $anchorChildren[$anchorName][] = $node;
        }

        $anchorNodes = $xPath->query("//meta[@type='slipstreamAnchor']");
        foreach ($anchorNodes as $node) {
            /**
             * @var \DOMNode $node
             */
            $anchorName = $node->getAttribute('value');
            if (array_key_exists($anchorName, $anchorChildren)) {
                foreach ($anchorChildren[$anchorName] as $anchorNode) {
                    $node->parentNode->insertBefore($anchorNode, $node);
                }
            }
            $node->parentNode->removeChild($node);
        }


        $alteredBody = $domDocument->saveHTML();

        // remove the xml declaration that was only added for the dom parser
        if (!$hasXmlDeclaration) {
           $alteredBody = substr($alteredBody, 40);
        }

        $response = $response->withBody(stream_for($alteredBody));
        $response = $response->withAddedHeader("yolo", "yolo");

        $componentContext->replaceHttpResponse($response);

        // restore previous parsing behavior
        if ($useInternalErrorsBackup !== true) {
            libxml_use_internal_errors($useInternalErrorsBackup);
        }
    }
}
