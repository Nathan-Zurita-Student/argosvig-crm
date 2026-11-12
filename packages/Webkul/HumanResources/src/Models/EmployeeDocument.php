<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\HumanResources\Contracts\EmployeeDocument as EmployeeDocumentContract;
use Webkul\User\Models\UserProxy;

class EmployeeDocument extends Model implements EmployeeDocumentContract
{
    protected $table = 'hr_employee_documents';

    protected $fillable = [
        'employee_id',
        'type',
        'name',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'expires_at',
        'status',
        'uploaded_by',
    ];

    protected $casts = ['expires_at' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProxy::modelClass());
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'uploaded_by');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }
}
