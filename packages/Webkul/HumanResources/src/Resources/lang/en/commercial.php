<?php

return [
    'title' => 'Sales follow-up',
    'tabs' => ['leads' => 'Opportunities without a next action', 'activities' => 'Overdue activities', 'quotes' => 'Quotes awaiting a recorded response'],
    'record' => 'Record',
    'owner' => 'Owner',
    'details' => ['leads' => 'Stage', 'activities' => 'Due at', 'quotes' => 'Send recorded at'],
    'empty' => 'No records for this filter.',
    'quote' => [
        'title' => 'Quote sends and responses',
        'description' => 'After sending the quote, record the send to keep a copy of the quote and follow up on the response.',
        'edit' => 'Edit quote',
        'sent' => 'Record send for current version',
        'accepted' => 'Record acceptance',
        'declined' => 'Record decline',
        'statuses' => ['sent' => 'Awaiting response', 'accepted' => 'Accepted', 'declined' => 'Declined', 'superseded' => 'Superseded'],
        'version' => 'Version :number',
        'changed' => 'The follow-up changed. Check the current version before trying again.',
        'no_versions' => 'No sends have been recorded.',
        'open_contract' => 'Open linked contract',
        'create_contract' => 'Create draft contract',
        'contract_source' => 'Draft from quote :subject (version :number)',
        'contract_exists' => 'A contract already links to this version.',
        'organization_mismatch' => 'Select the company linked to the accepted version.',
    ],
];
