<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\HumanResources\Contracts\Department as DepartmentContract;

class Department extends Model implements DepartmentContract
{
    protected $table = 'hr_departments';

    protected $fillable = ['name', 'code', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function employees(): HasMany
    {
        return $this->hasMany(EmployeeProxy::modelClass());
    }
}
