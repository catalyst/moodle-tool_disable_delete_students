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
 * Settings for the tool_disable_delete_students plugin.
 *
 * @package    tool_disable_delete_students
 * @copyright  2024 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Waleed ul hassan <waleed.hassan@catalyst-eu.net>
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_disable_delete_students', get_string('pluginname', 'tool_disable_delete_students'));
    $ADMIN->add('tools', $settings);

    // Days after course end to disable account.
    $settings->add(new admin_setting_configtext(
        'tool_disable_delete_students/disable_after_course_end',
        get_string('disable_after_course_end', 'tool_disable_delete_students'),
        get_string('disable_after_course_end_desc', 'tool_disable_delete_students'),
        21,
        PARAM_INT
    ));

    // Days after creation to disable account.
    $settings->add(new admin_setting_configtext(
        'tool_disable_delete_students/disable_after_creation',
        get_string('disable_after_creation', 'tool_disable_delete_students'),
        get_string('disable_after_creation_desc', 'tool_disable_delete_students'),
        45,
        PARAM_INT
    ));

    // Months after course end to delete account.
    $settings->add(new admin_setting_configtext(
        'tool_disable_delete_students/delete_after_months',
        get_string('delete_after_months', 'tool_disable_delete_students'),
        get_string('delete_after_months_desc', 'tool_disable_delete_students'),
        6,
        PARAM_INT
    ));
}
