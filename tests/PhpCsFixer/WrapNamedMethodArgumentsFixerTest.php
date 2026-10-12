<?php

namespace Vrok\SymfonyAddons\Tests\PhpCsFixer;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vrok\SymfonyAddons\PhpCsFixer\WrapNamedMethodArgumentsFixer;

final class WrapNamedMethodArgumentsFixerTest extends TestCase
{
    public static function provideCode(): \Iterator
    {
        yield 'more arguments than the maximum' => [
            "<?php\n\$this->method(a: 1, b: 2, c: 3);\n",
            "<?php\n\$this->method(\n    a: 1,\n    b: 2,\n    c: 3\n);\n",
        ];

        yield 'maximum not exceeded' => [
            "<?php\n\$this->method(a: 1, b: 2);\n",
            "<?php\n\$this->method(a: 1, b: 2);\n",
        ];

        yield 'positional arguments' => [
            "<?php\n\$this->method(1, 2, 3);\n",
            "<?php\n\$this->method(1, 2, 3);\n",
        ];

        yield 'multiline array argument' => [
            "<?php\n\$this->method(a: 1, b: [\n    'x',\n    'y',\n], c: [3, 4]);\n",
            "<?php\n\$this->method(\n    a: 1,\n    b: [\n    'x',\n    'y',\n],\n    c: [3, 4]\n);\n",
        ];

        yield 'already wrapped' => [
            "<?php\n\$this->method(\n    a: 1,\n    b: 2,\n    c: 3\n);\n",
            "<?php\n\$this->method(\n    a: 1,\n    b: 2,\n    c: 3\n);\n",
        ];
    }

    #[DataProvider('provideCode')]
    public function testFix(string $code, string $expected): void
    {
        $fixer = new WrapNamedMethodArgumentsFixer();
        $fixer->configure(['max_arguments' => 2]);

        $tokens = Tokens::fromCode($code);
        $fixer->fix(new \SplFileInfo(__FILE__), $tokens);

        self::assertSame($expected, $tokens->generateCode());
    }
}
