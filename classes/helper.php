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
 * Helper class
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   moodle-block_mycourses
 * @copyright 28/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 **/

namespace block_mycourses;

use ArrayIterator;
use context_system;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

/**
 * Class helper
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   moodle-block_mycourses
 * @copyright 28/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 */
final class helper {

    /**
     * @return string
     * @throws \dml_exception
     */
    public static function get_default_image() : string {
        return moodle_url::make_pluginfile_url(context_system::instance()->id, 'block_mycourses', 'defaultimage',
            1, '/', '');
    }

    /**
     * @return ArrayIterator
     * @throws \coding_exception
     */
    public static function get_enrolled_courses() : ArrayIterator {

        $courses = enrol_get_my_courses('*' , 'startdate DESC');
        $records = [];
        foreach ($courses as $course) {
            $records[$course->id] = new course($course);
        }

        return new ArrayIterator($records);
    }

}
