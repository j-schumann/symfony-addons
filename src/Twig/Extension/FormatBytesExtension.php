<?php

namespace Vrok\SymfonyAddons\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @deprecated since 3.8, moves to Vrok\SymfonyAddons\Twig\FormatBytesExtension
 *             in 4.0 and no longer extends AbstractExtension, getName() and
 *             getFilters() are removed
 */
class FormatBytesExtension extends AbstractExtension
{
    public function getName(): string
    {
        return 'format_bytes';
    }

    /**
     * @return TwigFilter[]
     */
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('formatBytes', $this->formatBytes(...)),
        ];
    }

    public function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, \count($units) - 1);

        $bytes /= 1024 ** $pow;

        // @todo format_number to respect locale
        return round($bytes, $precision).' '.$units[$pow];
    }
}
