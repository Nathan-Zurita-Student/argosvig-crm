<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webkul\HumanResources\Contracts\Incident as IncidentContract;
use Webkul\User\Models\UserProxy;

class Incident extends Model implements IncidentContract
{
    use SoftDeletes;

    protected $table = 'hr_incidents';

    protected $fillable = ['code', 'title', 'description', 'occurred_at', 'location', 'severity', 'status', 'employee_id', 'service_contract_id', 'assigned_to', 'reported_by', 'resolution', 'resolved_at'];

    protected $casts = ['occurred_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProxy::modelClass());
    }

    public function serviceContract(): BelongsTo
    {
        return $this->belongsTo(ServiceContractProxy::modelClass());
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'assigned_to');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'reported_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(IncidentImageProxy::modelClass());
    }
}
