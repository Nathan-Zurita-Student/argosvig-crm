<?php

use Illuminate\Support\Facades\Route;
use Webkul\HumanResources\Http\Controllers\CommercialFollowUpController;
use Webkul\HumanResources\Http\Controllers\DepartmentController;
use Webkul\HumanResources\Http\Controllers\DocumentCenterController;
use Webkul\HumanResources\Http\Controllers\EmployeeController;
use Webkul\HumanResources\Http\Controllers\EmployeeDocumentController;
use Webkul\HumanResources\Http\Controllers\IncidentController;
use Webkul\HumanResources\Http\Controllers\IncidentDraftController;
use Webkul\HumanResources\Http\Controllers\OperationsAssistantController;
use Webkul\HumanResources\Http\Controllers\QuoteVersionController;
use Webkul\HumanResources\Http\Controllers\ServiceContractController;
use Webkul\HumanResources\Http\Controllers\ShiftAssignmentController;
use Webkul\HumanResources\Http\Controllers\TimekeepingController;
use Webkul\HumanResources\Http\Controllers\VacationController;
use Webkul\HumanResources\Http\Controllers\WorkSiteController;

Route::middleware(['web', 'admin_locale', 'user'])->prefix(config('app.admin_path').'/operations')->name('admin.operations.')->group(function () {
    Route::get('', [OperationsAssistantController::class, 'index'])->name('index');
    Route::post('ask', [OperationsAssistantController::class, 'ask'])->middleware('throttle:8,1')->name('ask');
    Route::get('coverage/{site}', [OperationsAssistantController::class, 'coverage'])->whereNumber('site')->name('coverage');
    Route::get('incident-draft', [IncidentDraftController::class, 'create'])->name('incident_draft');
    Route::post('incident-draft', [IncidentDraftController::class, 'draft'])->middleware('throttle:4,1')->name('incident_draft.store');
});

Route::middleware(['web', 'user'])->prefix('admin/commercial-follow-up')->group(function () {
    Route::get('opportunities', [CommercialFollowUpController::class, 'index'])->defaults('tab', 'leads')->name('admin.leads.follow_up');
    Route::get('activities', [CommercialFollowUpController::class, 'index'])->defaults('tab', 'activities')->name('admin.activities.follow_up');
    Route::get('quotes', [CommercialFollowUpController::class, 'index'])->defaults('tab', 'quotes')->name('admin.quotes.follow_up');
    Route::get('quotes/{id}', [QuoteVersionController::class, 'index'])->whereNumber('id')->name('admin.quotes.follow_up.show');
    Route::post('quotes/{id}', [QuoteVersionController::class, 'store'])->whereNumber('id')->name('admin.quotes.follow_up.store');
});

Route::middleware(['web', 'user'])->prefix('admin/human-resources')->group(function () {
    Route::controller(DocumentCenterController::class)->prefix('documents')->name('admin.human_resources.document_center.')->group(function () {
        Route::get('', 'index')->name('index');
        Route::get('missing', 'missing')->name('missing');
        Route::get('create', 'create')->name('create');
        Route::post('', 'store')->name('store');
        Route::get('shared/create', 'createShared')->name('create_shared');
        Route::post('shared', 'storeShared')->name('store_shared');
        Route::get('{id}', 'show')->whereNumber('id')->name('show');
        Route::get('{id}/edit', 'edit')->whereNumber('id')->name('edit');
        Route::put('{id}', 'update')->whereNumber('id')->name('update');
        Route::get('{id}/download', 'download')->whereNumber('id')->name('download');
        Route::delete('{id}', 'destroy')->whereNumber('id')->name('delete');
    });

    Route::controller(TimekeepingController::class)->prefix('timekeeping')->name('admin.human_resources.timekeeping.')->group(function () {
        Route::get('', 'index')->name('index');
        Route::get('export', 'export')->name('export');
        Route::get('template', 'template')->name('template');
        Route::post('import', 'import')->name('import');
    });

    Route::controller(EmployeeController::class)->prefix('employees')->group(function () {
        Route::get('', 'index')->name('admin.human_resources.employees.index');
        Route::get('create', 'create')->name('admin.human_resources.employees.create');
        Route::post('', 'store')->name('admin.human_resources.employees.store');
        Route::get('{id}', 'show')->name('admin.human_resources.employees.show');
        Route::get('{id}/edit', 'edit')->name('admin.human_resources.employees.edit');
        Route::put('{id}', 'update')->name('admin.human_resources.employees.update');
        Route::delete('{id}', 'destroy')->name('admin.human_resources.employees.delete');
    });

    Route::controller(EmployeeDocumentController::class)->prefix('employees/{employeeId}/documents')->group(function () {
        Route::post('', 'store')->name('admin.human_resources.employee_documents.store');
        Route::get('{documentId}/download', 'download')->name('admin.human_resources.employee_documents.download');
        Route::delete('{documentId}', 'destroy')->name('admin.human_resources.employee_documents.delete');
    });

    Route::controller(VacationController::class)->prefix('vacations')->group(function () {
        Route::get('', 'index')->name('admin.human_resources.vacations.index');
        Route::get('create', 'create')->name('admin.human_resources.vacations.create');
        Route::post('', 'store')->name('admin.human_resources.vacations.store');
        Route::get('{id}/edit', 'edit')->name('admin.human_resources.vacations.edit');
        Route::put('{id}', 'update')->name('admin.human_resources.vacations.update');
        Route::delete('{id}', 'destroy')->name('admin.human_resources.vacations.delete');
    });

    Route::controller(DepartmentController::class)->prefix('departments')->group(function () {
        Route::get('', 'index')->name('admin.human_resources.departments.index');
        Route::get('create', 'create')->name('admin.human_resources.departments.create');
        Route::post('', 'store')->name('admin.human_resources.departments.store');
        Route::get('{id}/edit', 'edit')->name('admin.human_resources.departments.edit');
        Route::put('{id}', 'update')->name('admin.human_resources.departments.update');
        Route::delete('{id}', 'destroy')->name('admin.human_resources.departments.delete');
    });

    Route::controller(ServiceContractController::class)->prefix('contracts')->group(function () {
        Route::get('', 'index')->name('admin.human_resources.contracts.index');
        Route::get('create', 'create')->name('admin.human_resources.contracts.create');
        Route::post('', 'store')->name('admin.human_resources.contracts.store');
        Route::get('{id}/edit', 'edit')->name('admin.human_resources.contracts.edit');
        Route::put('{id}', 'update')->name('admin.human_resources.contracts.update');
        Route::delete('{id}', 'destroy')->name('admin.human_resources.contracts.delete');
    });

    Route::controller(IncidentController::class)->prefix('incidents')->group(function () {
        Route::get('', 'index')->name('admin.human_resources.incidents.index');
        Route::get('create', 'create')->name('admin.human_resources.incidents.create');
        Route::post('', 'store')->name('admin.human_resources.incidents.store');
        Route::get('{id}', 'show')->name('admin.human_resources.incidents.show');
        Route::get('{id}/edit', 'edit')->name('admin.human_resources.incidents.edit');
        Route::put('{id}', 'update')->name('admin.human_resources.incidents.update');
        Route::delete('{id}', 'destroy')->name('admin.human_resources.incidents.delete');
    });

    Route::controller(WorkSiteController::class)->prefix('work-sites')->group(function () {
        Route::get('', 'index')->name('admin.human_resources.work_sites.index');
        Route::get('create', 'create')->name('admin.human_resources.work_sites.create');
        Route::post('', 'store')->name('admin.human_resources.work_sites.store');
        Route::get('{id}/edit', 'edit')->name('admin.human_resources.work_sites.edit');
        Route::put('{id}', 'update')->name('admin.human_resources.work_sites.update');
        Route::delete('{id}', 'destroy')->name('admin.human_resources.work_sites.delete');
    });

    Route::controller(ShiftAssignmentController::class)->prefix('shifts')->group(function () {
        Route::get('', 'index')->name('admin.human_resources.shifts.index');
        Route::get('create', 'create')->name('admin.human_resources.shifts.create');
        Route::post('', 'store')->name('admin.human_resources.shifts.store');
        Route::get('{id}/edit', 'edit')->name('admin.human_resources.shifts.edit');
        Route::put('{id}', 'update')->name('admin.human_resources.shifts.update');
        Route::delete('{id}', 'destroy')->name('admin.human_resources.shifts.delete');
    });
});
