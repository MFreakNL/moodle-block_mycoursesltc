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
 * Lib functions
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   block_mycoursesltc
 * @copyright 29/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 **/

/**
 * Serve the file.
 *
 * @param stdClass      $course
 * @param stdClass      $cm
 * @param context_block $context
 * @param string        $filearea
 * @param array         $args
 * @param bool          $forcedownload
 * @param bool          $sendfileoptions
 *
 * @return void may terminate if file not found or do not die not specified
 * @throws coding_exception
 * @throws moodle_exception
 */
function block_mycoursesltc_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $sendfileoptions) {
    global $CFG;
    require_once("$CFG->libdir/filelib.php");

    // No access control here.
    $options['cacheability'] = 'public';

    $fs = get_file_storage();
    $itemid = $args[0];

    // Get the file.
    $files = $fs->get_area_files($context->id, 'block_mycoursesltc', 'defaultimage', 1);

    if (!empty($files)) {

        foreach ($files as $file) {

            if ($file->is_directory()) {
                continue;
            }

            \core\session\manager::write_close(); // Unlock session during file serving.
            send_stored_file($file, null, 0, $forcedownload, $options);

            return;
        }
    }

    send_file_not_found();
}