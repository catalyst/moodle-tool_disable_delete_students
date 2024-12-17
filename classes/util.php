<?php
namespace tool_disable_delete_students;

defined('MOODLE_INTERNAL') || die();

class util {
    /**
     * Get roles that should be excluded from cleanup
     * @return array
     */
    public static function get_excluded_roles(): array {
        return ['coursecreator', 'editingteacher', 'teacher', 'manager', 'admin'];
    }

    /**
     * Check if user has any excluded roles
     * @param int $userid
     * @return bool
     */
    public static function has_excluded_roles(int $userid): bool {
        global $DB;
        
        $excluded_roles = self::get_excluded_roles();
        $roles = get_user_roles(\context_system::instance(), $userid);
        
        foreach ($roles as $role) {
            if (in_array($role->shortname, $excluded_roles)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Process student accounts
     */
    public static function process_student_accounts() {
        global $DB, $CFG;

        $disable_after_course_end = get_config('tool_disable_delete_students', 'disable_after_course_end');
        $disable_after_creation = get_config('tool_disable_delete_students', 'disable_after_creation');
        $delete_after_months = get_config('tool_disable_delete_students', 'delete_after_months');

        // Get all active student accounts
        $sql = "SELECT DISTINCT u.* 
                FROM {user} u
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {role} r ON r.id = ra.roleid
                WHERE u.deleted = 0 
                AND r.shortname = 'student'";
        
        $students = $DB->get_records_sql($sql);

        foreach ($students as $student) {
            // Skip if user has excluded roles
            if (self::has_excluded_roles($student->id)) {
                continue;
            }

            $should_disable = false;
            $should_delete = false;

            // Check course end date condition
            $latest_course_end = self::get_latest_course_end_date($student->id);
            if ($latest_course_end) {
                $days_since_course_end = (time() - $latest_course_end) / DAYSECS;
                if ($days_since_course_end > $disable_after_course_end) {
                    $should_disable = true;
                }
                if ($days_since_course_end > ($delete_after_months * 30)) {
                    $should_delete = true;
                }
            }

            // Check account creation date condition
            $days_since_creation = (time() - $student->timecreated) / DAYSECS;
            if ($days_since_creation > $disable_after_creation) {
                $should_disable = true;
            }

            if ($should_delete) {
                delete_user($student);
                mtrace("Deleted user: " . $student->username);
            } elseif ($should_disable && !$student->suspended) {
                $DB->set_field('user', 'suspended', 1, ['id' => $student->id]);
                mtrace("Disabled user: " . $student->username);
            }
        }
    }

    /**
     * Get the latest course end date for a user
     * @param int $userid
     * @return int|null
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
