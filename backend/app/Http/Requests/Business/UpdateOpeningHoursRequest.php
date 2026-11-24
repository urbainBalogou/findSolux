<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Remplace les horaires d'une fiche. Une liste vide signifie « horaires non
 * renseignés ».
 */
class UpdateOpeningHoursRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opening_hours' => ['present', 'array', 'max:21'],
            'opening_hours.*.day' => ['required', 'integer', 'between:1,7'],
            'opening_hours.*.opens_at' => ['required', 'date_format:H:i'],
            'opening_hours.*.closes_at' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $byDay = [];
                foreach ($this->input('opening_hours') as $index => $range) {
                    if ($range['closes_at'] <= $range['opens_at']) {
                        $validator->errors()->add(
                            "opening_hours.{$index}.closes_at",
                            "L'heure de fermeture doit être après l'heure d'ouverture.",
                        );

                        continue;
                    }

                    foreach ($byDay[$range['day']] ?? [] as [$opensAt, $closesAt]) {
                        if ($range['opens_at'] < $closesAt && $opensAt < $range['closes_at']) {
                            $validator->errors()->add(
                                "opening_hours.{$index}.opens_at",
                                'Deux plages horaires se chevauchent le même jour.',
                            );
                        }
                    }
                    $byDay[$range['day']][] = [$range['opens_at'], $range['closes_at']];
                }
            },
        ];
    }
}
