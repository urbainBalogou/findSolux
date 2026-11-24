<?php

namespace App\Http\Requests\Business;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Nom affiché ou nouveau mot de passe d'un membre de l'équipe (oubli, départ).
 */
class UpdateTeamMemberRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:60'],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'password' => 'mot de passe',
        ];
    }
}
