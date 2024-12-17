<?php
namespace tool_disable_delete_students\task;

defined('MOODLE_INTERNAL') || die();

class cleanup_students extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('taskname', 'tool_disable_delete_students');
    }

    public function execute() {
        \tool_disable_delete_students\util::process_student_accounts();
    }
}
