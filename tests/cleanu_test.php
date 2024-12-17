<?php
namespace tool_disable_delete_students;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for student account cleanup
 *
 * @package    tool_disable_delete_students
 * @category   test
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_test extends \advanced_testcase {
    /** @var stdClass */
    protected $studentrole;
    /** @var stdClass */
    protected $teacherrole;
    /** @var stdClass */
    protected $managerrole;

    /**
     * Set up tests
     */
    protected function setUp(): void {
        global $DB;
        $this->resetAfterTest(true);
        
        // Set plugin configs
        set_config('disable_after_course_end', 21, 'tool_disable_delete_students');
        set_config('disable_after_creation', 45, 'tool_disable_delete_students');
        set_config('delete_after_months', 6, 'tool_disable_delete_students');

        // Get system roles
        $this->studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        $this->managerrole = $DB->get_record('role', ['shortname' => 'manager']);
    }

    /**
     * Create a course with valid start and end dates
     *
     * @param int $endoffset Number of seconds to offset the end date from start
     * @return stdClass The created course
     */
    private function create_course_with_dates(int $endoffset): \stdClass {
        $startdate = time() - (360 * DAYSECS); // Start date 360 days ago
        $enddate = $startdate + $endoffset;    // End date based on offset

        return $this->getDataGenerator()->create_course([
            'startdate' => $startdate,
            'enddate' => $enddate
        ]);
    }

    /**
     * Test excluded roles check
     */
    public function test_has_excluded_roles() {
        // Create users
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();

        // Assign roles using system context
        $systemcontext = \context_system::instance();
        role_assign($this->studentrole->id, $student->id, $systemcontext->id);
        role_assign($this->teacherrole->id, $teacher->id, $systemcontext->id);
        role_assign($this->managerrole->id, $manager->id, $systemcontext->id);

        // Test role checks
        $this->assertFalse(util::has_excluded_roles($student->id));
        $this->assertTrue(util::has_excluded_roles($teacher->id));
        $this->assertTrue(util::has_excluded_roles($manager->id));
    }

    /**
     * Test account disabling based on course end date
     */
    public function test_disable_after_course_end() {
        global $DB;

        // Create test data
        $student = $this->getDataGenerator()->create_user();
        
        // Create course that ended 22 days ago
        $course = $this->create_course_with_dates(-22 * DAYSECS);

        // Enrol student in course
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->studentrole->id);

        // Run cleanup
        util::process_student_accounts();

        // Check if student account was disabled
        $updated_user = $DB->get_record('user', ['id' => $student->id]);
        $this->assertEquals(1, $updated_user->suspended);
    }

    /**
     * Test account disabling based on creation date
     */
    public function test_disable_after_creation() {
        global $DB;

        // Create student with old creation date
        $student = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'timecreated', time() - (46 * DAYSECS), ['id' => $student->id]);

        // Assign student role
        $systemcontext = \context_system::instance();
        role_assign($this->studentrole->id, $student->id, $systemcontext->id);

        // Run cleanup
        util::process_student_accounts();

        // Check if student account was disabled
        $updated_user = $DB->get_record('user', ['id' => $student->id]);
        $this->assertEquals(1, $updated_user->suspended);
    }

    /**
     * Test account deletion
     */
    public function test_delete_after_months() {
        global $DB;

        // Create test data
        $student = $this->getDataGenerator()->create_user();
        
        // Create course that ended 6 months ago
        $course = $this->create_course_with_dates(-180 * DAYSECS);

        // Enrol student in course
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->studentrole->id);

        // Run cleanup
        util::process_student_accounts();

        // Check if student account was deleted
        $user_exists = $DB->record_exists('user', ['id' => $student->id, 'deleted' => 0]);
        $this->assertFalse($user_exists);
    }

    /**
     * Test that users with excluded roles are not affected
     */
    public function test_excluded_roles_protection() {
        global $DB;

        // Create test data
        $teacher = $this->getDataGenerator()->create_user();
        
        // Create course that ended 6 months ago
        $course = $this->create_course_with_dates(-180 * DAYSECS);

        // Assign teacher role
        $systemcontext = \context_system::instance();
        role_assign($this->teacherrole->id, $teacher->id, $systemcontext->id);

        // Also enrol as student
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $this->studentrole->id);

        // Run cleanup
        util::process_student_accounts();

        // Check that teacher account was not affected
        $updated_user = $DB->get_record('user', ['id' => $teacher->id]);
        $this->assertEquals(0, $updated_user->suspended);
        $this->assertEquals(0, $updated_user->deleted);
    }

    /**
     * Test multiple course enrollments
     */
    public function test_multiple_courses() {
        global $DB;

        // Create test data
        $student = $this->getDataGenerator()->create_user();
        
        // Create two courses with different end dates
        $oldcourse = $this->create_course_with_dates(-180 * DAYSECS); // Ended 6 months ago
        $newcourse = $this->create_course_with_dates(30 * DAYSECS);   // Ends in 30 days

        // Enrol student in both courses
        $this->getDataGenerator()->enrol_user($student->id, $oldcourse->id, $this->studentrole->id);
        $this->getDataGenerator()->enrol_user($student->id, $newcourse->id, $this->studentrole->id);

        // Run cleanup
        util::process_student_accounts();

        // Check that student account was not affected (due to active enrollment)
        $updated_user = $DB->get_record('user', ['id' => $student->id]);
        $this->assertEquals(0, $updated_user->suspended);
        $this->assertEquals(0, $updated_user->deleted);
    }
}
