<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webkul\HumanResources\Contracts\Vacation as VacationContract;

class Vacation extends Model implements VacationContract
{
    use SoftDeletes;

    protected $table = 'hr_vacations';

    protected $fillable = ['employee_id', 'starts_at', 'ends_at', 'days', 'type', 'status', 'notes'];

    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date', 'days' => 'integer'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProxy::modelClass());
    }
}
