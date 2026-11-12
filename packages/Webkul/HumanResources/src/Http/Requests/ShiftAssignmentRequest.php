<?php

namespace Webkul\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['work_site_id' => ['required', 'exists:hr_work_sites,id'], 'employee_id' => ['required', 'exists:hr_employees,id'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'shift_type' => ['required', Rule::in(['day', 'night', 'flexible'])], 'status' => ['required', Rule::in(['planned', 'confirmed', 'completed', 'absence'])], 'notes' => ['nullable', 'string', 'max:5000']];
    }
}
