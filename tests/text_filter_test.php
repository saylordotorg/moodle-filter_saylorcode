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

namespace filter_saylorcode;

/**
 * Tests for resolving embed tokens in real course content.
 *
 * @package    filter_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_saylorcode\text_filter
 */
final class text_filter_test extends \advanced_testcase {
    /**
     * Build a course containing one exercise activity.
     *
     * @param string $stableid The exercise the activity carries.
     * @return array The course, the activity and a filter bound to the course.
     */
    private function build_course(string $stableid = 'CS101-U01-E01'): array {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('saylorcode', [
            'course' => $course->id,
            'stableid' => $stableid,
            'startercode' => 'public class Main {}',
        ]);

        $context = \context_course::instance($course->id);
        $filter = new text_filter($context, []);

        return [$course, $instance, $filter];
    }

    /**
     * Text with no token is returned untouched.
     */
    public function test_text_without_a_token_is_unchanged(): void {
        [, , $filter] = $this->build_course();

        $text = '<p>Ordinary course content with no exercise in it.</p>';

        $this->assertSame($text, $filter->filter($text));
    }

    /**
     * A token backed by an activity in the course becomes a workspace.
     */
    public function test_token_resolves_to_the_backing_activity(): void {
        [$course, $instance, $filter] = $this->build_course();

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $result = $filter->filter('Try it: [[saylorcode:exercise=CS101-U01-E01]]');

        $this->assertStringContainsString('saylorcode-embed', $result);
        $this->assertStringContainsString('data-exercise="CS101-U01-E01"', $result);
        // The workspace comes from the module, so the editor region is present.
        $this->assertStringContainsString('data-region="editor"', $result);
        $this->assertStringNotContainsString('[[saylorcode:', $result);
    }

    /**
     * A token naming an exercise no activity carries degrades to a link.
     *
     * An editor that cannot save is worse than an honest message, because the
     * student would lose whatever they typed into it.
     */
    public function test_token_without_a_backing_activity_degrades(): void {
        [$course, , $filter] = $this->build_course();

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $result = $filter->filter('[[saylorcode:exercise=CS101-U09-E09]]');

        $this->assertStringContainsString('is not set up in this course yet', $result);
        $this->assertStringNotContainsString('data-region="editor"', $result);
    }

    /**
     * An embed must not reach into another course for its activity.
     */
    public function test_embed_does_not_borrow_another_courses_activity(): void {
        // A course that does carry the exercise.
        $this->build_course('CS101-U01-E01');

        // A second course with no activity of its own.
        $other = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($other, 'student'));

        $filter = new text_filter(\context_course::instance($other->id), []);
        $result = $filter->filter('[[saylorcode:exercise=CS101-U01-E01]]');

        $this->assertStringContainsString('is not set up in this course yet', $result);
        $this->assertStringNotContainsString('data-region="editor"', $result);
    }

    /**
     * A flood of tokens cannot buy unbounded lookups and workspaces.
     *
     * The filter runs on student-authored content, so one forum post holding
     * hundreds of distinct tokens must not turn every reader's page view into
     * hundreds of database lookups and inline workspace renders. Past the
     * ceiling the reader gets the link, which still opens the exercise.
     */
    public function test_token_flood_degrades_to_links_past_the_ceiling(): void {
        [$course, , $filter] = $this->build_course();

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $tokens = '';
        for ($i = 1; $i <= 30; $i++) {
            $tokens .= sprintf('[[saylorcode:exercise=CS101-U01-E%02d]] ', $i);
        }

        $result = $filter->filter($tokens);

        // The first token names the real exercise, so it still resolves.
        $this->assertStringContainsString('data-region="editor"', $result);

        // Past the ceiling the token is answered without a lookup.
        $this->assertStringContainsString(
            get_string('embedlimit', 'filter_saylorcode'),
            $result
        );
    }

    /**
     * Repeats of one exercise stay free and keep rendering past the ceiling.
     */
    public function test_repeated_tokens_share_one_resolution(): void {
        [$course, , $filter] = $this->build_course();

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $result = $filter->filter(str_repeat('[[saylorcode:exercise=CS101-U01-E01]] ', 30));

        // Every repeat resolves from the cache, so none is turned away.
        $this->assertStringNotContainsString(
            get_string('embedlimit', 'filter_saylorcode'),
            $result
        );
        $this->assertSame(30, substr_count($result, 'data-exercise="CS101-U01-E01"'));
    }

    /**
     * A malformed reference is hidden from a student.
     */
    public function test_broken_reference_is_hidden_from_students(): void {
        [$course, , $filter] = $this->build_course();

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $result = $filter->filter('Broken: [[saylorcode:exercise=NOPE]]');

        $this->assertStringNotContainsString('does not name a valid exercise', $result);
        $this->assertStringNotContainsString('[[saylorcode:', $result);
    }

    /**
     * A malformed reference is reported to someone who can fix it.
     */
    public function test_broken_reference_is_shown_to_an_editor(): void {
        [$course, , $filter] = $this->build_course();

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $result = $filter->filter('Broken: [[saylorcode:exercise=NOPE]]');

        $this->assertStringContainsString('does not name a valid exercise', $result);
    }

    /**
     * Unknown attributes are discarded rather than reaching the page.
     */
    public function test_unknown_attributes_never_reach_the_output(): void {
        [$course, , $filter] = $this->build_course();

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $result = $filter->filter(
            '[[saylorcode:exercise=CS101-U01-E01;runner=http://evil.example.org;apikey=hunter2]]'
        );

        $this->assertStringNotContainsString('evil.example.org', $result);
        $this->assertStringNotContainsString('hunter2', $result);
    }
}
