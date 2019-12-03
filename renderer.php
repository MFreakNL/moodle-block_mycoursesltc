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
 * Renderer class UI.
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   moodle-block_mycoursesltc
 * @copyright 28/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 **/

use block_mycoursesltc\output\output_courses;

defined('MOODLE_INTERNAL') || die;

/**
 * Class block_mycoursesltc_renderer
 *
 * @package   moodle-block_mycoursesltc
 * @copyright 28/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 */
class block_mycoursesltc_renderer extends plugin_renderer_base {

    /**
     * Get overview of active courses
     *
     * @return string
     * @throws moodle_exception
     */
    public function get_courses_overview() : string {
        global $PAGE;
        $PAGE->requires->js('/blocks/mycoursesltc/javascript/masonry.js' , true);
        $context = new output_courses();

        return parent::render_from_template('block_mycoursesltc/courses_overview',
            $context->export_for_template($this));
    }

}