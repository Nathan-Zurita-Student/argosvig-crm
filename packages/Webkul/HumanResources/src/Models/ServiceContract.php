<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\HumanResources\Contracts\ServiceContract as ServiceContractContract;
use Webkul\User\Models\UserProxy;

class ServiceContract extends Model implements ServiceContractContract
{
    use SoftDeletes;

    protected $table = 'hr_service_contracts';

    protected $fillable = ['organization_id', 'manager_id', 'contract_number', 'title', 'starts_at', 'ends_at', 'monthly_value', 'status', 'description'];

    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date', 'monthly_value' => 'decimal:2'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationProxy::modelClass());
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'manager_id');
    }
}
