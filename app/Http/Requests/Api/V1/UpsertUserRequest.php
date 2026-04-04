<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpsertUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                ...$this->profileRules(),
                'password' => ['required', 'string', Password::default(), 'confirmed'],
                'role' => ['required', Rule::in(User::roles())],
            ];
        }

        return [
            ...$this->profileRules($this->route('user')?->id),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'role' => ['required', Rule::in(User::roles())],
        ];
    }
}
