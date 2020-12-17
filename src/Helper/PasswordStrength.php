<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Helper;

/**
 * Allows to calculate the strength of a password with a variant of a NIST
 * proposal developed by Thomas Hruska.
 *
 * @see http://en.wikipedia.org/wiki/Password_strength#NIST_Special_Publication_800-63
 * @see http://cubicspot.blogspot.de/2011/11/how-to-calculate-password-strength.html
 * @see http://cubicspot.blogspot.de/2012/01/how-to-calculate-password-strength-part.html
 * @see http://cubicspot.blogspot.de/2012/06/how-to-calculate-password-strength-part.html
 */
class PasswordStrength
{
    public const RATING_BAD   = 'bad';
    public const RATING_WEAK  = 'weak';
    public const RATING_OK    = 'ok';
    public const RATING_GOOD  = 'good';
    public const RATING_GREAT = 'great';

    /**
     * Thresholds above which a password is rated OK/GOOD etc.
     */
    protected array $thresholds = [
        self::RATING_WEAK  => 15,
        self::RATING_OK    => 20,
        self::RATING_GOOD  => 25,
        self::RATING_GREAT => 30,
    ];

    /**
     * Returns the current threshold settings.
     */
    public function getThresholds(): array
    {
        return $this->thresholds;
    }

    /**
     * Allows to set the threshold values for each rating.
     */
    public function setThresholds(array $thresholds): self
    {
        $this->thresholds = array_merge($this->thresholds, $thresholds);

        return $this;
    }

    /**
     * Converts the given strength value into a human readable rating.
     */
    public function getRating(float $strength): string
    {
        if ($strength >= $this->thresholds[self::RATING_GREAT]) {
            return self::RATING_GREAT;
        }

        if ($strength >= $this->thresholds[self::RATING_GOOD]) {
            return self::RATING_GOOD;
        }

        if ($strength >= $this->thresholds[self::RATING_OK]) {
            return self::RATING_OK;
        }

        if ($strength >= $this->thresholds[self::RATING_WEAK]) {
            return self::RATING_WEAK;
        }

        return self::RATING_BAD;
    }

    /**
     * Calculates the password strength using an entropy method.
     * Returns a numeric value where higher = better, starting with -6 for an
     * empty string. Gives a bonus for passphrases consisting of 4 or more
     * words separated with a space.
     */
    public function getStrength(string $password): float
    {
        $y = strlen($password);

        // Variant on NIST rules to reduce long sequences of repeated characters
        $result = 0;
        $mult   = [];
        for ($i = 0; $i < $y; ++$i) {
            $code = ord($password[$i]);

            if (!isset($mult[$code])) {
                $mult[$code] = 1;
            }

            if ($i > 19) {
                $result += $mult[$code];
            } elseif ($i > 7) {
                $result += $mult[$code] * 1.5;
            } elseif ($i > 0) {
                $result += $mult[$code] * 2;
            } else {
                $result += 4;
            }

            $mult[$code] *= 0.75;
        }

        // NIST password strength rules allow up to 6 extra bits for mixed case
        // and non-alphabetic characters
        $lower   = preg_match('/[a-z]/', $password);
        $upper   = preg_match('/[A-Z]/', $password);
        $numeric = preg_match('/\d/', $password);
        $space   = preg_match('/ /', $password);
        $other   = !preg_match('/^[A-Za-z0-9 ]*$/', $password);

        $extrabits = 0;
        if ($upper) {
            ++$extrabits;
        }
        if ($lower && $upper) {
            ++$extrabits;
        }
        if ($numeric) {
            ++$extrabits;
        }
        if ($other) {
            $extrabits += 2;
        } elseif ($space) {
            ++$extrabits;
        }

        // malus if only special characters or only numeric
        if (!$lower && !$upper) {
            $extrabits -= 2;

            if (!$other && !$space) {
                $extrabits -= 4;
            }
        }

        // bonus if pw consists of 4 or more separate words
        if (count(explode(' ', preg_replace('/\s+/', ' ', $password))) > 3) {
            ++$extrabits;
        }

        return $result + $extrabits;
    }

    /**
     * Returns the rating for the given password.
     */
    public function ratePassword(string $password): string
    {
        return $this->getRating($this->getStrength($password));
    }
}
