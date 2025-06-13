<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Encoder;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * Used to decode 'application/x-www-form-urlencoded' requests for usage in
 * ApiPlatform. The decoder is auto-registered as service.
 */
readonly class FormDecoder implements DecoderInterface
{
    public const string FORMAT = 'form';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function decode(string $data, string $format, array $context = []): ?array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        return $request->request->all();
    }

    public function supportsDecoding(string $format, array $context = []): bool
    {
        return self::FORMAT === $format;
    }
}
