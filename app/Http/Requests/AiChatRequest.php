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

        if (is_array($this->input('history'))) {
            $this->merge([
                'history' => array_map(static function (mixed $item): mixed {
                    if (is_array($item) && is_string($item['content'] ?? null)) {
                        $item['content'] = trim($item['content']);
                    }

                    return $item;
                }, $this->input('history')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:800'],
            'history' => ['sometimes', 'array', 'max:8'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:800'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Inserisci una domanda.',
            'message.string' => 'La domanda deve essere un testo.',
            'message.max' => 'La domanda non puo superare 800 caratteri.',
            'history.array' => 'La cronologia della chat non e valida.',
            'history.max' => 'Puoi inviare al massimo gli ultimi 8 messaggi.',
            'history.*.role.in' => 'Il ruolo di un messaggio della cronologia non e valido.',
            'history.*.content.required' => 'Il testo di un messaggio della cronologia e obbligatorio.',
            'history.*.content.max' => 'Ogni messaggio della cronologia non puo superare 800 caratteri.',
        ];
    }
}
