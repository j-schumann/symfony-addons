<?php

namespace Vrok\SymfonyAddons\Twig;

use Twig\Attribute\AsTwigFilter;

class FormatBytesExtension
{
    /**
     * @param int|float $bytes
     * @param int       $precision
     */
    #[AsTwigFilter(name: 'formatBytes')]
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
