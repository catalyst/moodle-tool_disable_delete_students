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

namespace tool_disable_delete_students\task;
use tool_disable_delete_students\util;

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
 * @covers \tool_disable_delete_students\task\cleanup_students
 */
final class cleanup_test extends \advanced_testcase {
    /** @var int Role ID for student */
    protected $studentrole;
    /** @var int Role ID for teacher */
    protected $teacherrole;
    /** @var int Role ID for manager */
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

        // Create roles and store their IDs directly.
        $this->studentrole = $this->getDataGenerator()->create_role();
        $this->teacherrole = $this->getDataGenerator()->create_role();
        $this->managerrole = $this->getDataGenerator()->create_role();

        // Get system context for capability assignment.
        $systemcontext = \context_system::instance();

        // Assign the "protected account" capability to teacher and manager roles.
        $capability = 'tool/disable_delete_students:protected'; // Corrected capability name.
        assign_capability($capability, CAP_ALLOW, $this->teacherrole, $systemcontext->id);
        assign_capability($capability, CAP_ALLOW, $this->managerrole, $systemcontext->id);
    }

    /**
     * Create a course with valid start and end dates
     *
     * @param int $endoffset Number of seconds to offset the end date from start
     * @return \stdClass The created course
     */
    private function create_course_with_dates(int $endoffset): \stdClass {
        $startdate = time() - (360 * DAYSECS); // Start date 360 days ago.
        $enddate = time() + $endoffset;    // End date based on offset.

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
        role_assign($this->studentrole, $student->id, $systemcontext->id);
        role_assign($this->teacherrole, $teacher->id, $systemcontext->id);
        role_assign($this->managerrole, $manager->id, $systemcontext->id);

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
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();

        // Create course that ended 22 days ago.
        $oldcourse = $this->create_course_with_dates(-22 * DAYSECS);
        // Create course that ends in the future.
        $activecourse = $this->create_course_with_dates(30 * DAYSECS);

        // Enrol students in courses.
        $this->getDataGenerator()->enrol_user($student1->id, $oldcourse->id, $this->studentrole);
        $this->getDataGenerator()->enrol_user($student2->id, $activecourse->id, $this->studentrole);

        // Verify both accounts are active before running the task.
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $student1->id]));
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $student2->id]));

        // Start output buffering.
        ob_start();

        // Run cleanup.
        util::process_student_accounts();

        // Check if student accounts were disabled.
        $updateduser1 = $DB->get_record('user', ['id' => $student1->id]);
        $updateduser2 = $DB->get_record('user', ['id' => $student2->id]);

        // Capture the output.
        $output = ob_get_clean();

        // Assert the expected output.
        $this->assertStringContainsString("Disabled user: {$student1->username}", $output);
        $this->assertStringNotContainsString("Disabled user: {$student2->username}", $output);

        // Assert that only the user in the old course was disabled.
        $this->assertEquals(1, $updateduser1->suspended);
        $this->assertEquals(0, $updateduser2->suspended);
    }

    /**
     * Test account disabling based on creation date
     *
     * Verifies that student accounts are disabled correctly based on
     * the account creation date criteria.
     */
    public function test_disable_after_creation(): void {
        global $DB;

        // Create two student users - one old, one new.
        $oldstudent = $this->getDataGenerator()->create_user();
        $newstudent = $this->getDataGenerator()->create_user();
        // Set creation date for old student to 46 days ago.
        $DB->set_field('user', 'timecreated', time() - (46 * DAYSECS), ['id' => $oldstudent->id]);
        // New student's creation date remains as current time.

        // Assign student role to both users.
        $systemcontext = \context_system::instance();
        role_assign($this->studentrole, $oldstudent->id, $systemcontext->id);
        role_assign($this->studentrole, $newstudent->id, $systemcontext->id);

        // Verify both accounts are active before running the task.
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $oldstudent->id]));
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $newstudent->id]));

        // Start output buffering.
        ob_start();

        // Run cleanup.
        util::process_student_accounts();

        // Check if student accounts were disabled.
        $updateolduser = $DB->get_record('user', ['id' => $oldstudent->id]);
        $updatenewuser = $DB->get_record('user', ['id' => $newstudent->id]);

        // Capture the output.
        $output = ob_get_clean();

        // Assert the expected output.
        $this->assertStringContainsString("Disabled user: {$oldstudent->username}", $output);
        $this->assertStringNotContainsString("Disabled user: {$newstudent->username}", $output);

        // Assert that only the old account was disabled.
        $this->assertEquals(1, $updateolduser->suspended);
        $this->assertEquals(0, $updatenewuser->suspended);
    }

    /**
     * Test account deletion
     *
     * Verifies that student accounts are deleted correctly based on
     * the defined criteria for account deletion.
     */
    public function test_delete_after_months(): void {
        global $DB;

        // Create test data - one student for deletion, one to keep.
        $oldstudent = $this->getDataGenerator()->create_user();
        $activestudent = $this->getDataGenerator()->create_user();

        // Create courses - one old, one current.
        $oldcourse = $this->create_course_with_dates(-210 * DAYSECS); // 210 days ago
        $activecourse = $this->create_course_with_dates(30 * DAYSECS); // 30 days in future

        // Enrol students in their respective courses.
        $this->getDataGenerator()->enrol_user($oldstudent->id, $oldcourse->id, $this->studentrole);
        $this->getDataGenerator()->enrol_user($activestudent->id, $activecourse->id, $this->studentrole);

        // Verify both accounts exist before running the task.
        $this->assertTrue($DB->record_exists('user', ['id' => $oldstudent->id, 'deleted' => 0]));
        $this->assertTrue($DB->record_exists('user', ['id' => $activestudent->id, 'deleted' => 0]));

        // Start output buffering.
        ob_start();

        // Run cleanup.
        util::process_student_accounts();

        // Check if student accounts were affected.
        $olduserexists = $DB->record_exists('user', ['id' => $oldstudent->id, 'deleted' => 0]);
        $activeuserexists = $DB->record_exists('user', ['id' => $activestudent->id, 'deleted' => 0]);

        // Capture the output.
        $output = ob_get_clean();

        // Assert the expected output.
        $this->assertStringContainsString("Deleted user: {$oldstudent->username}", $output);
        $this->assertStringNotContainsString("Deleted user: {$activestudent->username}", $output);

        // Assert that only the old account was deleted.
        $this->assertFalse($olduserexists);
        $this->assertTrue($activeuserexists);
    }

    /**
     * Test that users with excluded roles are not affected
     *
     * Verifies that users with excluded roles are protected from
     * account disabling and deletion processes.
     */
    public function test_excluded_roles_protection(): void {
        global $DB;

        // Create test data - one teacher (protected) and one student (not protected).
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();

        // Set old creation dates for both users.
        $oldtime = time() - (46 * DAYSECS);
        $DB->set_field('user', 'timecreated', $oldtime, ['id' => $teacher->id]);
        $DB->set_field('user', 'timecreated', $oldtime, ['id' => $student->id]);

        // Create course that ended 22 days ago (past the disable threshold).
        $course = $this->create_course_with_dates(-22 * DAYSECS);

        // Assign roles.
        $systemcontext = \context_system::instance();
        role_assign($this->teacherrole, $teacher->id, $systemcontext->id);
        role_assign($this->studentrole, $student->id, $systemcontext->id);

        // Enrol both users in the old course.
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, $this->studentrole);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $this->studentrole);

        // Verify both accounts are active before running the task.
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $teacher->id]));
        $this->assertEquals(0, $DB->get_field('user', 'suspended', ['id' => $student->id]));

        // Start output buffering.
        ob_start();

        // Run cleanup.
        util::process_student_accounts();

        // Capture the output.
        $output = ob_get_clean();

        // Check if accounts were affected.
        $updatedteacher = $DB->get_record('user', ['id' => $teacher->id]);
        $updatedstudent = $DB->get_record('user', ['id' => $student->id]);

        // Assert the expected output.
        $this->assertStringNotContainsString("Disabled user: {$teacher->username}", $output);
        $this->assertStringContainsString("Disabled user: {$student->username}", $output);

        // Assert that only the student account was affected.
        $this->assertEquals(0, $updatedteacher->suspended);
        $this->assertEquals(0, $updatedteacher->deleted);
        $this->assertEquals(1, $updatedstudent->suspended);
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
        $this->getDataGenerator()->enrol_user($student->id, $oldcourse->id, $this->studentrole);
        $this->getDataGenerator()->enrol_user($student->id, $newcourse->id, $this->studentrole);

        // Run cleanup.
        util::process_student_accounts();

        // Check that student account was not affected (due to active enrollment).
        $updateduser = $DB->get_record('user', ['id' => $student->id]);
        $this->assertEquals(0, $updateduser->suspended);
        $this->assertEquals(0, $updateduser->deleted);
    }

    /**
     * Test protected account not disabled
     *
     * Verifies that users with the "protected" capability are not disabled
     * during the cleanup process.
     */
    public function test_protected_account_not_disabled(): void {
        global $DB;

        // Create a teacher user with an old creation date.
        $teacher = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'timecreated', time() - (46 * DAYSECS), ['id' => $teacher->id]); // Set creation date to 46 days ago.

        // Assign teacher role with protected capability.
        $systemcontext = \context_system::instance();
        role_assign($this->teacherrole, $teacher->id, $systemcontext->id);

        // Run cleanup.
        util::process_student_accounts();

        // Check if teacher account was disabled.
        $updateduser = $DB->get_record('user', ['id' => $teacher->id]);

        // Assert that the teacher account is not disabled.
        $this->assertEquals(0, $updateduser->suspended);
    }
}
