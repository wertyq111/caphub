<?php

namespace App\Http\Requests\Demo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSyncTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'input_type' => ['required', 'string', 'in:plain_text,article_payload'],
            'document_type' => ['nullable', 'string'],
            'source_lang' => ['required', 'string'],
            'target_lang' => ['required', 'string'],
            'content' => ['required', 'array'],
            'content.text' => ['nullable', 'string'],
            'content.title' => ['nullable', 'string'],
            'content.summary' => ['nullable', 'string'],
            'content.body' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $inputType = (string) $this->input('input_type');
            $content = (array) $this->input('content', []);

            if ($inputType === 'plain_text') {
                if (($content['text'] ?? null) === null || trim((string) $content['text']) === '') {
                    $validator->errors()->add('content.text', 'The content.text field is required when input_type is plain_text.');
                }

                foreach (['title', 'summary', 'body'] as $field) {
                    if (($content[$field] ?? null) !== null && trim((string) $content[$field]) !== '') {
                        $validator->errors()->add("content.$field", "The content.$field field is not allowed when input_type is plain_text.");
                    }
                }
            }

            if ($inputType === 'article_payload') {
                if (($content['text'] ?? null) !== null && trim((string) $content['text']) !== '') {
                    $validator->errors()->add('content.text', 'The content.text field is not allowed when input_type is article_payload.');
                }

                $articleFields = array_filter([
                    'title' => trim((string) ($content['title'] ?? '')),
                    'summary' => trim((string) ($content['summary'] ?? '')),
                    'body' => trim((string) ($content['body'] ?? '')),
                ]);

                if ($articleFields === []) {
                    $validator->errors()->add('content', 'At least one of content.title, content.summary, or content.body is required when input_type is article_payload.');
                }
            }
        });
    }
}
