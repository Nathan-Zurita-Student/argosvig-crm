<?php

namespace Webkul\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Webkul\HumanResources\Models\ShiftAssignmentProxy;
use Webkul\HumanResources\Models\VacationProxy;

class ShiftAssignmentRequest extends FormRequest
{
    /**
     * Statuses in which the employee is expected to be at the work site.
     */
    private const WORKING_STATUSES = ['planned', 'confirmed', 'completed'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['work_site_id' => ['required', 'exists:hr_work_sites,id'], 'employee_id' => ['required', 'exists:hr_employees,id'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'shift_type' => ['required', Rule::in(['day', 'night', 'flexible'])], 'status' => ['required', Rule::in(['planned', 'confirmed', 'completed', 'absence'])], 'notes' => ['nullable', 'string', 'max:5000']];
    }

    /**
     * An employee cannot work two overlapping shifts nor work during a vacation.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! in_array($this->input('status'), self::WORKING_STATUSES, true)) {
                return;
            }

            $from = Carbon::parse($this->input('starts_at'));
            $to = Carbon::parse($this->input('ends_at'));
            $employee = (int) $this->input('employee_id');

            $overlapsShift = ShiftAssignmentProxy::query()
                ->where('employee_id', $employee)
                ->when($this->route('id'), fn ($query, $id) => $query->whereKeyNot($id))
                ->whereIn('status', self::WORKING_STATUSES)
                ->where('starts_at', '<', $to)
                ->where('ends_at', '>', $from)
                ->exists();

            if ($overlapsShift) {
                $validator->errors()->add('employee_id', trans('human_resources::app.shifts.messages.overlap'));

                return;
            }

            $onVacation = VacationProxy::query()
                ->where('employee_id', $employee)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where('starts_at', '<=', $to->copy()->subSecond()->toDateString())
                ->where('ends_at', '>=', $from->toDateString())
                ->exists();

            if ($onVacation) {
                $validator->errors()->add('employee_id', trans('human_resources::app.shifts.messages.on_vacation'));
            }
        }];
    }
}
