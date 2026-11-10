<?php

/*
|--------------------------------------------------------------------------
| Role descriptions and permission summaries
|--------------------------------------------------------------------------
| Shown (read-only) on the Super Admin "Roles" page. These lines only describe
| the rules; the real enforcement is in the role middleware, the department
| scope and the policies on the API routes.
*/

return [
    'student' => [
        'description' => 'Own academic records only.',
        'permissions' => [
            'View and edit own limited profile',
            'View courses for own department, program, level and semester',
            'Register courses (no duplicates)',
            'View approved results only',
            'View academic history and CGPA',
            'Print registration summary and result statement',
        ],
    ],
    'lecturer' => [
        'description' => 'Assigned courses only.',
        'permissions' => [
            'View assigned courses and class lists',
            'Enter CA and exam scores',
            'Save drafts and submit results for review',
            'View submission status and feedback',
            'View course summaries',
        ],
    ],
    'dept_admin' => [
        'description' => 'Own department only.',
        'permissions' => [
            'Manage department students, lecturers and courses',
            'Assign lecturers to courses',
            'View department registrations',
            'Approve, reject (with feedback), return or reopen results',
            'View department reports',
        ],
    ],
    'super_admin' => [
        'description' => 'University-wide access.',
        'permissions' => [
            'Manage faculties, departments and programs',
            'Manage sessions and semesters (one current)',
            'Manage users, status and roles',
            'Manage courses and allocations',
            'Oversee registrations and results',
            'View reports and the activity log',
        ],
    ],
];
