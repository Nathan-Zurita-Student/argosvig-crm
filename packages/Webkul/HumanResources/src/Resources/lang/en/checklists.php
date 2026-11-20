<?php

return [
    'title' => 'Checklist board',
    'hint' => 'Drag the cards between the columns. Each card holds the items to check, and who checked them and when is kept on record.',
    'recent_done' => 'Completing columns show the last :days days. Use the search to find older checklists.',
    'show_all_done' => 'Show every completed card',
    'empty_column' => 'No cards in this column.',
    'no_items' => 'No items yet.',
    'new_item' => 'New checklist item',
    'add_item' => 'Add',
    'remove_item' => 'Remove item',
    'details' => 'Details',
    'save' => 'Save',
    'delete' => 'Delete card',
    'delete_confirm' => 'Delete this card and its items? This cannot be undone.',
    'completed_by' => 'Completed on :date by :name',
    'none' => 'None',

    'filters' => [
        'search' => 'Search',
        'search_placeholder' => 'Title, item, employee or work site',
        'all' => 'All',
        'apply' => 'Filter',
        'clear' => 'Clear',
    ],

    'fields' => [
        'title' => 'Title',
        'column' => 'Column',
        'description' => 'Description',
        'employee' => 'Employee',
        'work_site' => 'Work site',
        'assignee' => 'Assignee',
        'due_date' => 'Due date',
        'items' => 'Checklist items',
    ],

    'create' => [
        'title' => 'New card',
        'items_placeholder' => 'One item per line',
        'save' => 'Create card',
    ],

    'messages' => [
        'created' => 'Card created.',
        'updated' => 'Card updated.',
        'deleted' => 'Card deleted.',
        'moved' => 'Board updated.',
        'error' => 'Could not save. Please try again.',
    ],

    'defaults' => [
        'todo' => 'To do',
        'doing' => 'In progress',
        'done' => 'Done',
    ],

    'colors' => [
        'gray' => 'Gray',
        'blue' => 'Blue',
        'yellow' => 'Yellow',
        'orange' => 'Orange',
        'green' => 'Green',
        'red' => 'Red',
        'purple' => 'Purple',
    ],

    'settings' => [
        'group' => 'Operations',
        'group_info' => 'Configure the operations checklist board.',
        'title' => 'Checklist board',
        'info' => 'Add, rename, reorder and remove the board columns.',
        'open_board' => 'Open the board',
        'save' => 'Save columns',
        'hint' => 'Every column can be renamed, reordered and removed; drag by the icon to change the order. Turn on "Complete cards" in the column that ends the checklist: a card entering it is completed and kept in the history. When a column is removed, its cards move to the first column that does not complete cards.',
        'new_column' => 'New column',
        'name' => 'Name',
        'color' => 'Colour',
        'completes' => 'Complete cards',
        'completes_hint' => 'A card entering this column is completed.',
        'cards_count' => 'Cards in this column: :count',
        'remove' => 'Remove column',
        'last_column' => 'The board needs at least one column',
        'add_title' => 'Add column',
        'add_info' => 'Create a new step for the board.',
        'add' => 'Add column',
        'duplicate' => 'A column with this name already exists.',
        'remove_confirm' => 'Remove this column? The change applies once you save.',
        'remove_confirm_cards' => 'Remove this column? Its cards move to the first column that does not complete cards when you save.',
        'saved' => 'Board columns saved.',
        'unknown_column' => 'One of the columns no longer exists. Reload the page and try again.',
    ],
];
