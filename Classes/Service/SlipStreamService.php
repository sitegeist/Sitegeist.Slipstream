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


    protected const AT_CHARACTER_REPLACEMENT = ' __internal_at__';

    protected const AT_CHARACTER_SEARCH = ' @';

    protected const HTML_DOCTYPE = '<!DOCTYPE html>';

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

        $alteredHtml = $this->processHtml($html);

        if (is_null($alteredHtml)) {
            $response->getBody()->rewind();
            return $response;
        }

        $response = $response->withBody(Utils::streamFor($alteredHtml));
        if (!$this->debugMode) {
            $response = $response->withoutHeader('X-Slipstream');
        }

        return $response;
    }

    public function processHtml(string $html): ?string
    {
        if (!str_contains($html, 'data-slipstream')) {
            return null;
        }

        // Starting with Neos 7.3 it is possible to have attributes with @ (e.g. @click).
        // This replacement preserves attributes with @ character
        $html = str_replace(self::AT_CHARACTER_SEARCH, self::AT_CHARACTER_REPLACEMENT, $html);

        // detect html doctype
        $hasHtmlDoctype = substr($html, 0, 15) === self::HTML_DOCTYPE;

        // detect xml declaration
        $hasXmlDeclaration = substr($html, 0, 5) === '<?xml';

        // ignore xml parsing errors
        $useInternalErrorsBackup = libxml_use_internal_errors(true);

        $domDocument = new \DOMDocument('1.0', 'UTF-8');
        $success = $domDocument->loadHTML($hasXmlDeclaration ? $html : '<?xml encoding="UTF-8"?>' . $html);

        // in case of parsing errors return original body
        if (!$success) {
            if ($useInternalErrorsBackup !== true) {
                libxml_use_internal_errors($useInternalErrorsBackup);
            }
            return null;
        }

        $xPath = new \DOMXPath($domDocument);

        $sourceNodes = $xPath->query("//*[@data-slipstream]");
        if ($sourceNodes instanceof \DOMNodeList) {
            $nodesByTargetAndContentHash = [];

            /**
             * @var \DOMElement $node
             */
            foreach ($sourceNodes as $node) {
                /**
                 * @var string $content
                 */
                $content = $domDocument->saveHTML($node);
                $target = $node->getAttribute('data-slipstream');
                if (empty($target)) {
                    $target = '//head';
                } else if (str_starts_with($target, '#')) {
                    $target = '//*[@id="' . substr($target, 1) . '"]';
                }

                $prepend = $node->hasAttribute('data-slipstream-prepend');
                $contentHash = md5($content);

                /**
                 * @var \DOMElement $clone
                 */
                $clone = $node->cloneNode(true);
                if ($this->removeAttributes) {
                    $clone->removeAttribute('data-slipstream');
                    $clone->removeAttribute('data-slipstream-prepend');
                }
                $nodesByTargetAndContentHash[$target][$contentHash] = [
                    'prepend' => $prepend,
                    'node' => $clone
                ];

                /**
                 * @var \DOMElement $parentNode
                 */
                $parentNode =  $node->parentNode;
                // in debug mode leave a comment behind
                if ($this->debugMode) {
                    $comment = $domDocument->createComment(' ' . $content . ' ');
                    $parentNode->insertBefore($comment, $node);
                }

                $parentNode->removeChild($node);
            }


            foreach ($nodesByTargetAndContentHash as $targetPath => $configurations) {
                $query = $xPath->query($targetPath);
                if ($query && $query->count()) {
                    /**
                     * @var \DOMElement $targetNode
                     */
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
                        $nodeToInsertBefore = $targetNode->firstChild;
                    } else {
                        $nodeToInsertBefore = null;
                    }

                    // start comment
                    if ($this->debugMode) {
                        if ($hasPrepend) {
                            if ($nodeToInsertBefore) {
                                $targetNode->insertBefore($domDocument->createComment('slipstream-for: ' . $targetPath . ' prepend begin'), $nodeToInsertBefore);
                            } else {
                                $targetNode->appendChild($domDocument->createComment('slipstream-for: ' . $targetPath . ' prepend begin'));
                            }
                        }
                        if ($hasAppend) {
                            $targetNode->appendChild($domDocument->createComment('slipstream-for: ' . $targetPath . ' begin'));
                        }
                    }

                    foreach ($prepend as $node) {
                        if ($nodeToInsertBefore) {
                            $targetNode->insertBefore($node, $nodeToInsertBefore);
                        } else {
                            $targetNode->appendChild($node);
                        }
                    }
                    foreach ($append as $node) {
                        $targetNode->appendChild($node);
                    }

                    // end comment
                    if ($this->debugMode) {
                        if ($hasPrepend) {
                            if ($nodeToInsertBefore) {
                                $targetNode->insertBefore($domDocument->createComment('slipstream-for: ' . $targetPath . ' prepend end'), $nodeToInsertBefore);
                            } else {
                                $targetNode->appendChild($domDocument->createComment('slipstream-for: ' . $targetPath . ' prepend end'));
                            }
                        }
                        if ($hasAppend) {
                            $targetNode->appendChild($domDocument->createComment('slipstream-for: ' . $targetPath . ' end'));
                        }
                    }
                }
            }
        }

        if ($hasXmlDeclaration) {
            $alteredHtml = $domDocument->saveHTML();
        } else {
            $alteredHtml = $domDocument->saveHTML($domDocument->documentElement);
        }

        if ($alteredHtml === false) {
            return null;
        }

        // Replace the interal @ character with the original one
        $alteredHtml = str_replace(self::AT_CHARACTER_REPLACEMENT, self::AT_CHARACTER_SEARCH, $alteredHtml);

        if ($useInternalErrorsBackup !== true) {
            libxml_use_internal_errors($useInternalErrorsBackup);
        }

        return $hasHtmlDoctype ? self::HTML_DOCTYPE . $alteredHtml : $alteredHtml ;
    }
}
