<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClosedSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'staff_id' => 'required|exists:staff,id',
            'date' => 'required|date|date_format:Y-m-d',
            'time' => 'nullable|date_format:H:i',
            'reason' => 'required|string|max:255',
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'staff_id.required' => 'Lo staff è obbligatorio',
            'staff_id.exists' => 'Lo staff selezionato non esiste',
            'date.required' => 'La data è obbligatoria',
            'date.date' => 'La data deve essere una data valida',
            'date.date_format' => 'La data deve essere nel formato YYYY-MM-DD',
            'time.date_format' => 'L\'orario deve essere nel formato HH:MM',
            'reason.required' => 'La ragione è obbligatoria',
            'reason.max' => 'La ragione non deve superare 255 caratteri',
        ];
    }
}
