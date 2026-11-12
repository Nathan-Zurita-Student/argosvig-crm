<?php

use Illuminate\Support\Facades\Route;
use Webkul\HumanResources\Http\Controllers\DepartmentController;
use Webkul\HumanResources\Http\Controllers\EmployeeController;

Route::middleware(['web', 'user'])->prefix('admin/human-resources')->group(function () {
    Route::controller(EmployeeController::class)->prefix('employees')->group(function () {
        Route::get('', 'index')->name('admin.human_resources.employees.index');
        Route::get('create', 'create')->name('admin.human_resources.employees.create');
        Route::post('', 'store')->name('admin.human_resources.employees.store');
        Route::get('{id}/edit', 'edit')->name('admin.human_resources.employees.edit');
        Route::put('{id}', 'update')->name('admin.human_resources.employees.update');
        Route::delete('{id}', 'destroy')->name('admin.human_resources.employees.delete');
    });

    Route::controller(DepartmentController::class)->prefix('departments')->group(function () {
        Route::get('', 'index')->name('admin.human_resources.departments.index');
        Route::get('create', 'create')->name('admin.human_resources.departments.create');
        Route::post('', 'store')->name('admin.human_resources.departments.store');
        Route::get('{id}/edit', 'edit')->name('admin.human_resources.departments.edit');
        Route::put('{id}', 'update')->name('admin.human_resources.departments.update');
        Route::delete('{id}', 'destroy')->name('admin.human_resources.departments.delete');
    });
});
