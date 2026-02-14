<?php

namespace App\Application\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BanPlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'player_id' => ['required', 'uuid', 'exists:players,id'],
            'reason' => ['required', 'string', 'max:500'],
            'ban_ip' => ['nullable', 'boolean'],
        ];
    }
}
