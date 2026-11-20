<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Webkul\HumanResources\Models\EmployeeDocument;

uses(DatabaseTransactions::class);

/**
 * A second disk standing in for the private bucket.
 */
function fakeDocumentsCloud(): void
{
    config(['filesystems.disks.documents-cloud' => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/documents-cloud')]]);
    Storage::fake('documents-cloud');
    Storage::fake('local');
}

it('stores new documents on the configured disk and keeps blank forms free of company links', function () {
    fakeDocumentsCloud();
    config(['filesystems.documents' => 'documents-cloud']);
    $this->actingAs(getDefaultAdmin(), 'user');

    $this->post(route('admin.human_resources.document_center.store_shared'), [
        'type' => 'blank_form',
        'name' => 'Folha de ponto em branco',
        'file' => UploadedFile::fake()->create('folha.pdf', 10, 'application/pdf'),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $document = EmployeeDocument::query()->where('name', 'Folha de ponto em branco')->sole();

    expect($document->disk)->toBe('documents-cloud');
    Storage::disk('documents-cloud')->assertExists($document->path);
    Storage::disk('local')->assertMissing($document->path);

    $this->get(route('admin.human_resources.document_center.download', $document->id))->assertDownload('folha.pdf');

    // Client documents still need a company or a contract.
    $this->post(route('admin.human_resources.document_center.store_shared'), [
        'type' => 'solidarity_term',
        'name' => 'Termo sem vínculo',
        'file' => UploadedFile::fake()->create('termo.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('organizations');
});

it('moves stored documents to the documents disk only with --apply', function () {
    fakeDocumentsCloud();
    // Documents already in the database are not part of this test.
    EmployeeDocument::query()->update(['disk' => 'documents-cloud']);

    Storage::disk('local')->put('employee-documents/shared/procedimento.pdf', 'conteudo do procedimento');

    $document = EmployeeDocument::query()->create([
        'type' => 'procedure',
        'name' => 'Procedimento de ronda',
        'disk' => 'local',
        'path' => 'employee-documents/shared/procedimento.pdf',
        'original_name' => 'procedimento.pdf',
        'mime_type' => 'application/pdf',
        'size' => 24,
    ]);

    $this->artisan('hr:move-documents', ['disk' => 'documents-cloud'])->assertSuccessful();

    expect($document->refresh()->disk)->toBe('local');
    Storage::disk('documents-cloud')->assertMissing($document->path);

    $this->artisan('hr:move-documents', ['disk' => 'documents-cloud', '--apply' => true])->assertSuccessful();

    expect($document->refresh()->disk)->toBe('documents-cloud');
    expect(Storage::disk('documents-cloud')->get($document->path))->toBe('conteudo do procedimento');
    Storage::disk('local')->assertMissing($document->path);

    $this->artisan('hr:move-documents', ['disk' => 'unknown-disk'])->assertFailed();
});

it('reports documents whose file is missing instead of moving the record', function () {
    fakeDocumentsCloud();
    EmployeeDocument::query()->update(['disk' => 'documents-cloud']);

    $document = EmployeeDocument::query()->create([
        'type' => 'blank_form',
        'name' => 'Arquivo perdido',
        'disk' => 'local',
        'path' => 'employee-documents/shared/perdido.pdf',
        'original_name' => 'perdido.pdf',
        'mime_type' => 'application/pdf',
        'size' => 10,
    ]);

    $this->artisan('hr:move-documents', ['disk' => 'documents-cloud', '--apply' => true])->assertFailed();

    expect($document->refresh()->disk)->toBe('local');
});
