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
use coding_exception;
use context_course;
use context_helper;
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
    public static function get_my_courses($userid = 0, $fields = null, $sort = 'sortorder ASC', $limit = 0) {
        global $DB;

        // Guest account does not have any courses
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
        } else {
            if (is_string($fields)) {
                // turn the fields from a string to an array
                $fields = explode(',', $fields);
                $fields = array_map('trim', $fields);
                $fields = array_unique(array_merge($basefields, $fields));
            } else {
                if (is_array($fields)) {
                    $fields = array_unique(array_merge($basefields, $fields));
                } else {
                    throw new coding_exception('Invalid $fileds parameter in enrol_get_my_courses()');
                }
            }
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

        //note: we can not use DISTINCT + text fields due to Oracle and MS limitations, that is why we have the subselect there
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
        // $params['now1'] = time(); // improves db caching
        // $params['now2'] = $params['now1'];

        $courses = $DB->get_records_sql($sql, $params, 0, $limit);

        // preload contexts and check visibility
        foreach ($courses as $id => $course) {
//            context_helper::preload_from_record($course);
//            if (!$course->visible) {
//                if (!$context = context_course::instance($id, IGNORE_MISSING)) {
//                    unset($courses[$id]);
//                    continue;
//                }
//            }
            $enrolmentinfo = self::enrol_get_enrolment_info($id, $userid);

            $course->enrolment_start =$enrolmentinfo['startdate'];
            $course->enrolment_end =$enrolmentinfo['enddate'];
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
     * @return int|bool timestamp when active enrolment ends, false means no active enrolment now, 0 means never
     * @throws \dml_exception
     */
    public static function enrol_get_enrolment_info($courseid, $userid) : array {
        global $DB;

        $sql = "SELECT ue.*
              FROM {user_enrolments} ue
              JOIN {enrol} e ON (e.id = ue.enrolid AND e.courseid = :courseid)
              JOIN {user} u ON u.id = ue.userid
             WHERE ue.userid = :userid AND ue.status = :active AND e.status = :enabled AND u.deleted = 0";
        $params = [
            'enabled' => ENROL_INSTANCE_ENABLED,
            'active' => ENROL_USER_ACTIVE,
            'userid' => $userid,
            'courseid' => $courseid,
        ];

        if (!$enrolments = $DB->get_records_sql($sql, $params)) {
            return false;
        }

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
                // no end
            } else if (isset($changes[$end])) {
                $changes[$end] = $changes[$end] - 1;
            } else {
                $changes[$end] = -1;
            }
        }

        // let's sort then enrolment starts&ends and go through them chronologically,
        // looking for current status and the next future end of enrolment
        ksort($changes);

        $now = time();
        $current = 0;
        $present = null;

        foreach ($changes as $time => $change) {
            if ($time > $now) {
                if ($present === null) {
                    // we have just went past current time
                    $present = $current;
                    if ($present < 1) {
                        // no enrolment active
                        return [
                            'enddate' => false,
                            'startdate' => false,
                        ];
                    }
                }
                if ($present !== null) {
                    // we are already in the future - look for possible end
                    if ($current + $change < 1) {
                        return [
                            'enddate' => $time,
                            'startdate' => $started,
                        ];
                    }
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
            'startdate' => false,
        ];

    }

    /**
     * Get default course image
     *
     * @return string
     * @throws \dml_exception
     */
    public static function get_default_image() : string {
        return moodle_url::make_pluginfile_url(context_system::instance()->id, 'block_mycourses', 'defaultimage',
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
        $default = get_config('block_mycourses', 'courselimit');
        $userpreference = get_user_preferences('block_mycourses_limit', false);

        if ($userpreference) {
            return (int)$userpreference;
        }

        set_user_preference('block_mycourses_limit', $default);

        return (int)$default;
    }

}
