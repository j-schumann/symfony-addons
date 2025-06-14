<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\PhpCsFixer;

use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class WrapNamedMethodArgumentsFixer implements FixerInterface, ConfigurableFixerInterface
{
    private const int DEFAULT_MAX_ARGUMENTS = 3;
    private const array NESTING_OPEN_TOKENS = ['(', '[', '{'];
    private const array NESTING_CLOSE_TOKENS = [')', ']', '}'];

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
        return $tokens->isTokenKindFound(T_STRING);
    }

    public function isRisky(): bool
    {
        return false;
    }

    public function getConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            (new FixerOptionBuilder(
                'max_arguments',
                'Maximum number of arguments before formatting is applied.'
            ))
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
        for ($i = 0, $tokenCount = $tokens->count(); $i < $tokenCount; ++$i) {
            if (!$tokens[$i]->isGivenKind(T_STRING)) {
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
                Tokens::BLOCK_TYPE_PARENTHESIS_BRACE,
                $openParenIndex
            );

            if ($this->shouldFormatMethodCall($tokens, $openParenIndex, $closeParenIndex)) {
                $indentation = $this->detectIndentation($tokens, $i);
                $this->formatMethodCall($tokens, $openParenIndex, $closeParenIndex, $indentation);
            }
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function shouldFormatMethodCall(
        Tokens $tokens,
        int $openParenIndex,
        int $closeParenIndex,
    ): bool {
        $analysisResult = $this->analyzeArguments($tokens, $openParenIndex, $closeParenIndex);

        return $analysisResult['hasNamedArgs'] && $analysisResult['argumentCount'] > $this->maxArguments;
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function detectIndentation(Tokens $tokens, int $functionNameIndex): array
    {
        // Find the start of the line containing the function call
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

        // Detect current line indentation
        $baseIndent = '';
        if ($lineStartIndex > 0 && $tokens[$lineStartIndex]->isWhitespace()) {
            $whitespace = $tokens[$lineStartIndex]->getContent();
            $lines = explode("\n", $whitespace);
            $baseIndent = end($lines); // Get indentation after the last newline
        }

        // Detect indentation unit (try to find consistent indentation in the file)
        $indentUnit = $this->detectIndentationUnit($tokens);

        return [
            'base'     => $baseIndent,
            'unit'     => $indentUnit,
            'argument' => $baseIndent.$indentUnit,
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

                // Count leading spaces/tabs
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

        // Analyze indentations to find the unit
        if ([] === $indentations) {
            return '    '; // Default to 4 spaces
        }

        // Check if using tabs
        foreach ($indentations as $indent) {
            if (str_contains($indent, "\t")) {
                return "\t";
            }
        }

        // Count spaces - find the smallest non-zero indentation
        $spaceCounts = array_map('strlen', $indentations);
        $spaceCounts = array_filter($spaceCounts, static fn ($count) => $count > 0);

        if ([] === $spaceCounts) {
            return '    '; // Default to 4 spaces
        }

        $minSpaces = min($spaceCounts);

        return str_repeat(' ', $minSpaces);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function analyzeArguments(
        Tokens $tokens,
        int $openParenIndex,
        int $closeParenIndex,
    ): array {
        $topLevelCommas = [];
        $nestingLevel = 0;
        $hasContent = false;
        $hasNamedArgs = false;

        for ($i = $openParenIndex + 1; $i < $closeParenIndex; ++$i) {
            $token = $tokens[$i];
            $content = $token->getContent();

            if (\in_array($content, self::NESTING_OPEN_TOKENS, true)) {
                ++$nestingLevel;
            } elseif (\in_array($content, self::NESTING_CLOSE_TOKENS, true)) {
                --$nestingLevel;
            } elseif (',' === $content && 0 === $nestingLevel) {
                $topLevelCommas[] = $i;
            } elseif (':' === $content) {
                $hasNamedArgs = true;
            }

            if (!$token->isWhitespace()) {
                $hasContent = true;
            }
        }

        return [
            'argumentCount'  => $hasContent ? \count($topLevelCommas) + 1 : 0,
            'hasNamedArgs'   => $hasNamedArgs,
            'topLevelCommas' => $topLevelCommas,
        ];
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function formatMethodCall(Tokens $tokens, int $openParenIndex, int $closeParenIndex, array $indentation): void
    {
        $analysisResult = $this->analyzeArguments($tokens, $openParenIndex, $closeParenIndex);
        $topLevelCommas = $analysisResult['topLevelCommas'];

        // Work backwards to avoid index shifts
        $this->addNewlineBeforeClosingParenthesis($tokens, $closeParenIndex, $indentation['base']);
        $this->addNewlinesAfterCommas($tokens, $topLevelCommas, $indentation['argument']);
        $this->addNewlineAfterOpeningParenthesis($tokens, $openParenIndex, $indentation['argument']);
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function addNewlineBeforeClosingParenthesis(Tokens $tokens, int $closeParenIndex, string $baseIndent): void
    {
        $prevIndex = $tokens->getPrevMeaningfulToken($closeParenIndex);
        if (null === $prevIndex) {
            return;
        }

        if ($prevIndex + 1 === $closeParenIndex) {
            $tokens->insertAt($closeParenIndex, new Token([T_WHITESPACE, "\n".$baseIndent]));
        } else {
            $this->replaceWhitespaceWithNewline($tokens, $prevIndex + 1, $baseIndent);
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function addNewlinesAfterCommas(Tokens $tokens, array $topLevelCommas, string $argumentIndent): void
    {
        foreach (array_reverse($topLevelCommas) as $commaIndex) {
            $nextTokenIndex = $commaIndex + 1;
            if ($nextTokenIndex < \count($tokens) && $tokens[$nextTokenIndex]->isWhitespace()) {
                $tokens[$nextTokenIndex] = new Token([T_WHITESPACE, "\n".$argumentIndent]);
            } else {
                $tokens->insertAt($commaIndex + 1, new Token([T_WHITESPACE, "\n".$argumentIndent]));
            }
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function addNewlineAfterOpeningParenthesis(Tokens $tokens, int $openParenIndex, string $argumentIndent): void
    {
        $nextTokenIndex = $openParenIndex + 1;
        if ($nextTokenIndex < \count($tokens) && $tokens[$nextTokenIndex]->isWhitespace()) {
            $tokens[$nextTokenIndex] = new Token([T_WHITESPACE, "\n".$argumentIndent]);
        } else {
            $tokens->insertAt($openParenIndex + 1, new Token([T_WHITESPACE, "\n".$argumentIndent]));
        }
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function replaceWhitespaceWithNewline(Tokens $tokens, int $whitespaceIndex, string $indent): void
    {
        if ($tokens[$whitespaceIndex]->isWhitespace()) {
            $content = $tokens[$whitespaceIndex]->getContent();
            if (!str_contains($content, "\n")) {
                $tokens[$whitespaceIndex] = new Token([T_WHITESPACE, "\n".$indent]);
            }
        }
    }
}
