<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webkul\HumanResources\Contracts\WorkSite as WorkSiteContract;

class WorkSite extends Model implements WorkSiteContract
{
    use SoftDeletes;

    protected $table = 'hr_work_sites';

    protected $fillable = ['service_contract_id', 'code', 'name', 'address', 'city', 'state', 'required_staff', 'status', 'notes'];

    protected $casts = ['required_staff' => 'integer'];

    public function serviceContract(): BelongsTo
    {
        return $this->belongsTo(ServiceContractProxy::modelClass());
    }
}
