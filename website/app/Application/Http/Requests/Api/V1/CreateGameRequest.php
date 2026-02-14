<?php

namespace App\Application\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['nullable', 'array'],
            'settings.rounds' => ['nullable', 'integer', 'min:1', 'max:20'],
            'settings.answer_time' => ['nullable', 'integer', 'min:15', 'max:300'],
            'settings.vote_time' => ['nullable', 'integer', 'min:10', 'max:120'],
            'settings.min_players' => ['nullable', 'integer', 'min:2', 'max:16'],
            'settings.max_players' => ['nullable', 'integer', 'min:2', 'max:16'],
            'settings.acronym_length_min' => ['nullable', 'integer', 'min:1', 'max:6'],
            'settings.acronym_length_max' => ['nullable', 'integer', 'min:1', 'max:6'],
            'settings.time_between_rounds' => ['nullable', 'integer', 'min:3', 'max:30'],
            'settings.excluded_letters' => ['nullable', 'string', 'max:26'],
            'is_public' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:4', 'max:50'],
        ];
    }
}
