<?php

return [
    'title' => 'Fields required when entering this stage',
    'missing' => 'Fill in :field before moving the opportunity to :stage.',
    'invalid_stage' => 'Select a stage from the opportunity pipeline.',
    'lost_only' => 'The loss reason can only be required in the lost stage.',
    'fields' => [
        'person_id' => 'Contact',
        'user_id' => 'Owner',
        'lead_value' => 'Opportunity value',
        'expected_close_date' => 'Expected close date',
        'description' => 'Description',
        'lost_reason' => 'Loss reason',
    ],
];
