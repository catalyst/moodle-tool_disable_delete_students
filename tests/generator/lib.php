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
 * Generator for the tool_disable_delete_students plugin.
 *
 * This class provides methods for creating test courses and users
 * with specific roles for testing purposes.
 *
 * @package    tool_disable_delete_students
 * @copyright  2024 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Waleed ul hassan <waleed.hassan@catalyst-eu.net>
 */

/**
 * Tool disable_delete_students generator
 *
 * @package    tool_disable_delete_students
 * @category   test
 */
class tool_disable_delete_students_generator extends testing_module_generator {
    /**
     * Create a test course with specific settings.
     *
     * @param array|null $record Optional settings for the course.
     * @return stdClass Course record.
     */
    public function create_test_course($record = null) {
        $record = (array)$record;
        if (!isset($record['startdate'])) {
            $record['startdate'] = time();
        }
        return $this->getDataGenerator()->create_course($record);
    }

    /**
     * Create a test user with a specific role.
     *
     * @param string $role The role to assign to the user.
     * @param array|null $record Optional settings for the user.
     * @return stdClass User record.
     * @throws dml_exception|coding_exception If there is a database error while assigning the role.
     */
    public function create_test_user_with_role($role, $record = null) {
        global $DB;
        $user = $this->getDataGenerator()->create_user($record);
        $systemcontext = \context_system::instance();
        $rolerecord = $DB->get_record('role', ['shortname' => $role]);
        if ($rolerecord) {
            role_assign($rolerecord->id, $user->id, $systemcontext->id);
        }
        return $user;
    }
}
