<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class GenerateOrderReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin()
            && $this->user()?->tokenCan('reports:generate');
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2020', 'max:' . now()->year],
            'months' => ['nullable', 'array', 'min:1', 'max:12'],
            'months.*' => ['integer', 'min:1', 'max:12'],
        ];
    }
}
