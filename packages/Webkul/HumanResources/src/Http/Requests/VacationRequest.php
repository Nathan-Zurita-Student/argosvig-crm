<?php

namespace Webkul\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Webkul\HumanResources\Models\VacationProxy;

class VacationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'type' => ['required', Rule::in(['regular', 'collective', 'split'])],
            'status' => ['required', Rule::in(['scheduled', 'in_progress', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Two vacation periods of the same employee cannot share a day, unless one is cancelled.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('status') === 'cancelled') {
                return;
            }

            $overlaps = VacationProxy::query()
                ->where('employee_id', (int) $this->input('employee_id'))
                ->when($this->route('id'), fn ($query, $id) => $query->whereKeyNot($id))
                ->where('status', '!=', 'cancelled')
                ->whereDate('starts_at', '<=', $this->input('ends_at'))
                ->whereDate('ends_at', '>=', $this->input('starts_at'))
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('starts_at', trans('human_resources::app.vacations.messages.overlap'));
            }
        }];
    }
}
