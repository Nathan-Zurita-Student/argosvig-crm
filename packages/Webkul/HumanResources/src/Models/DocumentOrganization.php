<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Contact\Models\OrganizationProxy;

class DocumentOrganization extends Model
{
    protected $table = 'hr_document_organizations';

    public $timestamps = false;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OrganizationProxy::modelClass());
    }
}
