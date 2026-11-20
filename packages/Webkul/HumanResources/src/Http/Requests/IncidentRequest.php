<?php

namespace Webkul\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IncidentRequest extends FormRequest
{
    public const IMAGE_MAX_KB = 20 * 1024;

    public static function imageBatchLimit(): int
    {
        return max(1, (int) ini_get('max_file_uploads'));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'occurred_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['required', Rule::in(['open', 'investigating', 'resolved', 'closed'])],
            'employee_id' => ['nullable', 'integer', 'exists:hr_employees,id'],
            'service_contract_id' => ['nullable', 'integer', 'exists:hr_service_contracts,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'resolution' => ['nullable', 'string', 'max:10000', Rule::requiredIf(in_array($this->input('status'), ['resolved', 'closed'], true))],
            'image_count' => ['sometimes', 'integer', 'min:0', 'max:'.self::imageBatchLimit()],
            'images' => ['sometimes', 'array', 'max:'.self::imageBatchLimit()],
            'images.*' => ['bail', 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.self::IMAGE_MAX_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'image_count.max' => trans('human_resources::app.incidents.images.batch_error', ['count' => self::imageBatchLimit()]),
            'images.max' => trans('human_resources::app.incidents.images.batch_error', ['count' => self::imageBatchLimit()]),
            'images.*.image' => trans('human_resources::app.incidents.images.format_error'),
            'images.*.mimes' => trans('human_resources::app.incidents.images.format_error'),
            'images.*.max' => trans('human_resources::app.incidents.images.size_error', ['size' => self::IMAGE_MAX_KB / 1024]),
            'images.*.uploaded' => trans('human_resources::app.incidents.images.upload_error'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isEmpty() && $this->has('image_count')
                && $this->integer('image_count') !== count($this->file('images', []))) {
                $validator->errors()->add('images', trans('human_resources::app.incidents.images.incomplete_upload'));
            }
        }];
    }
}
