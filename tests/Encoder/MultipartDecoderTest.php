<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Encoder;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
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
            ->with($this->callback(
                function (Request $request): true {
                    $stack = new RequestStack();
                    $stack->push($request);

                    $decoder = new MultipartDecoder($stack);
                    $res = $decoder->decode('', 'multipart');

                    // all params and the file where combined
                    self::assertIsArray($res);
                    self::assertCount(3, $res);
                    self::assertSame('Notes', $res['content']);
                    self::assertSame('123', $res['category']);
                    self::assertInstanceOf(UploadedFile::class, $res['file']);
                    self::assertSame('normal.jpg', $res['file']->getClientOriginalName());

                    return true;
                }
            ))
            ->willReturn(new Response());

        $this->uploadFile(
            $mockedClient,
            __FILE__,
            'normal.jpg',
            'image/jpg',
            '/irrelevant',
            'POST',
            [
                'content'  => 'Notes',
                'category' => '123',
            ]
        );
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

    /**
     * Uses a given KernelBrowser (@see self::createKernelBrowsser) to upload
     * a file to the API.
     */
    protected function uploadFile(
        AbstractBrowser $client,
        ?string $path,
        ?string $origName,
        ?string $mimeType,
        string $url,
        string $method = 'POST',
        array $params = [],
    ): void {
        $uploadedFile = null;

        if ($path) {
            // this only works because that file is never used/moved like
            // normal handling would be with an UploadedFile
            $uploadedFile = new UploadedFile(
                $path,
                $origName,
                $mimeType,
                null,
                true
            );
        }

        $client->request(
            $method,
            $url,
            $params,
            null !== $uploadedFile ? ['file' => $uploadedFile] : [],
            [
                'HTTP_ACCEPT'  => 'application/ld+json',
                'CONTENT_TYPE' => 'multipart/form-data',
                'HTTP_ORIGIN'  => 'https://example.net',
            ]
        );
    }
}
