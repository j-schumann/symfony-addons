<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Encoder;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

// @see https://api-platform.com/docs/core/file-upload/#handling-the-multipart-deserialization
class MultipartDecoder implements DecoderInterface
{
    final public const FORMAT = 'multipart';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function decode(string $data, string $format, array $context = []): ?array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        return $request->request->all() + $request->files->all();
    }

    public function supportsDecoding(string $format, array $context = []): bool
    {
        return self::FORMAT === $format;
    }
}
