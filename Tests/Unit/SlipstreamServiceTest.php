<?php
declare(strict_types=1);

namespace Sitegeist\Slipstream\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionProperty;
use Sitegeist\Slipstream\Service\SlipStreamService;

class SlipstreamServiceTest extends TestCase
{

    /**
     * @test
     */
    public function nonHtmlContentIsRejected() {
        $service = $this->createService();
        $input = <<<EOF
            {
                "foo": {
                    "bar" : "baz"
                }
            }
            EOF;

        $result = $service->processHtml($input);
        $this->assertSame(
            null,
            $result
        );
    }


    /**
     * @test
     */
    public function moveToHeaderByDefaultWorks() {
        $service = $this->createService();
        $input = <<<EOF
            <html>
                <head></head>
                <body>
                    foo<div data-slipstream>slipped content</div>bar
                </body>
            </html>
            EOF;
        $output = <<<EOF
            <html>
                <head><div data-slipstream>slipped content</div></head>
                <body>
                    foobar
                </body>
            </html>
            EOF;

        $result = $service->processHtml($input);
        $this->assertSame(
            $output,
            $result
        );
    }

    /**
     * @test
     */
    public function deduplicationWorks() {
        $service = $this->createService();
        $input = <<<EOF
            <html>
                <head></head>
                <body>
                    foo<div data-slipstream>slipped content</div>bar<div data-slipstream>slipped content</div>baz<div data-slipstream>other slipped content</div>bam
                </body>
            </html>
            EOF;
        $output = <<<EOF
            <html>
                <head><div data-slipstream>slipped content</div><div data-slipstream>other slipped content</div></head>
                <body>
                    foobarbazbam
                </body>
            </html>
            EOF;

        $result = $service->processHtml($input);
        $this->assertSame(
            $output,
            $result
        );
    }

    /**
     * @test
     */
    public function debugInformationIsAdded() {
        $service = $this->createService(true);
        $input = <<<EOF
            <html>
                <head></head>
                <body>
                    foo<div data-slipstream>slipped content</div>bar
                </body>
            </html>
            EOF;
        $output = <<<EOF
            <html>
                <head><!--slipstream-for: //head begin--><div data-slipstream>slipped content</div><!--slipstream-for: //head end--></head>
                <body>
                    foo<!-- <div data-slipstream>slipped content</div> -->bar
                </body>
            </html>
            EOF;

        $result = $service->processHtml($input);
        $this->assertSame(
            $output,
            $result
        );
    }

    /**
     * @test
     */
    public function attributesAreRemovedAdded() {
        $service = $this->createService(false, true);
        $input = <<<EOF
            <html>
                <head></head>
                <body>
                    foo<div data-slipstream>slipped content</div>bar
                </body>
            </html>
            EOF;
        $output = <<<EOF
            <html>
                <head><div>slipped content</div></head>
                <body>
                    foobar
                </body>
            </html>
            EOF;

        $result = $service->processHtml($input);
        $this->assertSame(
            $output,
            $result
        );
    }

    /**
     * @test
     */
    public function targetSpecificationWorks() {
        $service = $this->createService();
        $input = <<<EOF
            <html>
                <head></head>
                <body>
                    foo<div data-slipstream="//body">slipped content</div>bar
                </body>
            </html>
            EOF;
        $output = <<<EOF
            <html>
                <head></head>
                <body>
                    foobar
                <div data-slipstream="//body">slipped content</div></body>
            </html>
            EOF;

        $result = $service->processHtml($input);
        $this->assertSame(
            $output,
            $result
        );
    }

    /**
     * @test
     */
    public function targetSpecificationWorksWithPrepend() {
        $service = $this->createService();
        $input = <<<EOF
            <html>
                <head></head>
                <body>
                    foo<div data-slipstream="//body" data-slipstream-prepend>slipped content</div>bar
                </body>
            </html>
            EOF;
        $output = <<<EOF
            <html>
                <head></head>
                <body><div data-slipstream="//body" data-slipstream-prepend>slipped content</div>
                    foobar
                </body>
            </html>
            EOF;

        $result = $service->processHtml($input);
        $this->assertSame(
            $output,
            $result
        );
    }

    /**
     * @test
     */
    public function specialCharactersWorkInContentAndSlipstreamWithDoctype() {
        $service = $this->createService();
        $input = <<<EOF
            <!DOCTYPE html>
            <html>
                <head></head>
                <body>
                    fooÄÖÜ<div data-slipstream>slipped content ÄÖÜ</div>barÄÖÜ
                </body>
            </html>
            EOF;
        $output = <<<EOF
            <!DOCTYPE html><html>
                <head><div data-slipstream>slipped content ÄÖÜ</div></head>
                <body>
                    fooÄÖÜbarÄÖÜ
                </body>
            </html>
            EOF;

        $result = $service->processHtml($input);
        $this->assertSame(
            $output,
            $result
        );
    }

    /**
     * @test
     */
    public function specialCharactersWorkInContentAndSlipstreamWithoutDoctype() {
        $service = $this->createService();
        $input = <<<EOF
            <html>
                <head></head>
                <body>
                    fooÄÖÜ<div data-slipstream>slipped content ÄÖÜ</div>barÄÖÜ
                </body>
            </html>
            EOF;
        $output = <<<EOF
            <html>
                <head><div data-slipstream>slipped content ÄÖÜ</div></head>
                <body>
                    fooÄÖÜbarÄÖÜ
                </body>
            </html>
            EOF;

        $result = $service->processHtml($input);
        $this->assertSame(
            $output,
            $result
        );
    }


    public function createService(bool $debugMode = false, bool $removeAttributes = false): SlipStreamService
    {
        $service = new SlipStreamService();

        $debugModeProperty = new ReflectionProperty($service, "debugMode");
        $debugModeProperty->setAccessible(true);
        $debugModeProperty->setValue($service, $debugMode);

        $removeAttributesProperty = new ReflectionProperty($service, "removeAttributes");
        $removeAttributesProperty->setAccessible(true);
        $removeAttributesProperty->setValue($service, $removeAttributes);

        return $service;
    }

    protected function createResponse(string $content): ResponseInterface
    {
        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->expects($this->any())->method('getContents')->willReturn($content);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->expects($this->any())->method('getBody')->willReturn($streamMock);
        return $responseMock;
    }


}
