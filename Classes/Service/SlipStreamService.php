<?php
namespace Sitegeist\Slipstream\Service;

use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Utils;

class SlipStreamService
{

    /**
     * @var bool
     * @Flow\InjectConfiguration(path="debugMode")
     */
    protected $debugMode;

    /**
     * @var bool
     * @Flow\InjectConfiguration(path="removeSlipstreamAttributes")
     */
    protected $removeAttributes;

    /**
     * Modify the given response and return a new one with the data-slipstream elements moved to
     * the target location
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    public function processResponse(ResponseInterface $response): ResponseInterface
    {
        $html = $response->getBody()->getContents();

        // detect xml or html declaration
        $hasXmlDeclaration = (substr($html, 0, 5) === '<?xml') || (substr($html, 0, 15) === '<!DOCTYPE html>');

        // ignore xml parsing errors
        $useInternalErrorsBackup = libxml_use_internal_errors(true);

        $domDocument = new \DOMDocument('1.0', 'UTF-8');
        $success = $domDocument->loadHTML($hasXmlDeclaration ? $html : '<?xml encoding="UTF-8"?>' . $html);

        // in case of parsing errors return original body
        if (!$success) {
            $response->getBody()->rewind();
            return $response;
        }

        $xPath = new \DOMXPath($domDocument);

        $sourceNodes = $xPath->query("//*[@data-slipstream]");
        $nodesByTargetAndContentHash = [];
        foreach ($sourceNodes as $node) {
            /**
             * @var \DOMNode $node
             */
            $content = $domDocument->saveHTML($node);
            $target = $node->getAttribute('data-slipstream');
            if (empty($target)) {
                $target = '//head';
            }

            $prepend = $node->hasAttribute('data-slipstream-prepend');
            $contentHash = md5($content);
            $clone = $node->cloneNode(true);
            if ($this->removeAttributes) {
                $clone->removeAttribute('data-slipstream');
                $clone->removeAttribute('data-slipstream-prepend');
            }
            $nodesByTargetAndContentHash[$target][$contentHash] = [
                'prepend' => $prepend,
                'node' => $clone
            ];

            // in debug mode leave a comment behind
            if ($this->debugMode) {
                $comment = $domDocument->createComment(' ' . $content . ' ');
                $node->parentNode->insertBefore($comment, $node);
            }

            $node->parentNode->removeChild($node);
        }

        foreach ($nodesByTargetAndContentHash as $targetPath => $configurations) {
            $query = $xPath->query($targetPath);
            if ($query && $query->count()) {
                $targetNode = $query->item(0);

                $prepend = [];
                $append = [];
                foreach ($configurations as $config) {
                    if ($config['prepend']) {
                        $prepend[] = $config['node'];
                    } else {
                        $append[] = $config['node'];
                    }
                }
                $hasPrepend = count($prepend);
                $hasAppend = count($append);

                if ($hasPrepend) {
                    $firstChildNode = $targetNode->firstChild;
                }

                if ($this->debugMode) {
                    $comment = 'slipstream-for: ' . $targetPath . ' ';
                    if ($hasPrepend) {
                        $targetNode->insertBefore($domDocument->createComment($comment . 'prepend begin'), $firstChildNode);
                    }
                    if ($hasAppend) {
                        $targetNode->appendChild($domDocument->createComment($comment . 'begin'));
                    }
                }

                foreach ($prepend as $node) {
                    $targetNode->insertBefore($node, $firstChildNode);
                }
                foreach ($append as $node) {
                    $targetNode->appendChild($node);
                }

                if ($this->debugMode) {
                    if ($hasPrepend) {
                        $targetNode->insertBefore($domDocument->createComment($comment . 'prepend end'), $firstChildNode);
                    }
                    if ($hasAppend) {
                        $targetNode->appendChild($domDocument->createComment($comment . 'end'));
                    }
                }
            }
        }

        if ($hasXmlDeclaration) {
            $alteredBody = $domDocument->saveHTML();
        } else {
            $alteredBody = $domDocument->saveHTML($domDocument->documentElement);
        }

        $response = $response->withBody(Utils::streamFor($alteredBody));
        if (!$this->debugMode) {
            $response = $response->withoutHeader('X-Slipstream');
        }

        // restore previous parsing behavior
        if ($useInternalErrorsBackup !== true) {
            libxml_use_internal_errors($useInternalErrorsBackup);
        }

        return $response;
    }
}
