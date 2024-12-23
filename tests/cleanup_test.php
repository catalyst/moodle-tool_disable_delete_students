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
 * Unit tests for the tool_disable_delete_students plugin.
 *
 * @package    tool_disable_delete_students
 * @category   test
 * @copyright  2024 Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_disable_delete_students;

/**
 * Unit tests for the tool_disable_delete_students plugin.
 *
 * The tests include:
 * - Verification of excluded roles
 * - Disabling accounts based on course end dates
 * - Disabling accounts based on account creation dates
 * - Deleting accounts after a specified period
 * - Ensuring users with excluded roles are not affected
 * - Handling of students enrolled in multiple courses
 *
 * @package    tool_disable_delete_students
 * @category   test
 * @copyright  2024 Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cleanup_test extends \advanced_testcase {
    /** @var stdClass */
    protected $studentrole;
    /** @var stdClass */
    protected $teacherrole;
    /** @var stdClass */
    protected $managerrole;

    /**
     * Set up tests
     *
     * Initializes the test environment, including setting plugin
     * configurations and retrieving system roles.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest(true);
        // Set plugin configurations.
        set_config('disable_after_course_end', 21, 'tool_disable_delete_students');
        set_config('disable_after_creation', 45, 'tool_disable_delete_students');
        set_config('delete_after_months', 6, 'tool_disable_delete_students');

        // Retrieve system roles.
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
        $startdate = time() - (360 * DAYSECS); // Start date 360 days ago.
        $enddate = $startdate + $endoffset;    // End date based on offset.

        return $this->getDataGenerator()->create_course([
            'startdate' => $startdate,
            'enddate' => $enddate,
        ]);
    }

    /**
     * Test excluded roles check
     *
     * Verifies that users with excluded roles are correctly identified
     * and that users without excluded roles are not affected.
     */
    public function test_has_excluded_roles(): void {
        // Create users.
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();

        // Assign roles using system context.
        $systemcontext = \context_system::instance();
        role_assign($this->studentrole->id, $student->id, $systemcontext->id);
        role_assign($this->teacherrole->id, $teacher->id, $systemcontext->id);
        role_assign($this->managerrole->id, $manager->id, $systemcontext->id);

        // Test role checks.
        $this->assertFalse(util::has_excluded_roles($student->id));
        $this->assertTrue(util::has_excluded_roles($teacher->id));
        $this->assertTrue(util::has_excluded_roles($manager->id));
    }

    /**
     * Test account disabling based on course end date
     *
     * Verifies that student accounts are disabled correctly based on
     * the course end date criteria.
     */
    public function test_disable_after_course_end(): void {
        global $DB;

        // Create test data.
        $student = $this->getDataGenerator()->create_user();

        // Create course that ended 22 days ago.
        $course = $this->create_course_with_dates(-22 * DAYSECS);

        // Enrol student in course.
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->studentrole->id);

        // Run cleanup.
        util::process_student_accounts();

        // Check if student account was disabled.
        $updateduser = $DB->get_record('user', ['id' => $student->id]);
        $this->assertEquals(1, $updateduser->suspended);
    }

    /**
     * Test account disabling based on creation date
     *
     * Verifies that student accounts are disabled correctly based on
     * the account creation date criteria.
     */
    public function test_disable_after_creation(): void {
        global $DB;

        // Create student with old creation date.
        $student = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'timecreated', time() - (46 * DAYSECS), ['id' => $student->id]);

        // Assign student role.
        $systemcontext = \context_system::instance();
        role_assign($this->studentrole->id, $student->id, $systemcontext->id);

        // Run cleanup.
        util::process_student_accounts();

        // Check if student account was disabled.
        $updateduser = $DB->get_record('user', ['id' => $student->id]);
        $this->assertEquals(1, $updateduser->suspended);
    }

    /**
     * Test account deletion
     *
     * Verifies that student accounts are deleted correctly based on
     * the defined criteria for account deletion.
     */
    public function test_delete_after_months(): void {
        global $DB;

        // Create test data.
        $student = $this->getDataGenerator()->create_user();

        // Create course that ended 6 months ago.
        $course = $this->create_course_with_dates(-180 * DAYSECS);

        // Enrol student in course.
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->studentrole->id);

        // Run cleanup.
        util::process_student_accounts();

        // Check if student account was deleted.
        $userexists = $DB->record_exists('user', ['id' => $student->id, 'deleted' => 0]);
        $this->assertFalse($userexists);
    }

    /**
     * Test that users with excluded roles are not affected
     *
     * Verifies that users with excluded roles are protected from
     * account disabling and deletion processes.
     */
    public function test_excluded_roles_protection(): void {
        global $DB;

        // Create test data.
        $teacher = $this->getDataGenerator()->create_user();

        // Create course that ended 6 months ago.
        $course = $this->create_course_with_dates(-180 * DAYSECS);

        // Assign teacher role.
        $systemcontext = \context_system::instance();
        role_assign($this->teacherrole->id, $teacher->id, $systemcontext->id);

        // Also enrol as student.
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $this->studentrole->id);

        // Run cleanup.
        util::process_student_accounts();

        // Check that teacher account was not affected.
        $updateduser = $DB->get_record('user', ['id' => $teacher->id]);
        $this->assertEquals(0, $updateduser->suspended);
        $this->assertEquals(0, $updateduser->deleted);
    }

    /**
     * Test multiple course enrollments
     *
     * Verifies that students enrolled in multiple courses are not
     * affected by the cleanup process if they have active enrollments.
     */
    public function test_multiple_courses(): void {
        global $DB;

        // Create test data.
        $student = $this->getDataGenerator()->create_user();

        // Create two courses with different end dates.
        $oldcourse = $this->create_course_with_dates(-180 * DAYSECS); // Ended 6 months ago.
        $newcourse = $this->create_course_with_dates(30 * DAYSECS);   // Ends in 30 days.

        // Enrol student in both courses.
        $this->getDataGenerator()->enrol_user($student->id, $oldcourse->id, $this->studentrole->id);
        $this->getDataGenerator()->enrol_user($student->id, $newcourse->id, $this->studentrole->id);

        // Run cleanup.
        util::process_student_accounts();

        // Check that student account was not affected (due to active enrollment).
        $updateduser = $DB->get_record('user', ['id' => $student->id]);
        $this->assertEquals(0, $updateduser->suspended);
        $this->assertEquals(0, $updateduser->deleted);
    }
}
