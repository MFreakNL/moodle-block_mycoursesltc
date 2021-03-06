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
 * @package   block_mycoursesltc
 * @copyright 28/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 **/

namespace block_mycoursesltc;

use ArrayIterator;
use coding_exception;
use context_helper;
use context_system;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

/**
 * Class helper
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   block_mycoursesltc
 * @copyright 28/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 */
final class helper {

    /**
     * Returns list of courses userID is enrolled in and can access
     *
     * - $fields is an array of field names to ADD
     *   so name the fields you really need, which will
     *   be added and uniq'd
     *
     * @param int          $userid
     * @param string|array $fields
     * @param string       $sort
     * @param int          $limit max number of courses
     *
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_my_courses(int $userid = 0, $fields = null, string $sort = 'sortorder ASC', int $limit = 0) : array {
        global $DB;

        // Guest account does not have any courses.
        if (isguestuser() or !isloggedin()) {
            return ([]);
        }

        $basefields = [
            'id',
            'category',
            'sortorder',
            'shortname',
            'fullname',
            'idnumber',
            'startdate',
            'visible',
            'groupmode',
            'groupmodeforce',
            'cacherev',
        ];

        if (empty($fields)) {
            $fields = $basefields;

        } else if (is_string($fields)) {

            // Turn the fields from a string to an array.
            $fields = explode(',', $fields);
            $fields = array_map('trim', $fields);
            $fields = array_unique(array_merge($basefields, $fields));

        } else if (is_array($fields)) {
            $fields = array_unique(array_merge($basefields, $fields));
        } else {
            throw new coding_exception('Invalid $fileds parameter in enrol_get_my_courses()');
        }
        if (in_array('*', $fields)) {
            $fields = ['*'];
        }

        $orderby = "";
        $sort = trim($sort);
        if (!empty($sort)) {
            $rawsorts = explode(',', $sort);
            $sorts = [];
            foreach ($rawsorts as $rawsort) {
                $rawsort = trim($rawsort);
                if (strpos($rawsort, 'c.') === 0) {
                    $rawsort = substr($rawsort, 2);
                }
                $sorts[] = trim($rawsort);
            }
            $sort = 'c.' . implode(',c.', $sorts);
            $orderby = "ORDER BY $sort";
        }

        $wheres = ["c.id <> :siteid"];
        $params = ['siteid' => SITEID];

        $coursefields = 'c.' . join(',c.', $fields);
        $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = CONTEXT_COURSE;
        $wheres = implode(" AND ", $wheres);

        // Note we can not use DISTINCT + text fields due to Oracle and MS limitations, that is why we have the subselect there.
        $sql = "SELECT $coursefields $ccselect
              FROM {course} c
              JOIN (SELECT DISTINCT e.courseid
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = :userid)
                     WHERE ue.status = :active AND e.status = :enabled
                   ) en ON (en.courseid = c.id)
           $ccjoin
             WHERE $wheres
          $orderby";
        $params['userid'] = $userid;
        $params['active'] = ENROL_USER_ACTIVE;
        $params['enabled'] = ENROL_INSTANCE_ENABLED;

        $courses = $DB->get_records_sql($sql, $params, 0, $limit);

        // Preload contexts and check visibility.
        foreach ($courses as $id => $course) {
            $enrolmentinfo = self::enrol_get_enrolment_info($id, $userid);

            $course->enrolment_active = is_enrolled(\context_course::instance($course->id), $userid, '', true);
            $course->enrolment_start = $enrolmentinfo['startdate'] ?? false;
            $course->enrolment_end = $enrolmentinfo['enddate'] ?? false;

            $courses[$id] = $course;
        }

        return $courses;
    }

    /**
     * This function returns the end of current active user enrolment.
     *
     * It deals correctly with multiple overlapping user enrolments.
     *
     * @param int $courseid
     * @param int $userid
     *
     * @return array
     * @throws \dml_exception
     */
    public static function enrol_get_enrolment_info(int $courseid, int $userid) : array {
        global $DB;

        $sql = "SELECT ue.*
              FROM {user_enrolments} ue
              JOIN {enrol} e ON (e.id = ue.enrolid AND e.courseid = :courseid)
              JOIN {user} u ON u.id = ue.userid
             WHERE ue.userid = :userid AND e.status = :enabled AND u.deleted = 0";
        $params = [
            'enabled' => ENROL_INSTANCE_ENABLED,
            'userid' => $userid,
            'courseid' => $courseid,
        ];

        if (!$enrolments = $DB->get_records_sql($sql, $params)) {
            return [
                'enddate' => false,
                'startdate' => false,
            ];
        }
        // @TODO rewrite this logic.
        $changes = [];
        $started = 0;
        foreach ($enrolments as $ue) {
            $start = (int)$ue->timestart;
            $end = (int)$ue->timeend;

            if ($start > $started) {
                $started = $start;
            }

            if ($end != 0 and $end < $start) {
                debugging('Invalid enrolment start or end in user_enrolment id:' . $ue->id);
                continue;
            }
            if (isset($changes[$start])) {
                $changes[$start] = $changes[$start] + 1;
            } else {
                $changes[$start] = 1;
            }

            if ($end === 0) {
                continue;
            }

            if (isset($changes[$end])) {
                $changes[$end] = $changes[$end] - 1;
            } else {
                $changes[$end] = -1;
            }
        }

        // Let's sort then enrolment starts&ends and go through them chronologically.
        // Looking for current status and the next future end of enrolment.
        ksort($changes);

        $now = time();
        $current = 0;
        $present = null;

        foreach ($changes as $time => $change) {
            if ($time > $now) {
                if ($present === null) {
                    // We have just went past current time.
                    $present = $current;
                    if ($present < 1) {
                        // No enrolment active.
                        return [
                            'enddate' => false,
                            'startdate' => $started,
                        ];
                    }
                }
                // We are already in the future - look for possible end.
                if (($present !== null) && $current + $change < 1) {
                    return [
                        'enddate' => $time,
                        'startdate' => $started,
                    ];
                }
            }
            $current += $change;
        }

        if ($current > 0) {
            return [
                'enddate' => 0,
                'startdate' => $started,
            ];
        }

        return [
            'enddate' => false,
            'startdate' => $started,
        ];
    }

    /**
     * Get default course image
     *
     * @return string
     * @throws \dml_exception
     */
    public static function get_default_image() : string {
        return moodle_url::make_pluginfile_url(context_system::instance()->id, 'block_mycoursesltc', 'defaultimage',
            1, '/', '');
    }

    /**
     * Get user their enrolled courses.
     *
     * @return ArrayIterator
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_enrolled_courses() : ArrayIterator {
        global $USER;
        $courses = self::get_my_courses($USER->id);
        $records = [];
        foreach ($courses as $course) {
            $records[$course->id] = new course($course);
        }

        return new ArrayIterator($records);
    }

    /**
     * Get course limit
     *
     * @return int
     *
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public static function get_course_limit() : int {
        $default = get_config('block_mycoursesltc', 'courselimit');
        $userpreference = get_user_preferences('block_mycoursesltc_limit', false);

        if ($userpreference) {
            return (int)$userpreference;
        }

        set_user_preference('block_mycoursesltc_limit', $default);

        return (int)$default;
    }

    /**
     * @param string $setting
     *
     * @return string
     * @throws \dml_exception
     */
    protected static function get_settings_image(string $setting) : string {
        $file = get_config('block_mycoursesltc', $setting);

        return moodle_url::make_pluginfile_url(context_system::instance()->id, 'block_mycoursesltc',
            'block_mycoursesltc_' . $setting, 0, '', $file);
    }

    /**
     * @return string
     * @throws \dml_exception
     */
    public static function course_expired_image() : string {
        return self::get_settings_image('course_expired_image');
    }

    /**
     * @return bool
     * @throws \dml_exception
     */
    public static function has_course_expired_image() : bool {
        $file = get_config('block_mycoursesltc', 'course_hidden_image');

        return !empty($file);
    }

    /**
     * @return string
     * @throws \dml_exception
     */
    public static function course_hidden_image() : string {
        return self::get_settings_image('course_hidden_image');
    }

    /**
     * @return bool
     * @throws \dml_exception
     */
    public static function has_course_hidden_image() : bool {
        $file = get_config('block_mycoursesltc', 'course_expired_image');

        return !empty($file);
    }

    /**
     * Returns the course percentage completed by a certain user, returns null if no completion data is available.
     *
     * @param \stdClass $course Moodle course object
     * @param int       $userid The id of the user, 0 for the current user
     *
     * @return null|float The percentage, or null if completion is not supported in the course,
     *         or there are no activities that support completion.
     */
    public static function get_course_progress_percentage($course, $userid = 0) {
        global $USER;

        // Make sure we continue with a valid userid.
        if (empty($userid)) {
            $userid = $USER->id;
        }

        $completion = new \completion_info($course);

        // First, let's make sure completion is enabled.
        if (!$completion->is_enabled()) {
            return null;
        }

        // Disable this causes trouble when enrolment is expired.
        //        if (!$completion->is_tracked_user($userid)) {
        //            return null;
        //        }

        // Before we check how many modules have been completed see if the course has.
        if ($completion->is_course_complete($userid)) {
            return 100;
        }

        // Get the number of modules that support completion.
        $modules = $completion->get_activities();
        $count = count($modules);
        if (!$count) {
            return null;
        }

        // Get the number of modules that have been completed.
        $completed = 0;
        foreach ($modules as $module) {
            $data = $completion->get_data($module, true, $userid);
            $completed += $data->completionstate == COMPLETION_INCOMPLETE ? 0 : 1;
        }

        return ($completed / $count) * 100;
    }
}