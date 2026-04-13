<?php

namespace App\Http\Requests\Admin;

use App\Enums\TranslationProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTranslationProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(TranslationProvider::values())],
        ];
    }
}
