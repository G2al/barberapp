<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            $this->merge(['message' => trim($this->input('message'))]);
        }
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:800'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Inserisci una domanda.',
            'message.string' => 'La domanda deve essere un testo.',
            'message.max' => 'La domanda non puo superare 800 caratteri.',
        ];
    }
}
