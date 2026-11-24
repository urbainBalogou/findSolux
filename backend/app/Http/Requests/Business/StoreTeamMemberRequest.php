<?php

namespace App\Http\Requests\Business;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Membre de l'équipe créé par le garagiste : nom affiché, identifiant de
 * connexion (unique dans toute l'application) et mot de passe qu'il lui transmet.
 */
class StoreTeamMemberRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'username' => 'identifiant',
            'password' => 'mot de passe',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'L’identifiant ne peut contenir que des lettres sans accent, des chiffres, des points et des tirets.',
            'username.unique' => 'Cet identifiant est déjà pris : choisissez-en un autre, par exemple en ajoutant le nom du garage.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => Str::lower(trim($this->input('username')))]);
        }
    }
}
