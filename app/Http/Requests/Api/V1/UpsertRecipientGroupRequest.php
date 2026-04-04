<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertRecipientGroupRequest extends FormRequest
{
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
        $recipientGroupId = $this->route('recipient_group')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('recipient_groups', 'name')->ignore($recipientGroupId),
            ],
        ];
    }
}
