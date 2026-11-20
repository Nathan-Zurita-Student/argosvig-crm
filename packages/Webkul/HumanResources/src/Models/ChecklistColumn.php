<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\HumanResources\Contracts\ChecklistColumn as ChecklistColumnContract;

class ChecklistColumn extends Model implements ChecklistColumnContract
{
    /**
     * The columns a new board starts with. All of them can be renamed, reordered or
     * removed; a column that "completes" marks the cards entering it as completed.
     */
    public const DEFAULTS = [
        ['key' => 'todo', 'color' => 'gray', 'completes' => false],
        ['key' => 'doing', 'color' => 'blue', 'completes' => false],
        ['key' => 'done', 'color' => 'green', 'completes' => true],
    ];

    public const COLORS = ['gray', 'blue', 'yellow', 'orange', 'green', 'red', 'purple'];

    protected $table = 'hr_checklist_columns';

    protected $fillable = ['key', 'label', 'color', 'position', 'completes'];

    protected $casts = ['position' => 'integer', 'completes' => 'boolean'];

    public function checklists(): HasMany
    {
        return $this->hasMany(ChecklistProxy::modelClass(), 'column_id');
    }
}
