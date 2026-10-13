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

        yield 'ternary in positional arguments' => [
            "<?php\n\$this->method(\$a ? FOO : 2, \$b, \$c);\n",
            "<?php\n\$this->method(\$a ? FOO : 2, \$b, \$c);\n",
        ];

        yield 'closure return type in positional arguments' => [
            "<?php\n\$this->method(fn (): int => 1, \$b, \$c);\n",
            "<?php\n\$this->method(fn (): int => 1, \$b, \$c);\n",
        ];

        yield 'keyword as argument name' => [
            "<?php\n\$this->method(class: 1, default: 2, c: 3);\n",
            "<?php\n\$this->method(\n    class: 1,\n    default: 2,\n    c: 3\n);\n",
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

        yield 'comma in an interpolated string' => [
            "<?php\n\$this->method(a: 1, b: \"/items?ids=\$x,\$y\");\n",
            "<?php\n\$this->method(a: 1, b: \"/items?ids=\$x,\$y\");\n",
        ];

        yield 'comma in an interpolated string of a wrapped call' => [
            "<?php\n\$this->method(a: \"{\$w},{\$h}\", b: 2, c: 3);\n",
            "<?php\n\$this->method(\n    a: \"{\$w},{\$h}\",\n    b: 2,\n    c: 3\n);\n",
        ];

        yield 'comma in a heredoc' => [
            "<?php\n\$this->method(a: 1, b: <<<EOT\n    \$x,\$y\n    EOT);\n",
            "<?php\n\$this->method(a: 1, b: <<<EOT\n    \$x,\$y\n    EOT);\n",
        ];

        yield 'attribute in an argument' => [
            "<?php\n\$this->method(a: #[Pure] fn () => 1, b: 2, c: 3);\n",
            "<?php\n\$this->method(\n    a: #[Pure] fn () => 1,\n    b: 2,\n    c: 3\n);\n",
        ];

        yield 'dollar brace in a string argument' => [
            "<?php\n\$this->method(a: \"\${x}\", b: 2, c: 3);\n",
            "<?php\n\$this->method(\n    a: \"\${x}\",\n    b: 2,\n    c: 3\n);\n",
        ];

        yield 'trailing comma within the maximum' => [
            "<?php\n\$this->method(a: 1, b: 2,);\n",
            "<?php\n\$this->method(a: 1, b: 2,);\n",
        ];

        yield 'trailing comma of a wrapped call' => [
            "<?php\nif (true) {\n    \$this->method(a: 1, b: 2, c: 3,);\n}\n",
            "<?php\nif (true) {\n    \$this->method(\n        a: 1,\n        b: 2,\n        c: 3,\n    );\n}\n",
        ];

        yield 'named arguments only in a nested call' => [
            "<?php\n\$this->method(\$a, \$b, \$c, foo(x: 1));\n",
            "<?php\n\$this->method(\$a, \$b, \$c, foo(x: 1));\n",
        ];

        yield 'nested call wrapped, positional outer call kept' => [
            "<?php\n\$this->outer(\$x, \$this->inner(a: 1, b: 2, c: 3), \$y);\n",
            "<?php\n\$this->outer(\$x, \$this->inner(\n    a: 1,\n    b: 2,\n    c: 3\n), \$y);\n",
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

    public function testFixWrapsEveryCallInALongFile(): void
    {
        $code = "<?php\n";
        $expected = "<?php\n";
        for ($i = 0; $i < 15; ++$i) {
            $code .= "\$this->m$i(a: 1, b: 2, c: 3);\n";
            $expected .= "\$this->m$i(\n    a: 1,\n    b: 2,\n    c: 3\n);\n";
        }

        $fixer = new WrapNamedMethodArgumentsFixer();
        $fixer->configure(['max_arguments' => 2]);

        $tokens = Tokens::fromCode($code);
        $fixer->fix(new \SplFileInfo(__FILE__), $tokens);
        self::assertSame($expected, $tokens->generateCode());

        $tokens = Tokens::fromCode($expected);
        $fixer->fix(new \SplFileInfo(__FILE__), $tokens);
        self::assertSame($expected, $tokens->generateCode());
    }

    public function testConfigurationDefaultsToThreeArguments(): void
    {
        $fixer = new WrapNamedMethodArgumentsFixer();

        self::assertSame(['max_arguments' => 3], $fixer->getConfigurationDefinition()->resolve([]));
    }
}
