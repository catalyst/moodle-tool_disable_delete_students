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
     * @throws \dml_exception
     * @throws \coding_exception
     */
    public static function has_excluded_roles(int $userid): bool {
        // Use has_capability() instead of direct DB query.
        $context = \context_system::instance();
        return has_capability('tool/disable_delete_students:protected', $context, $userid);
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
    public static function process_student_accounts(): void {
        global $DB;

        // Get all users who are not already deleted or suspended.
        $sql = "SELECT u.*
                FROM {user} u
                WHERE u.deleted = 0
                AND u.suspended = 0
                AND u.id > 1"; // Exclude admin user.

        $users = $DB->get_records_sql($sql);

        foreach ($users as $user) {
            // Skip users with excluded roles.
            if (self::has_excluded_roles($user->id)) {
                continue;
            }

            // Check if user should be deleted (enrolled in courses that ended more than 6 months ago).
            if (self::should_delete_user($user)) {
                delete_user($user);
                mtrace("Deleted user: {$user->username}");
                continue;
            }

            // Check if user should be disabled.
            if (self::should_disable_user($user)) {
                $user->suspended = 1;
                $DB->update_record('user', $user);
                mtrace("Disabled user: {$user->username}");
            }
        }
    }

    /**
     * Check if a user should be deleted
     *
     * @param \stdClass $user The user record to check
     * @return bool True if the user should be deleted
     * @throws \dml_exception
     */
    private static function should_delete_user(\stdClass $user): bool {
        global $DB;

        // Get the user's most recent course end date.
        $sql = "SELECT MAX(c.enddate) as lastenddate
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {course} c ON c.id = e.courseid
                WHERE ue.userid = :userid";

        $params = ['userid' => $user->id];
        $record = $DB->get_record_sql($sql, $params);

        if (!$record || !$record->lastenddate) {
            return false;
        }

        // Delete if the last course ended more than 6 months ago.
        return ($record->lastenddate < time() - (180 * DAYSECS));
    }

    /**
     * Check if a user should be disabled
     *
     * @param \stdClass $user The user record to check
     * @return bool True if the user should be disabled
     * @throws \dml_exception
     */
    private static function should_disable_user(\stdClass $user): bool {
        global $DB;

        // First check if user has any course enrollments.
        $hasenrollments = $DB->record_exists('user_enrolments', ['userid' => $user->id]);

        if (!$hasenrollments) {
            // If no enrollments, check account age.
            return ($user->timecreated < time() - (45 * DAYSECS));
        }

        // Check for active or future courses.
        $sql = "SELECT 1
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {course} c ON c.id = e.courseid
                WHERE ue.userid = :userid
                AND (c.enddate = 0 OR c.enddate > :cutoffdate)";

        $params = [
            'userid' => $user->id,
            'cutoffdate' => time() - (21 * DAYSECS),
        ];

        // If there are any active or future courses, don't disable.
        if ($DB->record_exists_sql($sql, $params)) {
            return false;
        }

        // User has only old course enrollments or old account.
        return true;
    }

    /**
     * Get the latest course end date for a specific user.
     *
     * Retrieves the most recent end date among all courses the user is enrolled in.
     * Only considers courses with valid end dates (enddate > 0).
     *
     * @param int $userid The ID of the user to check
     * @return int|null The timestamp of the latest course end date, or null if no valid end dates found
     * @throws \dml_exception
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
