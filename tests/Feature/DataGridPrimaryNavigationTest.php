<?php

use Illuminate\Support\Facades\File;
use Webkul\DataGrid\DataGrid;

class PrimaryNavigationTestDataGrid extends DataGrid
{
    public function prepareQueryBuilder() {}

    public function prepareColumns() {}

    public function renderPrimaryLink(mixed $label, string $url): string
    {
        return $this->primaryLink($label, $url);
    }
}

it('renders accessible same-tab links and keeps exports free of html', function () {
    $grid = new PrimaryNavigationTestDataGrid;

    request()->merge(['export' => false]);

    $link = $grid->renderPrimaryLink('Registro <principal>', 'https://example.test/records/1?mode=view');

    expect($link)
        ->toContain('<a ')
        ->toContain('href="https://example.test/records/1?mode=view"')
        ->toContain('Registro &lt;principal&gt;')
        ->not->toContain('target=');

    request()->merge(['export' => true]);

    expect($grid->renderPrimaryLink('Registro principal', 'https://example.test/records/1'))
        ->toBe('Registro principal');
});

it('keeps the primary-link convention in every crm datagrid', function () {
    $directories = [
        base_path('packages/Webkul/Admin/src/DataGrids'),
        base_path('packages/Webkul/HumanResources/src/DataGrids'),
        base_path('packages/Webkul/WebForm/src/DataGrids'),
    ];

    $dataGrids = collect($directories)
        ->flatMap(fn ($directory) => File::allFiles($directory))
        ->filter(fn ($file) => str_ends_with(strtolower($file->getFilename()), 'grid.php'));

    expect($dataGrids)->not->toBeEmpty();

    /**
     * Users are edited in a modal: their grid sends the avatar data and the page
     * template turns the name into the link that opens it.
     */
    $linkedInTemplate = ['UserDataGrid.php'];

    foreach ($dataGrids as $dataGrid) {
        $contents = $dataGrid->getContents();

        if (! in_array($dataGrid->getFilename(), $linkedInTemplate, true)) {
            expect($contents)->toContain('primaryLink');
        }

        expect($contents)
            ->not->toContain('icon-eye')
            ->not->toContain('_blank');
    }
});

it('opens the user modal from the name in the users grid', function () {
    $template = File::get(base_path('packages/Webkul/Admin/src/Resources/views/settings/users/index.blade.php'));

    expect($template)->toMatch('/@click="editModal\(record\.actions\.find\(action => action\.index === \'edit\'\)\?\.url\)"\s*>\s*@\{\{ record\.name\.name \}\}\s*<\/a>/');

    // A page link to the edit route would answer with JSON, since that route feeds the modal.
    expect(File::get(base_path('packages/Webkul/Admin/src/DataGrids/Settings/UserDataGrid.php')))
        ->not->toContain('primaryLink');
});
