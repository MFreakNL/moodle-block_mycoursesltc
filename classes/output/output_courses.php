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
 * Output class for the student course overview.
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   moodle-block_mycoursesltc
 * @copyright 28/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 **/

namespace block_mycoursesltc\output;
defined('MOODLE_INTERNAL') || die;

use ArrayIterator;
use block_mycoursesltc\helper;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Class output_courses
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   moodle-block_mycoursesltc
 * @copyright 28/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 */
class output_courses implements renderable, templatable {

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     *
     * @return stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function export_for_template(renderer_base $output) : stdClass {
        global $CFG;

        $data = new stdClass();
        $data->courses = helper::get_enrolled_courses();
        $data->itemsperpage = $this->get_items_per_page();
        $data->courselimit = helper::get_course_limit();
        $data->wwwroot = $CFG->wwwroot;

        return $data;
    }

    /**
     * @return ArrayIterator
     * @throws \dml_exception
     * @throws \coding_exception
     */
    private function get_items_per_page() : ArrayIterator {
        $limit = helper::get_course_limit();

        $list = [
            8 => ['value' => 8],
            16 => ['value' => 16],
            24 => ['value' => 24],
        ];

        $list = array_map(static function ($item) use ($limit) {
            if ($limit === $item['value']) {
                $item += ['active' => true];
            }

            return $item;
        }, $list);

        return new ArrayIterator($list);
    }
}