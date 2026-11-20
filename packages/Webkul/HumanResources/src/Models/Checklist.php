<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\HumanResources\Contracts\Checklist as ChecklistContract;
use Webkul\User\Models\UserProxy;

class Checklist extends Model implements ChecklistContract
{
    protected $table = 'hr_checklists';

    protected $fillable = [
        'column_id',
        'position',
        'title',
        'description',
        'employee_id',
        'work_site_id',
        'assigned_to',
        'due_date',
        'created_by',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'position' => 'integer',
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function column(): BelongsTo
    {
        return $this->belongsTo(ChecklistColumnProxy::modelClass(), 'column_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItemProxy::modelClass(), 'checklist_id')->orderBy('position')->orderBy('id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProxy::modelClass())->withTrashed();
    }

    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSiteProxy::modelClass())->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'completed_by');
    }
}
