<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer;

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\Tokenizer\Token;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 * @author Graham Campbell <graham@alt-three.com>
 * @author Odín del Río <odin.drp@gmail.com>
 *
 * @internal
 */
final class Utils
{
    /**
     * @var array<string,true>
     */
    private static $deprecations = [];

    /**
     * Converts a camel cased string to a snake cased string.
     */
    public static function camelCaseToUnderscore(string $string): string
    {
        return strtolower(Preg::replace('/(?<!^)((?=[A-Z][^A-Z])|(?<![A-Z])(?=[A-Z]))/', '_', $string));
    }

    /**
     * Compare two integers for equality.
     *
     * We'll return 0 if they're equal, 1 if the first is bigger than the
     * second, and -1 if the second is bigger than the first.
     */
    public static function cmpInt(int $a, int $b): int
    {
        if ($a === $b) {
            return 0;
        }

        return $a < $b ? -1 : 1;
    }

    /**
     * Calculate the trailing whitespace.
     *
     * What we're doing here is grabbing everything after the final newline.
     */
    public static function calculateTrailingWhitespaceIndent(Token $token): string
    {
        if (!$token->isWhitespace()) {
            throw new \InvalidArgumentException(sprintf('The given token must be whitespace, got "%s".', $token->getName()));
        }

        $str = strrchr(
            str_replace(["\r\n", "\r"], "\n", $token->getContent()),
            "\n"
        );

        if (false === $str) {
            return '';
        }

        return ltrim($str, "\n");
    }

    /**
     * Perform stable sorting using provided comparison function.
     *
     * Stability is ensured by using Schwartzian transform.
     *
     * @param mixed[]  $elements
     * @param callable $getComparedValue a callable that takes a single element and returns the value to compare
     * @param callable $compareValues    a callable that compares two values
     *
     * @return mixed[]
     */
    public static function stableSort(array $elements, callable $getComparedValue, callable $compareValues): array
    {
        array_walk($elements, static function (&$element, int $index) use ($getComparedValue): void {
            $element = [$element, $index, $getComparedValue($element)];
        });

        usort($elements, static function ($a, $b) use ($compareValues) {
            $comparison = $compareValues($a[2], $b[2]);

            if (0 !== $comparison) {
                return $comparison;
            }

            return self::cmpInt($a[1], $b[1]);
        });

        return array_map(static function (array $item) {
            return $item[0];
        }, $elements);
    }

    /**
     * Sort fixers by their priorities.
     *
     * @param FixerInterface[] $fixers
     *
     * @return FixerInterface[]
     */
    public static function sortFixers(array $fixers): array
    {
        // Schwartzian transform is used to improve the efficiency and avoid
        // `usort(): Array was modified by the user comparison function` warning for mocked objects.
        return self::stableSort(
            $fixers,
            static function (FixerInterface $fixer) {
                return $fixer->getPriority();
            },
            static function (int $a, int $b) {
                return self::cmpInt($b, $a);
            }
        );
    }

    /**
     * Join names in natural language wrapped in backticks, e.g. `a`, `b` and `c`.
     *
     * @param string[] $names
     *
     * @throws \InvalidArgumentException
     */
    public static function naturalLanguageJoinWithBackticks(array $names): string
    {
        if (empty($names)) {
            throw new \InvalidArgumentException('Array of names cannot be empty.');
        }

        $names = array_map(static function (string $name) {
            return sprintf('`%s`', $name);
        }, $names);

        $last = array_pop($names);

        if ($names) {
            return implode(', ', $names).' and '.$last;
        }

        return $last;
    }

    /**
     * Handle triggering deprecation error.
     */
    public static function triggerDeprecation(string $message, string $exceptionClass = \RuntimeException::class): void
    {
        if (getenv('PHP_CS_FIXER_FUTURE_MODE')) {
            throw new $exceptionClass("{$message} This check was performed as `PHP_CS_FIXER_FUTURE_MODE` env var is set.");
        }

        self::$deprecations[$message] = true;
        @trigger_error($message, E_USER_DEPRECATED);
    }

    public static function getTriggeredDeprecations(): array
    {
        $triggeredDeprecations = array_keys(self::$deprecations);
        sort($triggeredDeprecations);

        return $triggeredDeprecations;
    }
}
