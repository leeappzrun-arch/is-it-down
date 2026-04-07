<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\ServiceValidation;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

class UpsertServiceTemplateRequest extends FormRequest
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
            'templateName' => $this->input('name', $this->input('templateName', '')),
            'serviceName' => $this->input('service_name', $this->input('serviceName', '')),
            'intervalSeconds' => $this->input('interval_seconds', $this->input('intervalSeconds', Service::INTERVAL_1_MINUTE)),
            'expectType' => $this->input('expect_type', $this->input('expectType', Service::EXPECT_NONE)),
            'expectValue' => $this->input('expect_value', $this->input('expectValue', '')),
            'selectedServiceGroupIds' => $this->input('service_group_ids', $this->input('selectedServiceGroupIds', [])),
            'selectedRecipientGroupIds' => $this->input('recipient_group_ids', $this->input('selectedRecipientGroupIds', [])),
            'selectedRecipientIds' => $this->input('recipient_ids', $this->input('selectedRecipientIds', [])),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->serviceTemplateValidationRules(
            (string) $this->input('expectType', Service::EXPECT_NONE),
            $this->route('service_template')?->id
        );
    }
}
