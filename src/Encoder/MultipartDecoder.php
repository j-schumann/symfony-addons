<?php

namespace Vrok\SymfonyAddons\Encoder;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * @see https://api-platform.com/docs/core/file-upload/#handling-the-multipart-deserialization
 */
readonly class MultipartDecoder implements DecoderInterface
{
    public const string FORMAT = 'multipart';

    public function __construct(private RequestStack $requestStack)
    {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|null
     */
    public function decode(string $data, string $format, array $context = []): ?array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        return $request->request->all() + $request->files->all();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDecoding(string $format, array $context = []): bool
    {
        return self::FORMAT === $format;
    }
}
