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
 * Scheduled task for managing student accounts lifecycle.
 *
 * This task handles the automated process of disabling and/or deleting student accounts
 * based on configured criteria within the tool_disable_delete_students plugin.
 *
 * @package    tool_disable_delete_students
 * @copyright  2024 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Waleed ul hassan <waleed.hassan@catalyst-eu.net>
 */

namespace tool_disable_delete_students\task;

/**
 * Scheduled task class for cleaning up student accounts.
 *
 * @package    tool_disable_delete_students
 */
class cleanup_students extends \core\task\scheduled_task {

    /**
     * Returns the name of the scheduled task.
     *
     * @return string The name of the task
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('taskname', 'tool_disable_delete_students');
    }

    /**
     * Executes the scheduled task to process student accounts.
     *
     * This method triggers the account processing utility which handles
     * the disabling and/or deletion of student accounts based on configured rules.
     *
     * @return void
     */
    public function execute() {
        \tool_disable_delete_students\util::process_student_accounts();
    }
}
