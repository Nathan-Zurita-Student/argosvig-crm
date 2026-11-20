<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\HumanResources\Contracts\ChecklistItem as ChecklistItemContract;
use Webkul\User\Models\UserProxy;

class ChecklistItem extends Model implements ChecklistItemContract
{
    protected $table = 'hr_checklist_items';

    protected $fillable = ['checklist_id', 'title', 'position', 'completed_at', 'completed_by'];

    protected $casts = ['position' => 'integer', 'completed_at' => 'datetime'];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ChecklistProxy::modelClass(), 'checklist_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass(), 'completed_by');
    }
}
