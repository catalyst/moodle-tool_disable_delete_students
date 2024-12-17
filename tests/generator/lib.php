<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Tool disable_delete_students generator
 *
 * @package    tool_disable_delete_students
 * @category   test
 */
class tool_disable_delete_students_generator extends testing_module_generator {
    /**
     * Create a test course with specific settings
     * @param array $record
     * @return stdClass course record
     */
    public function create_test_course($record = null) {
        $record = (array)$record;
        
        if (!isset($record['startdate'])) {
            $record['startdate'] = time();
        }
        
        return $this->getDataGenerator()->create_course($record);
    }

    /**
     * Create a test user with specific role
     * @param string $role
     * @param array $record
     * @return stdClass user record
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
