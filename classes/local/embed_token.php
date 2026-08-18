<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace filter_saylorcode\local;

use local_saylorcode\local\stable_id;

/**
 * A parsed Saylor Code Studio embed token.
 *
 * An embed looks like this in course content:
 *
 *     [[saylorcode:exercise=CS101-U05-E03;mode=compact;version=latest]]
 *
 * The token carries a reference and presentation preferences and nothing else.
 * It can never carry a runner address, an API key, markup or a script, because
 * every attribute is matched against a fixed whitelist and anything unrecognised
 * is discarded rather than passed through (specification section 11.2).
 *
 * That strictness is the point: content authors write these by hand, embeds are
 * copied between courses, and the filter runs on text that any user with editing
 * rights can supply.
 *
 * @package    filter_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class embed_token {

    /** @var string Matches a whole token and captures its attribute list. */
    public const PATTERN = '/\[\[saylorcode:([^\]\[<>]{1,255})\]\]/i';

    /** @var string Compact three tab presentation for inline content. */
    public const MODE_COMPACT = 'compact';

    /** @var string Full three pane presentation. */
    public const MODE_FULL = 'full';

    /** @var string Render only a link to the stand alone activity. */
    public const MODE_LINK = 'link';

    /** @var string Follow the latest approved version. */
    public const VERSION_LATEST = 'latest';

    /** @var int[] Heights an author may request, in pixels. */
    private const ALLOWED_HEIGHTS = [300, 400, 500, 600, 700, 800];

    /** @var stable_id The referenced exercise. */
    private stable_id $exercise;

    /** @var string One of the MODE_* constants. */
    private string $mode;

    /** @var string Either VERSION_LATEST or a positive integer as a string. */
    private string $version;

    /** @var int|null Requested height in pixels, already validated. */
    private ?int $height;

    /** @var bool Whether the instructions pane is shown. */
    private bool $showinstructions;

    /** @var bool Whether a full screen control is offered. */
    private bool $allowfullscreen;

    /**
     * Build a token.
     *
     * @param stable_id $exercise The referenced exercise.
     * @param string $mode One of the MODE_* constants.
     * @param string $version VERSION_LATEST or a version number.
     * @param int|null $height Requested height in pixels.
     * @param bool $showinstructions Whether to show instructions.
     * @param bool $allowfullscreen Whether to offer full screen.
     */
    public function __construct(
        stable_id $exercise,
        string $mode = self::MODE_COMPACT,
        string $version = self::VERSION_LATEST,
        ?int $height = null,
        bool $showinstructions = true,
        bool $allowfullscreen = true
    ) {
        $this->exercise = $exercise;
        $this->mode = $mode;
        $this->version = $version;
        $this->height = $height;
        $this->showinstructions = $showinstructions;
        $this->allowfullscreen = $allowfullscreen;
    }

    /**
     * Parse the attribute list captured from a token.
     *
     * @param string $attributes The text between the token delimiters.
     * @return self|null Null when the token does not name a valid exercise.
     */
    public static function parse(string $attributes): ?self {
        $parsed = [];
        foreach (explode(';', $attributes) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || strpos($pair, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $pair, 2);
            $key = strtolower(trim($key));
            // An unknown attribute is dropped here rather than carried forward,
            // so a future attribute cannot be smuggled through an old release.
            if (self::is_known_attribute($key)) {
                $parsed[$key] = trim($value);
            }
        }

        if (!isset($parsed['exercise']) || !stable_id::is_valid($parsed['exercise'])) {
            return null;
        }

        return new self(
            stable_id::parse($parsed['exercise']),
            self::clean_mode($parsed['mode'] ?? ''),
            self::clean_version($parsed['version'] ?? ''),
            self::clean_height($parsed['height'] ?? ''),
            self::clean_bool($parsed['showinstructions'] ?? '', true),
            self::clean_bool($parsed['allowfullscreen'] ?? '', true)
        );
    }

    /**
     * Whether an attribute name is one this filter understands.
     *
     * @param string $key Lower case attribute name.
     * @return bool
     */
    private static function is_known_attribute(string $key): bool {
        return in_array($key, [
            'exercise',
            'mode',
            'version',
            'height',
            'showinstructions',
            'allowfullscreen',
        ], true);
    }

    /**
     * Reduce a requested mode to a supported one.
     *
     * @param string $value Raw attribute value.
     * @return string One of the MODE_* constants.
     */
    private static function clean_mode(string $value): string {
        $value = strtolower(trim($value));
        if (in_array($value, [self::MODE_COMPACT, self::MODE_FULL, self::MODE_LINK], true)) {
            return $value;
        }
        return self::MODE_COMPACT;
    }

    /**
     * Reduce a requested version to either latest or a version number.
     *
     * @param string $value Raw attribute value.
     * @return string
     */
    private static function clean_version(string $value): string {
        $value = strtolower(trim($value));
        if ($value !== '' && ctype_digit($value) && (int) $value > 0) {
            return $value;
        }
        return self::VERSION_LATEST;
    }

    /**
     * Reduce a requested height to one of the approved values.
     *
     * Free numeric input is not accepted, because an arbitrary height is a
     * layout hazard on small screens and offers nothing an approved step does
     * not (specification section 11.2).
     *
     * @param string $value Raw attribute value.
     * @return int|null Null when no usable height was requested.
     */
    private static function clean_height(string $value): ?int {
        $value = trim($value);
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }
        $requested = (int) $value;

        return in_array($requested, self::ALLOWED_HEIGHTS, true) ? $requested : null;
    }

    /**
     * Interpret a boolean attribute.
     *
     * @param string $value Raw attribute value.
     * @param bool $default Value to use when the attribute is absent or unclear.
     * @return bool
     */
    private static function clean_bool(string $value, bool $default): bool {
        $value = strtolower(trim($value));
        if (in_array($value, ['1', 'true', 'yes'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no'], true)) {
            return false;
        }
        return $default;
    }

    /**
     * The referenced exercise.
     *
     * @return stable_id
     */
    public function get_exercise(): stable_id {
        return $this->exercise;
    }

    /**
     * Presentation mode.
     *
     * @return string
     */
    public function get_mode(): string {
        return $this->mode;
    }

    /**
     * Version policy.
     *
     * @return string
     */
    public function get_version(): string {
        return $this->version;
    }

    /**
     * Requested height in pixels.
     *
     * @return int|null
     */
    public function get_height(): ?int {
        return $this->height;
    }

    /**
     * Whether instructions are shown.
     *
     * @return bool
     */
    public function shows_instructions(): bool {
        return $this->showinstructions;
    }

    /**
     * Whether a full screen control is offered.
     *
     * @return bool
     */
    public function allows_fullscreen(): bool {
        return $this->allowfullscreen;
    }

    /**
     * Whether this token pins an explicit version.
     *
     * @return bool
     */
    public function is_pinned(): bool {
        return $this->version !== self::VERSION_LATEST;
    }

    /**
     * Rebuild the canonical token text.
     *
     * Used by the editor plugin so that an inserted token is always well formed.
     *
     * @return string
     */
    public function __toString(): string {
        $parts = [
            'exercise=' . (string) $this->exercise,
            'mode=' . $this->mode,
            'version=' . $this->version,
        ];
        if ($this->height !== null) {
            $parts[] = 'height=' . $this->height;
        }
        if (!$this->showinstructions) {
            $parts[] = 'showinstructions=false';
        }
        if (!$this->allowfullscreen) {
            $parts[] = 'allowfullscreen=false';
        }

        return '[[saylorcode:' . implode(';', $parts) . ']]';
    }
}
