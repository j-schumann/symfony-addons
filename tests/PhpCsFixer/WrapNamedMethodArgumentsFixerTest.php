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

        yield 'partially wrapped' => [
            "<?php\n\$this->method(a: 1,\n    b: 2, c: 3);\n",
            "<?php\n\$this->method(a: 1,\n    b: 2, c: 3);\n",
        ];

        yield 'without whitespace after the commas' => [
            "<?php\n\$this->method(a: 1,b: 2,c: 3);\n",
            "<?php\n\$this->method(\n    a: 1,\n    b: 2,\n    c: 3\n);\n",
        ];

        yield 'indented with spaces inside the parentheses' => [
            "<?php\nif (true) {\n    \$this->method( a: 1, b: 2, c: 3 );\n}\n",
            "<?php\nif (true) {\n    \$this->method(\n        a: 1,\n        b: 2,\n        c: 3\n    );\n}\n",
        ];

        yield 'indented with tabs' => [
            "<?php\nif (true) {\n\t\$this->method(a: 1, b: 2, c: 3);\n}\n",
            "<?php\nif (true) {\n\t\$this->method(\n\t\ta: 1,\n\t\tb: 2,\n\t\tc: 3\n\t);\n}\n",
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

    public function testConfigurationDefaultsToThreeArguments(): void
    {
        $fixer = new WrapNamedMethodArgumentsFixer();

        self::assertSame(['max_arguments' => 3], $fixer->getConfigurationDefinition()->resolve([]));
    }
}
