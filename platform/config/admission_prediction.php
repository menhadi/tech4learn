<?php

return [
    'schema_version' => 2,

    'pdf' => [
        'pdftotext_binary' => env('ADMISSION_PDFTOTEXT_BINARY', 'pdftotext'),
        'python_binary' => env('ADMISSION_PYTHON_BINARY', 'python'),
        'timeout_seconds' => 180,
        'allotment_timeout_seconds' => 1800,
        'table_timeout_seconds' => 600,
    ],

    'probe' => [
        'connect_timeout_seconds' => 15,
        'timeout_seconds' => 90,
        'max_bytes' => 25 * 1024 * 1024,
    ],

    'josaa' => [
        'or_cr_url' => 'https://josaa.admissions.nic.in/Applicant/SeatAllotmentResult/currentorcr.aspx',
        'timeout_seconds' => 60,
    ],

    /*
    | These blueprints describe inputs and official authorities; they do not
    | contain cut-offs or prediction values. Historical facts must enter the
    | system as verified official resources and versioned datasets.
    */
    'exams' => [
        'jee-main-paper-1' => [
            'code' => 'jee-main-paper-1',
            'name' => 'JEE Main Paper 1 (B.E./B.Tech.)',
            'country_code' => 'IN',
            'authorities' => [
                'National Testing Agency',
                'Joint Seat Allocation Authority',
                'Central Seat Allocation Board',
            ],
            'official_domains' => [
                'nta.ac.in',
                'jeemain.nta.ac.in',
                'jeemain.nta.nic.in',
                'josaa.nic.in',
                'csab.nic.in',
                'josaa.admissions.nic.in',
            ],
            'document_delivery_domains' => ['cdnbbsr.s3waas.gov.in'],
            'score_schema' => [
                'max_marks' => 300,
                'inputs' => ['total', 'subject'],
                'subjects' => [
                    ['code' => 'physics', 'name' => 'Physics', 'max_marks' => 100],
                    ['code' => 'chemistry', 'name' => 'Chemistry', 'max_marks' => 100],
                    ['code' => 'mathematics', 'name' => 'Mathematics', 'max_marks' => 100],
                ],
            ],
            'rank_dimensions' => ['exam_year', 'session', 'shift', 'category'],
            'admission_dimensions' => [
                'exam_year', 'counselling_body', 'round', 'category', 'quota',
                'gender_pool', 'domicile_state', 'institute', 'program',
            ],
            'resource_kinds' => [
                'exam_statistics', 'marks_percentile_rank', 'opening_closing_rank',
                'seat_matrix', 'allotment_result', 'counselling_rules', 'correction_notice',
            ],
        ],

        'neet-ug' => [
            'code' => 'neet-ug',
            'name' => 'NEET (UG)',
            'country_code' => 'IN',
            'authorities' => [
                'National Testing Agency',
                'Medical Counselling Committee',
            ],
            'official_domains' => [
                'nta.ac.in',
                'exams.nta.ac.in',
                'neet.nta.nic.in',
                'mcc.nic.in',
            ],
            'document_delivery_domains' => ['cdnbbsr.s3waas.gov.in'],
            'score_schema' => [
                'max_marks' => 720,
                'inputs' => ['total', 'subject'],
                'subjects' => [
                    ['code' => 'physics', 'name' => 'Physics', 'max_marks' => 180],
                    ['code' => 'chemistry', 'name' => 'Chemistry', 'max_marks' => 180],
                    ['code' => 'botany', 'name' => 'Botany', 'max_marks' => 180],
                    ['code' => 'zoology', 'name' => 'Zoology', 'max_marks' => 180],
                ],
            ],
            'rank_dimensions' => ['exam_year', 'category'],
            'admission_dimensions' => [
                'exam_year', 'counselling_body', 'round', 'category', 'quota',
                'domicile_state', 'college', 'course', 'institution_type',
            ],
            'resource_kinds' => [
                'exam_statistics', 'marks_rank', 'seat_matrix', 'allotment_result',
                'opening_closing_rank', 'counselling_rules', 'correction_notice',
            ],
        ],
    ],
];
