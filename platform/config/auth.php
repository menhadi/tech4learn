<?php

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        
        'student' => [
            'driver' => 'session',
            'provider' => 'students',
        ],

        // =======================================================
        // >> YEH SECTION HUMNE ADD KIYA HAI <<
        'student-api' => [
            'driver' => 'sanctum',
            'provider' => 'students',
        ],
        // =======================================================
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
        'students' => [
            'driver' => 'eloquent',
            'model' => App\Models\Student::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_resets',
            'expire' => 60,
            'throttle' => 60,
        ],
        'students' => [
            'provider' => 'students',
            'table' => 'password_resets',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];