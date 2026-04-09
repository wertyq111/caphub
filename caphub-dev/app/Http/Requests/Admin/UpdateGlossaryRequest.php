<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGlossaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'term' => ['sometimes', 'required', 'string', 'max:191'],
            'source_lang' => ['sometimes', 'required', 'string', 'max:16'],
            'target_lang' => ['sometimes', 'required', 'string', 'max:16'],
            'standard_translation' => ['sometimes', 'required', 'string', 'max:191'],
            'domain' => ['sometimes', 'required', 'string', 'max:64'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:999999'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
