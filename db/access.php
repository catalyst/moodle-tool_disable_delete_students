$capabilities = [
    'tool/disable_delete_students:protected' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'admin' => CAP_ALLOW,
        ],
    ],
];