<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\HumanResources\Contracts\EmployeeDocument as EmployeeDocumentContract;
use Webkul\User\Models\UserProxy;

class EmployeeDocument extends Model implements EmployeeDocumentContract
{
    public const EMPLOYEE_TYPES = [
        'identity', 'contract', 'certificate', 'occupational_health', 'payslip',
        'work_card', 'address_proof', 'training', 'timesheet', 'other',
    ];

    public const SHARED_TYPES = ['solidarity_term', 'third_party_certificate', 'shared'];

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
        'competence',
        'notes',
        'status',
        'uploaded_by',
    ];

    protected $casts = ['expires_at' => 'date', 'competence' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProxy::modelClass());
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'uploaded_by');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(OrganizationProxy::modelClass(), 'hr_document_organizations', 'document_id', 'organization_id');
    }

    public function organizationLinks(): HasMany
    {
        return $this->hasMany(DocumentOrganization::class, 'document_id');
    }

    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(ServiceContractProxy::modelClass(), 'hr_document_contracts', 'document_id', 'contract_id');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at?->lt(today()) ?? false;
    }
}
