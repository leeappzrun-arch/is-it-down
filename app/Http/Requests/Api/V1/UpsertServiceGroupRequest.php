<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\ServiceValidation;
use Illuminate\Foundation\Http\FormRequest;

class UpsertServiceGroupRequest extends FormRequest
{
    use ServiceValidation;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'groupName' => $this->input('name', $this->input('groupName', '')),
            'groupSelectedRecipientGroupIds' => $this->input('recipient_group_ids', $this->input('groupSelectedRecipientGroupIds', [])),
            'groupSelectedRecipientIds' => $this->input('recipient_ids', $this->input('groupSelectedRecipientIds', [])),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->serviceGroupValidationRules($this->route('service_group')?->id);
    }
}
