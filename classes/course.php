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
 * Course decorator class
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   block_mycoursesltc
 * @copyright 29/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 **/

namespace block_mycoursesltc;
defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/completionlib.php');

use completion_completion;
use completion_info;
use core_completion\progress;
use grade_grade;
use grade_item;
use lang_string;

/**
 * Class course
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   block_mycoursesltc
 * @copyright 29/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 */
class course {

    /**
     * @var \stdClass
     */
    private $course;

    /**
     * @var \stdClass
     */
    private $completion;

    /**
     * course constructor.
     *
     * @param \stdClass $course
     */
    public function __construct(\stdClass $course) {
        $this->course = $course;
    }

    /**
     * The date the user is enrolled
     *
     * @return string
     */
    public function date_started() : string {
        return date('d-m-Y', $this->course->enrolment_start);
    }

    /**
     * Get user there coursegrade
     *
     * @return string
     */
    public function grade() : string {
        global $USER;

        $gradeitem = grade_item::fetch_all([
            'userid' => $USER->id,
            'courseid' => $this->get_id(),
            'itemtype' => 'course',
        ]);

        if (empty($gradeitem)) {
            return '';
        }

        $gradeitem = reset($gradeitem);
        $gradegrades = grade_grade::fetch_users_grades($gradeitem, [$USER->id], true);
        $grade = reset($gradegrades);

        if (empty($grade->finalgrade)) {
            return '';
        }

        return grade_format_gradevalue($grade->finalgrade, $gradeitem, true,
            GRADE_DISPLAY_TYPE_REAL, 2);
    }

    /**
     * Get the url to the course
     *
     * @return string
     * @throws \moodle_exception
     */
    public function courseurl() : string {
        return (new \moodle_url('/course/view.php', ['id' => $this->course->id]))->out(false);
    }

    /**
     * Check if course is started
     *
     * @return bool
     * @throws \coding_exception
     */
    public function course_is_started() : bool {
        $completion = $this->completion();

        return $completion->progress > 0;
    }

    /**
     * Display progressbar text
     *
     * @return string
     * @throws \coding_exception
     */
    public function progress_text() : string {
        $completion = $this->completion();

        if (empty($completion->progress)) {
            return new lang_string('text:course_not_started', __NAMESPACE__);
        }

        if ($completion->progress == 100 && $completion->timecompleted === '01-01-1970') {
            return new lang_string('text:course_finished_no_date', __NAMESPACE__);
        }

        if ($completion->progress == 100 && $completion->timecompleted > 0) {
            return new lang_string('text:course_finished', __NAMESPACE__, ['date' => $completion->timecompleted]);
        }

        return $completion->progress . ' %';
    }

    /**
     * Load course image
     *
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function image() : string {
        global $CFG;

        // Todo Caching.
        $fs = get_file_storage();
        $context = \context_course::instance($this->course->id);

        if($this->enrolment_is_ended() && helper::has_course_expired_image()){
            return helper::course_expired_image();
        }

        if($this->course_is_hidden() && helper::has_course_hidden_image()){
            return helper::course_hidden_image();
        }

        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', false, 'timecreated');
        foreach ($files as $file) {

            if (!$file->is_valid_image()) {
                continue;
            }

            return file_encode_url("$CFG->wwwroot/pluginfile.php",
                '/' . $file->get_contextid() . '/' . $file->get_component() . '/' . $file->get_filearea() .
                $file->get_filepath() . $file->get_filename(), false);
        }

        return helper::get_default_image();
    }

    /**
     * Get user their completion percentage
     *
     * @return float
     * @throws \coding_exception
     */
    public function completion_percentage() : float {
        $completion = $this->completion();

        return $completion->progress;
    }

    /**
     * Get course fullname
     *
     * @return string
     */
    public function fullname() : string {
        return $this->course->fullname;
    }

    /**
     * Get course id
     *
     * @return int
     */
    public function get_id() : int {
        return $this->course->id;
    }

    /**
     * Get user completion
     *
     * @return \stdClass
     * @throws \coding_exception
     */
    protected function completion() : \stdClass {
        global $USER;

        if (isset($this->completion)) {
            return $this->completion;
        }

        $completion = new completion_info($this->course);

        // First, let's make sure completion is enabled.
        if (!$completion->is_enabled()) {
            $this->completion = (object)[
                'completed' => 0,
                'progress' => 0,
            ];

            return $this->completion;
        }

        $percentage = progress::get_course_progress_percentage($this->course);

        $params = [
            'userid' => $USER->id,
            'course' => $this->get_id(),
        ];
        $completion = new completion_completion($params);

        $return['timecompleted'] = date('d-m-Y', $completion->timecompleted);
        $return['completed'] = $completion->is_complete();
        $return['progress'] = empty($percentage) ? 0.00 : floor($percentage);

        $this->completion = (object)$return;

        return $this->completion;
    }

    /**
     * @return bool
     */
    public function enrolment_is_ended() : bool {
        if ($this->course->enrolment_end === false) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    private function course_is_hidden() : bool {
        return empty($this->course->visible);
    }

}