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
defined('MOODLE_INTERNAL') || die;

/**
 * Class describing the Moodle block
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   block_mycoursesltc
 * @copyright 28/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 **/

/**
 * Class block_mycoursesltc
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   block_mycoursesltc
 * @copyright 28/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 */
class block_mycoursesltc extends block_base {

    /**
     * Init
     *
     * @return void
     * @throws coding_exception
     */
    public function init() : void {
        $this->title = get_string('pluginname', 'block_mycoursesltc');
    }

    /**
     * Subclasses should override this and return true if the
     * subclass block has a settings.php file.
     *
     * @return boolean
     */
    public function has_config() : bool {
        return true;
    }

    /**
     * Applicable formats.
     *
     * @return array
     */
    public function applicable_formats() : array {
        return [
            'my' => true,
        ];
    }

    /**
     * Specialization.
     *
     * Happens right after the initialisation is complete.
     *
     * @return void
     * @throws coding_exception
     */
    public function specialization() : void {

        if (empty($this->config->title)) {
            $this->title = get_string('pluginname', 'block_mycoursesltc');

            return;
        }

        $this->title = $this->config->title;
    }

    /**
     * The content object.
     *
     * @return stdObject
     * @throws coding_exception
     */
    public function get_content() {
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        if ((!isloggedin() || isguestuser() || !has_capability('block/mycoursesltc:view', $this->context))) {
            $this->content = new stdClass();
            $this->content->text = '';

            return $this->content;
        }

        // Fix ajax call.
        $USER->ajax_updatable_user_prefs['block_mycoursesltc_limit'] = true;

        $renderer = $this->page->get_renderer('block_mycoursesltc');

        $this->content = new stdClass();
        $this->content->text = $renderer->get_courses_overview();

        return $this->content;
    }

}