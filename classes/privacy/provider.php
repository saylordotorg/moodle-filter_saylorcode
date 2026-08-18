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

namespace filter_saylorcode\privacy;

use core_privacy\local\metadata\null_provider;

/**
 * Privacy implementation for the Saylor Code Studio embed filter.
 *
 * The filter is a pure text transformation. It turns a reference in course
 * content into a workspace and stores nothing itself; any work a student does
 * belongs to mod_saylorcode.
 *
 * @package    filter_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements null_provider {

    /**
     * Explain why this plugin stores no user data.
     *
     * @return string The identifier of a string describing the reason.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
