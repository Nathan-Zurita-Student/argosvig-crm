<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Contracts\Stage as StageContract;

class Stage extends Model implements StageContract
{
    public const REQUIRED_FIELD_OPTIONS = ['person_id', 'user_id', 'lead_value', 'expected_close_date', 'description', 'lost_reason'];

    public $timestamps = false;

    protected $table = 'lead_pipeline_stages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'name',
        'probability',
        'sort_order',
        'lead_pipeline_id',
        'required_fields',
    ];

    protected $casts = ['required_fields' => 'array'];

    public function missingFields(array $values): array
    {
        $errors = [];

        foreach ($this->required_fields ?? [] as $field) {
            if (blank($values[$field] ?? null)) {
                $errors[$field] = trans('admin::pipeline_requirements.missing', [
                    'field' => trans('admin::pipeline_requirements.fields.'.$field),
                    'stage' => $this->name,
                ]);
            }
        }

        return $errors;
    }

    /**
     * Get the pipeline that owns the pipeline stage.
     */
    public function pipeline()
    {
        return $this->belongsTo(PipelineProxy::modelClass(), 'lead_pipeline_id');
    }

    /**
     * Get the leads.
     */
    public function leads()
    {
        return $this->hasMany(LeadProxy::modelClass(), 'lead_pipeline_stage_id');
    }
}
