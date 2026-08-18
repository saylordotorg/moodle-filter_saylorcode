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

/**
 * Tests for embed token parsing.
 *
 * Embed tokens are written by hand into course content, so this parser sees
 * user supplied text. The tests below are deliberately adversarial: an
 * attribute that is not understood must be discarded, never carried through.
 *
 * @package    filter_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_saylorcode\local\embed_token
 */
final class embed_token_test extends \advanced_testcase {
    /**
     * A minimal token resolves with sensible defaults.
     */
    public function test_minimal_token_uses_defaults(): void {
        $token = embed_token::parse('exercise=CS101-U05-E03');

        $this->assertNotNull($token);
        $this->assertSame('CS101-U05-E03', (string) $token->get_exercise());
        $this->assertSame(embed_token::MODE_COMPACT, $token->get_mode());
        $this->assertSame(embed_token::VERSION_LATEST, $token->get_version());
        $this->assertTrue($token->shows_instructions());
        $this->assertTrue($token->allows_fullscreen());
        $this->assertFalse($token->is_pinned());
    }

    /**
     * Every supported attribute is honoured.
     */
    public function test_full_token_is_parsed(): void {
        $token = embed_token::parse(
            'exercise=CS101-U05-E03;mode=full;version=7;height=500;showinstructions=false;allowfullscreen=no'
        );

        $this->assertNotNull($token);
        $this->assertSame(embed_token::MODE_FULL, $token->get_mode());
        $this->assertSame('7', $token->get_version());
        $this->assertSame(500, $token->get_height());
        $this->assertFalse($token->shows_instructions());
        $this->assertFalse($token->allows_fullscreen());
        $this->assertTrue($token->is_pinned());
    }

    /**
     * A token without a usable exercise reference resolves to nothing.
     *
     * @return array
     */
    public static function unusable_provider(): array {
        return [
            'no exercise' => ['mode=compact'],
            'empty exercise' => ['exercise='],
            'malformed exercise' => ['exercise=NOT-AN-ID'],
            'sql fragment' => ["exercise=CS101-U05-E03' OR '1'='1"],
            'traversal' => ['exercise=../../etc/passwd'],
            'empty string' => [''],
        ];
    }

    /**
     * Unusable tokens must return null rather than a partial object.
     *
     * @param string $attributes The attribute list.
     * @dataProvider unusable_provider
     */
    public function test_unusable_tokens_return_null(string $attributes): void {
        $this->assertNull(embed_token::parse($attributes));
    }

    /**
     * An attribute this filter does not understand is discarded silently.
     *
     * This is the important one. An author, or someone editing course content,
     * must not be able to introduce a runner address, a key or a script by
     * inventing an attribute name.
     */
    public function test_unknown_attributes_are_discarded(): void {
        $token = embed_token::parse(
            'exercise=CS101-U05-E03;runner=http://evil.example.org;apikey=secret;onload=alert(1)'
        );

        $this->assertNotNull($token);

        // Nothing from those attributes may survive into the canonical form.
        $rebuilt = (string) $token;
        $this->assertStringNotContainsString('evil.example.org', $rebuilt);
        $this->assertStringNotContainsString('secret', $rebuilt);
        $this->assertStringNotContainsString('alert', $rebuilt);
        $this->assertStringNotContainsString('runner', $rebuilt);
    }

    /**
     * An unsupported mode falls back to compact rather than being echoed.
     */
    public function test_unknown_mode_falls_back(): void {
        $token = embed_token::parse('exercise=CS101-U05-E03;mode=<script>alert(1)</script>');

        $this->assertNotNull($token);
        $this->assertSame(embed_token::MODE_COMPACT, $token->get_mode());
    }

    /**
     * Heights outside the approved set are dropped, not clamped silently to a
     * caller supplied number.
     */
    public function test_unapproved_height_is_dropped(): void {
        $this->assertNull(embed_token::parse('exercise=CS101-U05-E03;height=99999')->get_height());
        $this->assertNull(embed_token::parse('exercise=CS101-U05-E03;height=13')->get_height());
        $this->assertNull(embed_token::parse('exercise=CS101-U05-E03;height=abc')->get_height());
        $this->assertSame(400, embed_token::parse('exercise=CS101-U05-E03;height=400')->get_height());
    }

    /**
     * A non numeric or negative version falls back to latest.
     */
    public function test_bad_version_falls_back_to_latest(): void {
        $this->assertSame(embed_token::VERSION_LATEST, embed_token::parse('exercise=CS101-U05-E03;version=abc')->get_version());
        $this->assertSame(embed_token::VERSION_LATEST, embed_token::parse('exercise=CS101-U05-E03;version=-3')->get_version());
        $this->assertSame(embed_token::VERSION_LATEST, embed_token::parse('exercise=CS101-U05-E03;version=0')->get_version());
    }

    /**
     * Whitespace and casing in the reference are tolerated.
     */
    public function test_reference_is_normalised(): void {
        $token = embed_token::parse(' exercise = cs101-u05-e03 ; mode = FULL ');

        $this->assertNotNull($token);
        $this->assertSame('CS101-U05-E03', (string) $token->get_exercise());
        $this->assertSame(embed_token::MODE_FULL, $token->get_mode());
    }

    /**
     * The canonical form round trips.
     */
    public function test_canonical_form_round_trips(): void {
        $original = embed_token::parse('exercise=CS101-U05-E03;mode=full;version=7;height=500');
        $text = (string) $original;

        $this->assertMatchesRegularExpression(embed_token::PATTERN, $text);

        preg_match(embed_token::PATTERN, $text, $matches);
        $reparsed = embed_token::parse($matches[1]);

        $this->assertSame((string) $original->get_exercise(), (string) $reparsed->get_exercise());
        $this->assertSame($original->get_mode(), $reparsed->get_mode());
        $this->assertSame($original->get_version(), $reparsed->get_version());
        $this->assertSame($original->get_height(), $reparsed->get_height());
    }

    /**
     * Markup introduced by an earlier filter is stripped, not fatal.
     *
     * Moodle's URL to link filter rewrites a bare address inside a token before
     * this filter runs. If that stopped the token matching, a broken reference
     * would stay visible to students instead of being hidden from them, and the
     * behaviour would depend on how an administrator ordered the filters.
     */
    public function test_markup_from_earlier_filters_is_stripped(): void {
        $token = embed_token::parse('exercise=<b>CS101-U05-E03</b>');

        $this->assertNotNull($token);
        $this->assertSame('CS101-U05-E03', (string) $token->get_exercise());
    }

    /**
     * A token whose URL has already been linkified still parses, and the
     * address still goes nowhere.
     */
    public function test_linkified_token_still_parses_and_discards() {
        $attributes = 'exercise=CS101-U05-E03;runner=<a href="http://evil.example.org">http://evil.example.org</a>';

        $token = embed_token::parse($attributes);

        $this->assertNotNull($token);
        $this->assertStringNotContainsString('evil.example.org', (string) $token);
    }

    /**
     * The pattern is still bounded, so a token cannot swallow a whole page.
     */
    public function test_pattern_is_length_bounded(): void {
        $huge = '[[saylorcode:exercise=CS101-U05-E03' . str_repeat('x', 300) . ']]';

        $this->assertDoesNotMatchRegularExpression(embed_token::PATTERN, $huge);
    }
}
