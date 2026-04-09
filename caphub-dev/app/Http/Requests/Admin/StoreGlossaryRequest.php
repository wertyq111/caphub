<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGlossaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'term' => ['required', 'string', 'max:191'],
            'source_lang' => ['required', 'string', 'max:16'],
            'target_lang' => ['required', 'string', 'max:16'],
            'standard_translation' => ['required', 'string', 'max:191'],
            'domain' => ['sometimes', 'string', 'max:64'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:999999'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
