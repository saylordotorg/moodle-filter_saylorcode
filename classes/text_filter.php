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

use filter_saylorcode\local\embed_token;

/**
 * Resolves Saylor Code Studio embed tokens in filtered content.
 *
 * Turns a token such as
 *
 *     [[saylorcode:exercise=CS101-U05-E03;mode=compact]]
 *
 * into a coding workspace inside a Page, Book chapter or Lesson page, without
 * the exercise definition ever being copied into that content. One exercise,
 * many places (specification sections 5.2 and 8.5).
 *
 * @package    filter_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \core_filters\text_filter {
    /**
     * Replace every embed token in the given text.
     *
     * @param string $text The text to filter.
     * @param array $options Filter options.
     * @return string The filtered text.
     */
    #[\Override]
    public function filter($text, array $options = []) {
        // Cheap rejection first. Most text on a Moodle page contains no token,
        // and this filter runs on nearly everything.
        if (!is_string($text) || $text === '' || stripos($text, '[[saylorcode:') === false) {
            return $text;
        }

        $result = preg_replace_callback(
            embed_token::PATTERN,
            function (array $matches): string {
                return $this->render_token($matches[1], $matches[0]);
            },
            $text
        );

        // Never return null from a filter; a regex failure must leave the page
        // readable rather than blanking the content.
        return $result ?? $text;
    }

    /**
     * Render one token.
     *
     * @param string $attributes The captured attribute list.
     * @param string $original The whole original token, for author diagnostics.
     * @return string HTML.
     */
    protected function render_token(string $attributes, string $original): string {
        $token = embed_token::parse($attributes);

        if ($token === null) {
            return $this->render_invalid($original);
        }

        return $this->render_embed($token);
    }

    /**
     * Render a valid embed.
     *
     * @param embed_token $token The parsed token.
     * @return string HTML.
     */
    protected function render_embed(embed_token $token): string {
        global $OUTPUT, $PAGE, $USER;

        $exercise = (string) $token->get_exercise();

        // A guest or logged out visitor has nowhere to persist work, so they are
        // offered the stand alone activity and told plainly why, rather than
        // being given an editor that silently loses their code.
        $canpersist = isloggedin() && !isguestuser($USER);

        $data = [
            'exercise' => $exercise,
            'mode' => $token->get_mode(),
            'version' => $token->get_version(),
            'pinned' => $token->is_pinned(),
            'height' => $token->get_height(),
            'showinstructions' => $token->shows_instructions(),
            'allowfullscreen' => $token->allows_fullscreen(),
            'canpersist' => $canpersist,
            'uniqid' => \html_writer::random_id('saylorcode_embed_'),
            'activityurl' => (new \moodle_url('/local/saylorcode/exercise.php', [
                'stableid' => $exercise,
                'version' => $token->get_version(),
            ]))->out(false),
        ];

        if ($token->get_mode() === embed_token::MODE_LINK) {
            return $OUTPUT->render_from_template('filter_saylorcode/embed_link', $data);
        }

        // An embed with no backing activity has nowhere to save work and no
        // web services to call, so it would render an editor that silently
        // does nothing. Resolve the activity that carries this exercise and
        // hand rendering to the module itself, which keeps an embedded
        // workspace identical to the stand alone one rather than letting the
        // two drift apart.
        $backing = $this->find_backing_activity($exercise);

        if ($backing === null) {
            $data['unavailable'] = get_string('noactivity', 'filter_saylorcode', $exercise);
            return $OUTPUT->render_from_template('filter_saylorcode/embed_link', $data);
        }

        [$instance, $cm] = $backing;

        $modcontext = \context_module::instance($cm->id);
        if (!has_capability('mod/saylorcode:view', $modcontext)) {
            $data['unavailable'] = get_string('noactivity', 'filter_saylorcode', $exercise);
            return $OUTPUT->render_from_template('filter_saylorcode/embed_link', $data);
        }

        $renderer = $PAGE->get_renderer('mod_saylorcode');

        return \html_writer::div(
            $renderer->render_activity($instance, $cm, $modcontext),
            'saylorcode-embed saylorcode-embed-' . $token->get_mode(),
            ['data-region' => 'saylorcode-embed', 'data-exercise' => $exercise]
        );
    }

    /**
     * Find the activity in this course that carries the given exercise.
     *
     * Until the central library lands, an exercise is defined on an activity,
     * so an embed borrows the activity that already holds it. Searching the
     * current course rather than the whole site keeps one course's embed from
     * quietly resolving to another course's activity.
     *
     * @param string $exercise The stable exercise id.
     * @return array|null The instance and course module, or null when none matches.
     */
    protected function find_backing_activity(string $exercise): ?array {
        global $DB;

        $coursectx = $this->context->get_course_context(false);
        if (!$coursectx) {
            return null;
        }

        $instance = $DB->get_record('saylorcode', [
            'course' => $coursectx->instanceid,
            'stableid' => $exercise,
        ], '*', IGNORE_MULTIPLE);

        if (!$instance) {
            return null;
        }

        $cm = get_coursemodule_from_instance('saylorcode', $instance->id, $coursectx->instanceid, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }

        $modinfo = get_fast_modinfo($coursectx->instanceid);
        $cminfo = $modinfo->get_cm($cm->id);
        if (!$cminfo->uservisible) {
            return null;
        }

        return [$instance, $cminfo];
    }

    /**
     * Render the result of a token that names no valid exercise.
     *
     * Someone who can edit the content needs to know the reference is broken.
     * A student does not, and showing them a diagnostic would be noise at best
     * and confusing at worst, so for them the token simply disappears.
     *
     * @param string $original The original token text.
     * @return string HTML.
     */
    protected function render_invalid(string $original): string {
        if (!$this->viewer_can_edit()) {
            return '';
        }

        return \html_writer::div(
            get_string('invalidtoken', 'filter_saylorcode', s($original)),
            'alert alert-warning saylorcode-invalid-token',
            ['role' => 'status']
        );
    }

    /**
     * Whether the current viewer is someone who could fix a broken token.
     *
     * @return bool
     */
    protected function viewer_can_edit(): bool {
        return has_capability('moodle/course:manageactivities', $this->context)
            || has_capability('moodle/site:config', \context_system::instance());
    }
}
