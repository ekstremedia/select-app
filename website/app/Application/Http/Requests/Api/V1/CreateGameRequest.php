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
            'settings.time_between_rounds' => ['nullable', 'integer', 'min:3', 'max:120'],
            'settings.excluded_letters' => ['nullable', 'string', 'max:26'],
            'settings.chat_enabled' => ['nullable', 'boolean'],
            'settings.max_edits' => ['nullable', 'integer', 'min:0', 'max:20'],
            'settings.max_vote_changes' => ['nullable', 'integer', 'min:0', 'max:20'],
            'settings.allow_ready_check' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:4', 'max:50'],
            'add_bots' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $s = $this->input('settings', []);
                if (isset($s['min_players'], $s['max_players']) && $s['min_players'] > $s['max_players']) {
                    $validator->errors()->add('settings.min_players', 'Min players must be ≤ max players.');
                }
                if (isset($s['acronym_length_min'], $s['acronym_length_max']) && $s['acronym_length_min'] > $s['acronym_length_max']) {
                    $validator->errors()->add('settings.acronym_length_min', 'Min length must be ≤ max length.');
                }
            },
        ];
    }
}
