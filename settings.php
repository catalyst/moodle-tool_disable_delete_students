<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_disable_delete_students', get_string('pluginname', 'tool_disable_delete_students'));
    $ADMIN->add('tools', $settings);

    // Days after course end to disable account
    $settings->add(new admin_setting_configtext(
        'tool_disable_delete_students/disable_after_course_end',
        get_string('disable_after_course_end', 'tool_disable_delete_students'),
        get_string('disable_after_course_end_desc', 'tool_disable_delete_students'),
        21,
        PARAM_INT
    ));

    // Days after creation to disable account
    $settings->add(new admin_setting_configtext(
        'tool_disable_delete_students/disable_after_creation',
        get_string('disable_after_creation', 'tool_disable_delete_students'),
        get_string('disable_after_creation_desc', 'tool_disable_delete_students'),
        45,
        PARAM_INT
    ));

    // Months after course end to delete account
    $settings->add(new admin_setting_configtext(
        'tool_disable_delete_students/delete_after_months',
        get_string('delete_after_months', 'tool_disable_delete_students'),
        get_string('delete_after_months_desc', 'tool_disable_delete_students'),
        6,
        PARAM_INT
    ));
}
