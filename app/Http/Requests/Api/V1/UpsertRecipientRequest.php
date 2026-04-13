<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\RecipientValidation;
use App\Models\Recipient;
use App\Support\Services\ServiceData;
use Illuminate\Foundation\Http\FormRequest;

class UpsertRecipientRequest extends FormRequest
{
    use RecipientValidation;

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
            'endpointType' => $this->input('endpoint_type', $this->input('endpointType', Recipient::TYPE_MAIL)),
            'endpointTarget' => $this->input('endpoint_target', $this->input('endpointTarget', '')),
            'selectedGroupIds' => $this->input('group_ids', $this->input('selectedGroupIds', [])),
            'additionalHeaders' => ServiceData::normalizeAdditionalHeaders($this->input('additional_headers', $this->input('additionalHeaders', []))),
            'webhookAuthType' => $this->input('webhook_auth_type', $this->input('webhookAuthType', Recipient::WEBHOOK_AUTH_NONE)),
            'webhookAuthUsername' => $this->input('webhook_auth_username', $this->input('webhookAuthUsername', '')),
            'webhookAuthPassword' => $this->input('webhook_auth_password', $this->input('webhookAuthPassword', '')),
            'webhookAuthToken' => $this->input('webhook_auth_token', $this->input('webhookAuthToken', '')),
            'webhookAuthHeaderName' => $this->input('webhook_auth_header_name', $this->input('webhookAuthHeaderName', '')),
            'webhookAuthHeaderValue' => $this->input('webhook_auth_header_value', $this->input('webhookAuthHeaderValue', '')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->recipientValidationRules(
            (string) $this->input('endpointType', Recipient::TYPE_MAIL),
            (string) $this->input('webhookAuthType', Recipient::WEBHOOK_AUTH_NONE),
        );
    }
}
