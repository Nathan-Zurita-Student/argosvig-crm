<?php

namespace Webkul\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Webkul\HumanResources\Contracts\QuoteVersion as QuoteVersionContract;
use Webkul\Quote\Models\QuoteProxy;

class QuoteVersion extends Model implements QuoteVersionContract
{
    public $timestamps = false;

    protected $table = 'hr_quote_versions';

    protected $fillable = ['quote_id', 'number', 'status', 'snapshot', 'sent_at', 'sent_by', 'resolved_at', 'resolved_by'];

    protected $casts = ['snapshot' => 'array', 'sent_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(QuoteProxy::modelClass());
    }

    public function contract(): HasOne
    {
        return $this->hasOne(ServiceContractProxy::modelClass())->withTrashed();
    }
}
