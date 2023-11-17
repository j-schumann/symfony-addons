<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Encoder;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\Serializer\Serializer;
use Vrok\SymfonyAddons\Encoder\FormDecoder;

/**
 * @group FormDecoder
 */
class FormDecoderTest extends KernelTestCase
{
    public function testDecoder(): void
    {
        $mockedClient = $this->getMockBuilder(HttpKernelBrowser::class)
            ->onlyMethods(['doRequest'])
            ->setConstructorArgs([static::bootKernel()])
            ->getMock();

        $mockedClient
            ->expects(self::once())
            ->method('doRequest')
            ->with($this->callback(
                function ($request) {
                    $stack = new RequestStack();
                    $stack->push($request);

                    $decoder = new FormDecoder($stack);
                    $res = $decoder->decode('', 'form');

                    // all params and the file where combined
                    self::assertIsArray($res);
                    self::assertCount(2, $res);
                    self::assertSame('Notes', $res['content']);
                    self::assertSame('123', $res['category']);

                    return true;
                }
            ))
            ->willReturn(new Response());

        $mockedClient->request(
            'POST',
            '/irrelevant',
            [
                'content'  => 'Notes',
                'category' => '123',
            ],
            [],
            [
                'HTTP_ACCEPT'  => 'application/ld+json',
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'HTTP_ORIGIN'  => 'https://example.net',
            ]
        );
    }

    public function testSupports(): void
    {
        $decoder = new FormDecoder(new RequestStack());
        self::assertTrue($decoder->supportsDecoding('form'));
        self::assertFalse($decoder->supportsDecoding('text/plain'));
    }

    public function testService(): void
    {
        /** @var Serializer $serializer */
        $serializer = static::getContainer()->get('serializer');
        self::assertTrue($serializer->supportsDecoding('form'));
    }
}
