<?php

namespace App\Http\Requests;

use App\Enums\BusinessEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordBusinessEventRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(BusinessEventType::class)],
            // Identifiant anonyme généré par l'application ou la page web.
            'device_id' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
        ];
    }
}
