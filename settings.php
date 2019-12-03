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
 * Settings
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   moodle-block_mycoursesltc
 * @copyright 28/10/2019 Mfreak.nl | LdesignMedia.nl - Luuk Verhoeven
 * @author    Luuk Verhoeven
 **/
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configstoredfile('block_mycoursesltc/defaultcourseimage',
        get_string('settings:defaultimage', 'block_mycoursesltc'),
        get_string('settings:defaultimagedesc', 'block_mycoursesltc'),
        'defaultimage', 1, ['maxfiles' => 1, 'accepted_types' => ['.jpeg', '.jpg', '.png', '.svg']]));

    $choices = [
        8 => 8,
        16 => 16,
        24 => 24,
    ];
    $settings->add(new admin_setting_configselect('block_mycoursesltc/courselimit',
        get_string('settings:courselimit', 'block_mycoursesltc'),
        get_string('settings:courselimit_desc', 'block_mycoursesltc'),
        6, $choices));

}