<?php

namespace Vrok\SymfonyAddons\Tests\Encoder;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\Serializer\Serializer;
use Vrok\SymfonyAddons\Encoder\MultipartDecoder;

final class MultipartDecoderTest extends KernelTestCase
{
    public function testDecoder(): void
    {
        $mockedClient = $this->getMockBuilder(HttpKernelBrowser::class)
            ->onlyMethods(['doRequest'])
            ->setConstructorArgs([self::bootKernel()])
            ->getMock();

        $mockedClient
            ->expects($this->once())
            ->method('doRequest')
            ->willReturnCallback(static function (Request $request): Response {
                $stack = new RequestStack([$request]);

                $decoder = new MultipartDecoder($stack);
                $res = $decoder->decode('', 'multipart');

                // all params and the file were combined
                self::assertIsArray($res);
                self::assertCount(3, $res);
                self::assertSame('Notes', $res['content']);
                self::assertSame('123', $res['category']);
                self::assertInstanceOf(UploadedFile::class, $res['file']);
                self::assertSame('normal.jpg', $res['file']->getClientOriginalName());

                return new Response();
            });

        // the file is never moved, unlike the normal handling of an UploadedFile
        $file = new UploadedFile(__FILE__, 'normal.jpg', 'image/jpg', null, true);

        $mockedClient->request(
            'POST',
            '/irrelevant',
            [
                'category' => '123',
                'content'  => 'Notes',
            ],
            ['file' => $file],
            [
                'CONTENT_TYPE' => 'multipart/form-data',
                'HTTP_ACCEPT'  => 'application/ld+json',
                'HTTP_ORIGIN'  => 'https://example.net',
            ]
        );
    }

    public function testDecodeWithoutRequest(): void
    {
        $decoder = new MultipartDecoder(new RequestStack());

        self::assertNull($decoder->decode('', 'multipart'));
    }

    public function testSupports(): void
    {
        $decoder = new MultipartDecoder(new RequestStack());
        self::assertTrue($decoder->supportsDecoding('multipart'));
        self::assertFalse($decoder->supportsDecoding('text/plain'));
    }

    public function testService(): void
    {
        /** @var Serializer $serializer */
        $serializer = self::getContainer()->get('serializer');
        self::assertTrue($serializer->supportsDecoding('multipart'));
    }
}
