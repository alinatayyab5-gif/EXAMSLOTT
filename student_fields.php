<?php
// Single definition of all student fields: [label, input type]. Used by the form and the validation.
// 'email' lives in the users table; every other key is a column in students.
$STUDENT_GROUPS = [
    'Personal information' => [
        'full_name' => ['Full name', 'text'],
        'email'     => ['Email', 'email'],
        'phone'     => ['Phone', 'text'],
        'cnic'      => ['CNIC / B-Form number', 'text'],
        'dob'       => ['Date of birth', 'date'],
        'gender'    => ['Gender', 'select'],
        'address'   => ['Address', 'text'],
    ],
    'Parent / guardian' => [
        'father_name'       => ['Father / guardian name', 'text'],
        'parent_cnic'       => ['Parent CNIC', 'text'],
        'occupation'        => ['Occupation', 'text'],
        'parent_contact'    => ['Contact number', 'text'],
        'emergency_contact' => ['Emergency contact', 'text'],
    ],
    'Academic information' => [
        'reg_no'             => ['Registration number', 'text'],
        'program'            => ['Program / degree', 'text'],
        'semester'           => ['Semester', 'number'],
        'session_batch'      => ['Session / batch', 'text'],
        'prev_qualification' => ['Previous qualification', 'text'],
        'prev_institute'     => ['Previous institute', 'text'],
        'cgpa'               => ['Marks / CGPA', 'text'],
    ],
];
$STUDENT_GENDERS = ['Male', 'Female', 'Other'];
