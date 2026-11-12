<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webkul\HumanResources\Contracts\Employee as EmployeeContract;

class Employee extends Model implements EmployeeContract
{
    use SoftDeletes;

    protected $table = 'hr_employees';

    protected $fillable = [
        'department_id',
        'employee_number',
        'full_name',
        'cpf',
        'birth_date',
        'rg',
        'email',
        'phone',
        'job_title',
        'hire_date',
        'status',
        'alterdata_employee_id',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hire_date' => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentProxy::modelClass());
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocumentProxy::modelClass());
    }
}
