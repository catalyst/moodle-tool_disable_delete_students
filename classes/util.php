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
 * Utility class for managing student account lifecycle operations.
 *
 * This class provides functionality for managing student accounts including:
 * - Identifying accounts for cleanup
 * - Processing account disabling and deletion based on configured rules
 * - Handling role-based exclusions
 *
 * @package    tool_disable_delete_students
 * @copyright  2024 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Waleed ul hassan <waleed.hassan@catalyst-eu.net>
 */

namespace tool_disable_delete_students;

defined('MOODLE_INTERNAL') || die();

/**
 * Utility class containing static methods for student account management.
 */
class util {
    /**
     * Get the list of roles that should be excluded from the cleanup process.
     *
     * Users with these roles will not be subject to automatic disabling or deletion,
     * even if they also have a student role.
     *
     * @return array Array of role shortnames that are excluded from processing
     */
    public static function get_excluded_roles(): array {
        return ['coursecreator', 'editingteacher', 'teacher', 'manager', 'admin'];
    }

    /**
     * Check if a user has any roles that exclude them from cleanup.
     *
     * @param int $userid The ID of the user to check
     * @return bool True if the user has any excluded roles, false otherwise
     */
    public static function has_excluded_roles(int $userid): bool {
        global $DB;

        $excludedroles = self::get_excluded_roles();
        $roles = get_user_roles(\context_system::instance(), $userid);

        foreach ($roles as $role) {
            if (in_array($role->shortname, $excludedroles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process all student accounts for potential disabling or deletion.
     *
     * This method implements the main business logic for account management:
     * - Identifies active student accounts
     * - Checks each account against configured timeframes
     * - Disables accounts that meet the disability criteria
     * - Deletes accounts that meet the deletion criteria
     * - Respects role-based exclusions
     */
    public static function process_student_accounts() {
        global $DB, $CFG;

        $disableaftercourseend = get_config('tool_disable_delete_students', 'disable_after_course_end');
        $disableaftercreation = get_config('tool_disable_delete_students', 'disable_after_creation');
        $deleteaftermonths = get_config('tool_disable_delete_students', 'delete_after_months');

        // Get all active student accounts.
        $sql = "SELECT DISTINCT u.* 
                FROM {user} u
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {role} r ON r.id = ra.roleid
                WHERE u.deleted = 0 
                AND r.shortname = 'student'";

        $students = $DB->get_records_sql($sql);

        foreach ($students as $student) {
            // Skip if user has excluded roles.
            if (self::has_excluded_roles($student->id)) {
                continue;
            }

            $shoulddisable = false;
            $shoulddelete = false;

            // Check course end date condition.
            $latestcourseend = self::get_latest_course_end_date($student->id);
            if ($latestcourseend) {
                $dayssincecourseend = (time() - $latestcourseend) / DAYSECS;
                if ($dayssincecourseend > $disableaftercourseend) {
                    $shoulddisable = true;
                }
                if ($dayssincecourseend > ($deleteaftermonths * 30)) {
                    $shoulddelete = true;
                }
            }

            // Check account creation date condition.
            $dayssincecreation = (time() - $student->timecreated) / DAYSECS;
            if ($dayssincecreation > $disableaftercreation) {
                $shoulddisable = true;
            }

            if ($shoulddelete) {
                delete_user($student);
                mtrace("Deleted user: " . $student->username);
            } else if ($shoulddisable && !$student->suspended) {
                $DB->set_field('user', 'suspended', 1, ['id' => $student->id]);
                mtrace("Disabled user: " . $student->username);
            }
        }
    }

    /**
     * Get the latest course end date for a specific user.
     *
     * Retrieves the most recent end date among all courses the user is enrolled in.
     * Only considers courses with valid end dates (enddate > 0).
     *
     * @param int $userid The ID of the user to check
     * @return int|null The timestamp of the latest course end date, or null if no valid end dates found
     */
    private static function get_latest_course_end_date(int $userid): ?int {
        global $DB;

        $sql = "SELECT MAX(c.enddate) as latest_end
                FROM {course} c
                JOIN {enrol} e ON e.courseid = c.id
                JOIN {user_enrolments} ue ON ue.enrolid = e.id
                WHERE ue.userid = :userid AND c.enddate > 0";

        $result = $DB->get_field_sql($sql, ['userid' => $userid]);
        return $result ?: null;
    }
}
