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

/**
 * Language strings for the Saylor Code Studio embed filter.
 *
 * @package    filter_saylorcode
 * @copyright  2026 Saylor Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['filtername'] = 'Saylor Code Studio embeds';
$string['invalidtoken'] = 'This Saylor Code Studio embed does not name a valid exercise, so it has been hidden from students: {$a}. The expected form is [[saylorcode:exercise=CS101-U05-E03]].';
$string['loadingexercise'] = 'Loading the coding exercise.';
$string['noactivity'] = 'Exercise {$a} is not set up in this course yet, so it cannot be worked on here.';
$string['nojavascript'] = 'This coding exercise needs JavaScript.';
$string['notsignedin'] = 'Sign in to save your work on this exercise. You can still open it to read and experiment, but nothing will be kept.';
$string['openexercise'] = 'Open the exercise';
$string['openfullscreen'] = 'Open full screen';
$string['pluginname'] = 'Saylor Code Studio embeds';
$string['privacy:metadata'] = 'The Saylor Code Studio embed filter stores no personal data. It resolves a reference in course content into a coding workspace, and any work a student does is stored by the activity rather than by this filter.';
