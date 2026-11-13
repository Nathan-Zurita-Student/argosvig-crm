<?php

namespace Webkul\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Rule;
use Webkul\Lead\Models\Stage;

class PipelineForm extends FormRequest
{
    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct(ValidationFactory $validationFactory)
    {
        $this->validatorExtensions($validationFactory);
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $requirements = [
            'stages.*.required_fields.*' => ['nullable', Rule::in(Stage::REQUIRED_FIELD_OPTIONS)],
            'stages.*.required_fields' => ['sometimes', 'array', 'max:'.(count(Stage::REQUIRED_FIELD_OPTIONS) + 1), function ($attribute, $value, $fail) {
                $stage = str_replace('.required_fields', '', $attribute);

                if (is_array($value) && in_array('lost_reason', $value, true) && $this->input($stage.'.code') !== 'lost') {
                    $fail(trans('admin::pipeline_requirements.lost_only'));
                }
            }],
        ];

        if (request('id')) {
            return [
                'name' => 'required|unique:lead_pipelines,name,'.request('id'),
                'stages.*.name' => 'unique_key',
                'stages.*.code' => 'unique_key',
            ] + $requirements;
        }

        return [
            'name' => 'required|unique:lead_pipelines,name',
            'rotten_days' => 'required',
            'stages.*.name' => 'unique_key',
            'stages.*.code' => 'unique_key',
        ] + $requirements;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'stages.*.name.unique_key' => trans('admin::app.settings.pipelines.duplicate-name'),
        ];
    }

    /**
     * Place all your validator extensions here.
     *
     * @return void
     */
    public function validatorExtensions(ValidationFactory $validationFactory)
    {
        $validationFactory->extend(
            'unique_key',
            function ($attribute, $value, $parameters) {
                $key = last(explode('.', $attribute));

                $stages = collect(request()->get('stages'))->filter(function ($stage) use ($key, $value) {
                    return $stage[$key] === $value;
                });

                if ($stages->count() > 1) {
                    return false;
                }

                return true;
            }
        );
    }
}
