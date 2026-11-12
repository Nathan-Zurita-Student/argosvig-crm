<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\HumanResources\Contracts\ShiftAssignment as ShiftAssignmentContract;

class ShiftAssignment extends Model implements ShiftAssignmentContract
{
    protected $table = 'hr_shift_assignments';

    protected $fillable = ['work_site_id', 'employee_id', 'starts_at', 'ends_at', 'shift_type', 'status', 'notes'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSiteProxy::modelClass());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProxy::modelClass());
    }
}
