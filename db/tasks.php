<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'tool_disable_delete_students\task\cleanup_students',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '0',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '0',
    ]
];
