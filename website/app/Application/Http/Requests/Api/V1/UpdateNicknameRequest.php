<?php

namespace App\Application\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNicknameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $player = $this->attributes->get('player');

        return [
            'nickname' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('players', 'nickname')->ignore($player?->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.regex' => 'Nickname may only contain letters, numbers, and underscores.',
            'nickname.min' => 'Nickname must be at least 3 characters.',
            'nickname.max' => 'Nickname must not exceed 20 characters.',
        ];
    }
}
