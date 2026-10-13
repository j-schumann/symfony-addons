<?php

namespace Vrok\SymfonyAddons\PhpCsFixer;

use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * @implements ConfigurableFixerInterface<array{max_arguments?: int}, array{max_arguments: int}>
 */
final class WrapNamedMethodArgumentsFixer implements FixerInterface, ConfigurableFixerInterface
{
    private const int DEFAULT_MAX_ARGUMENTS = 3;

    private int $maxArguments = self::DEFAULT_MAX_ARGUMENTS;

    public function getDefinition(): FixerDefinition
    {
        return new FixerDefinition(
            'Wrap method arguments to separate lines when they are named and exceed the maximum argument count (default 3).',
            [
                new CodeSample(
                    '<?php
$this->method(arg1: $value1, arg2: $value2, arg3: $value3);
// will be changed to:
$this->method(
    arg1: $value1,
    arg2: $value2,
    arg3: $value3
);

// will stay unchanged:
$this->method(arg1: $value1, arg2: $value2);',
                    ['max_arguments' => 2]
                ),
            ]
        );
    }

    public function getName(): string
    {
        return 'VrokSymfonyAddons/wrap_named_method_arguments';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function supports(\SplFileInfo $file): bool
    {
        return true;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(\T_STRING);
    }

    public function isRisky(): bool
    {
        return false;
    }

    public function getConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            new FixerOptionBuilder(
                'max_arguments',
                'Maximum number of arguments before formatting is applied.'
            )
                ->setAllowedTypes(['int'])
                ->setDefault(self::DEFAULT_MAX_ARGUMENTS)
                ->getOption(),
        ]);
    }

    public function configure(array $configuration): void
    {
        $this->maxArguments = $configuration['max_arguments'] ?? self::DEFAULT_MAX_ARGUMENTS;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public function fix(\SplFileInfo $file, Tokens $tokens): void
    {
        // formatCall() inserts tokens, so the count must be read on every iteration
        for ($i = 0; $i < $tokens->count(); ++$i) {
            if (!$tokens[$i]->isGivenKind(\T_STRING)) {
                continue;
            }

            $openParenIndex = $tokens->getNextMeaningfulToken($i);
            if (
                null === $openParenIndex
                || !$tokens[$openParenIndex]->equals('(')
            ) {
                continue;
            }

            $closeParenIndex = $tokens->findBlockEnd(
                Tokens::BLOCK_TYPE_PARENTHESIS,
                $openParenIndex
            );

            $analysis = $this->analyzeArguments($tokens, $openParenIndex, $closeParenIndex);
            if (
                !$analysis['hasNamedArgs']
                || $analysis['argumentCount'] <= $this->maxArguments
                || $this->areArgumentsAlreadyFormatted(
                    $tokens,
                    $openParenIndex,
                    $closeParenIndex,
                    $analysis['separators'],
                )
            ) {
                continue;
            }

            $this->formatCall(
                $tokens,
                $openParenIndex,
                $closeParenIndex,
                $analysis['separators'],
                $this->detectIndentation($tokens, $i),
            );
        }
    }

    /**
     * @param Tokens<Token> $tokens
     * @param list<int>     $separators
     */
    private function areArgumentsAlreadyFormatted(
        Tokens $tokens,
        int $openParenIndex,
        int $closeParenIndex,
        array $separators,
    ): bool {
        foreach ([$openParenIndex, ...$separators] as $index) {
            $nextIndex = $index + 1;
            if (
                $nextIndex < $closeParenIndex
                && $tokens[$nextIndex]->isWhitespace()
                && str_contains($tokens[$nextIndex]->getContent(), "\n")
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Tokens<Token>                         $tokens
     * @return array{base: string, argument: string}
     */
    private function detectIndentation(Tokens $tokens, int $functionNameIndex): array
    {
        $lineStartIndex = $functionNameIndex;
        while ($lineStartIndex > 0) {
            $prevIndex = $lineStartIndex - 1;
            if (
                $tokens[$prevIndex]->isWhitespace()
                && str_contains($tokens[$prevIndex]->getContent(), "\n")
            ) {
                break;
            }

            --$lineStartIndex;
        }

        $baseIndent = '';
        if ($lineStartIndex > 0) {
            $lines = explode("\n", $tokens[$lineStartIndex - 1]->getContent());
            $baseIndent = end($lines);
        }

        // try to find a consistent indentation in the file
        $indentUnit = $this->detectIndentationUnit($tokens);

        return [
            'argument' => $baseIndent.$indentUnit,
            'base'     => $baseIndent,
        ];
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function detectIndentationUnit(Tokens $tokens): string
    {
        $indentations = [];

        // Sample some whitespace tokens to detect indentation pattern
        for ($i = 0, $count = min(100, $tokens->count()); $i < $count; ++$i) {
            if (!$tokens[$i]->isWhitespace()) {
                continue;
            }

            $content = $tokens[$i]->getContent();
            if (!str_contains($content, "\n")) {
                continue;
            }

            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                if ('' === $line) {
                    continue;
                }

                $indent = '';
                $len = \strlen($line);
                for ($j = 0; $j < $len; ++$j) {
                    if (' ' === $line[$j] || "\t" === $line[$j]) {
                        $indent .= $line[$j];
                    } else {
                        break;
                    }
                }

                if ('' !== $indent) {
                    $indentations[] = $indent;
                }
            }
        }

        if ([] === $indentations) {
            return '    ';
        }

        foreach ($indentations as $indent) {
            if (str_contains($indent, "\t")) {
                return "\t";
            }
        }

        return str_repeat(' ', min(array_map(strlen(...), $indentations)));
    }

    /**
     * Nested blocks (calls, arrays, closures, attributes, braces in strings) are skipped as a
     * whole, so only the commas and named-argument colons of this call are found. Commas inside
     * strings are part of string tokens and never equal the ',' token.
     *
     * @param  Tokens<Token>                                                        $tokens
     * @return array{argumentCount: int, hasNamedArgs: bool, separators: list<int>}
     */
    private function analyzeArguments(
        Tokens $tokens,
        int $openParenIndex,
        int $closeParenIndex,
    ): array {
        $commas = [];
        $hasNamedArgs = false;
        $lastMeaningfulIndex = null;

        for ($i = $openParenIndex + 1; $i < $closeParenIndex; ++$i) {
            $token = $tokens[$i];
            if ($token->isWhitespace() || $token->isComment()) {
                continue;
            }

            $lastMeaningfulIndex = $i;
            $blockType = Tokens::detectBlockType($token);
            if (null !== $blockType && $blockType['isStart']) {
                $i = $tokens->findBlockEnd($blockType['type'], $i);
                $lastMeaningfulIndex = $i;
            } elseif ($token->equals(',')) {
                $commas[] = $i;
            } elseif ($token->isGivenKind(CT::T_NAMED_ARGUMENT_COLON)) {
                $hasNamedArgs = true;
            }
        }

        // a trailing comma separates no further argument
        if ([] !== $commas && end($commas) === $lastMeaningfulIndex) {
            array_pop($commas);
        }

        return [
            'argumentCount' => null === $lastMeaningfulIndex ? 0 : \count($commas) + 1,
            'hasNamedArgs'  => $hasNamedArgs,
            'separators'    => $commas,
        ];
    }

    /**
     * Works backwards, so the indices of the earlier tokens stay valid.
     *
     * @param Tokens<Token>                         $tokens
     * @param list<int>                             $separators
     * @param array{base: string, argument: string} $indentation
     */
    private function formatCall(
        Tokens $tokens,
        int $openParenIndex,
        int $closeParenIndex,
        array $separators,
        array $indentation,
    ): void {
        $this->addNewlineBeforeClosingParenthesis(
            $tokens,
            $closeParenIndex,
            $indentation['base'],
        );

        foreach (array_reverse($separators) as $commaIndex) {
            $this->setNewlineAfter($tokens, $commaIndex, $indentation['argument']);
        }

        $this->setNewlineAfter($tokens, $openParenIndex, $indentation['argument']);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function addNewlineBeforeClosingParenthesis(
        Tokens $tokens,
        int $closeParenIndex,
        string $baseIndent,
    ): void {
        $prevIndex = $tokens->getPrevMeaningfulToken($closeParenIndex);
        if (null === $prevIndex) {
            return;
        }

        if ($prevIndex + 1 === $closeParenIndex) {
            $tokens->insertAt($closeParenIndex, new Token([\T_WHITESPACE, "\n".$baseIndent]));
        } else {
            $this->replaceWhitespaceWithNewline($tokens, $prevIndex + 1, $baseIndent);
        }
    }

    /**
     * Replaces the whitespace after the given token, or inserts it if there is none.
     *
     * @param Tokens<Token> $tokens
     */
    private function setNewlineAfter(Tokens $tokens, int $index, string $indent): void
    {
        $newline = new Token([\T_WHITESPACE, "\n".$indent]);
        if ($tokens[$index + 1]->isWhitespace()) {
            $tokens[$index + 1] = $newline;
        } else {
            $tokens->insertAt($index + 1, $newline);
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function replaceWhitespaceWithNewline(
        Tokens $tokens,
        int $whitespaceIndex,
        string $indent,
    ): void {
        if ($tokens[$whitespaceIndex]->isWhitespace()) {
            $content = $tokens[$whitespaceIndex]->getContent();
            if (!str_contains($content, "\n")) {
                $tokens[$whitespaceIndex] = new Token([\T_WHITESPACE, "\n".$indent]);
            }
        }
    }
}
