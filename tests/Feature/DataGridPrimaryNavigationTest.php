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

    foreach ($dataGrids as $dataGrid) {
        $contents = $dataGrid->getContents();

        expect($contents)
            ->toContain('primaryLink')
            ->not->toContain('icon-eye')
            ->not->toContain('_blank');
    }
});
