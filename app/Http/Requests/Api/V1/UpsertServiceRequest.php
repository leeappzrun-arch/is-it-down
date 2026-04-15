<?php

namespace App\Http\Requests\Api\V1;

use App\Concerns\ServiceValidation;
use App\Models\Service;
use App\Models\ServiceTemplate;
use App\Support\Services\ServiceData;
use App\Support\Services\ServiceTemplateData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpsertServiceRequest extends FormRequest
{
    use ServiceValidation;

    private ?string $templateValidationError = null;

    private bool $templateRequested = false;

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
        $templateDefaults = $this->templateDefaults();

        $this->merge([
            'intervalSeconds' => $this->input('interval_seconds', $templateDefaults['intervalSeconds'] ?? $this->input('intervalSeconds', Service::INTERVAL_1_MINUTE)),
            'monitoringMethod' => $this->input('monitoring_method', $templateDefaults['monitoringMethod'] ?? $this->input('monitoringMethod', Service::MONITOR_HTTP)),
            'expectType' => $this->input('expect_type', $templateDefaults['expectType'] ?? $this->input('expectType', Service::EXPECT_NONE)),
            'expectValue' => $this->input('expect_value', $templateDefaults['expectValue'] ?? $this->input('expectValue', '')),
            'additionalHeaders' => ServiceData::normalizeAdditionalHeaders($this->input('additional_headers', $templateDefaults['additionalHeaders'] ?? $this->input('additionalHeaders', []))),
            'sslExpiryNotificationsEnabled' => $this->input('ssl_expiry_notifications_enabled', $templateDefaults['sslExpiryNotificationsEnabled'] ?? $this->input('sslExpiryNotificationsEnabled', false)),
            'selectedServiceGroupIds' => $this->input('service_group_ids', $templateDefaults['selectedServiceGroupIds'] ?? $this->input('selectedServiceGroupIds', [])),
            'selectedRecipientGroupIds' => $this->input('recipient_group_ids', $templateDefaults['selectedRecipientGroupIds'] ?? $this->input('selectedRecipientGroupIds', [])),
            'selectedRecipientIds' => $this->input('recipient_ids', $templateDefaults['selectedRecipientIds'] ?? $this->input('selectedRecipientIds', [])),
            'name' => $this->input('name', $templateDefaults['name'] ?? ''),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->templateValidationError !== null) {
                    $validator->errors()->add('template', $this->templateValidationError);
                }
            },
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $usesTemplateDefaults = $this->route('service') === null && $this->templateRequested;

        return $this->serviceValidationRules(
            (string) $this->input('expectType', Service::EXPECT_NONE),
            requiresName: ! $usesTemplateDefaults,
        );
    }

    /**
     * Resolve the template defaults requested by the API caller.
     *
     * @return array<string, mixed>
     */
    private function templateDefaults(): array
    {
        $templateIdentifier = $this->input('template', $this->input('template_id'));

        $this->templateRequested = filled($templateIdentifier);

        if (! $this->templateRequested) {
            return [];
        }

        $template = $this->resolveTemplate($templateIdentifier);

        if (! $template instanceof ServiceTemplate) {
            return [];
        }

        return ServiceTemplateData::serviceFormState($template->serviceConfiguration());
    }

    /**
     * Resolve a service template by numeric id or exact name.
     */
    private function resolveTemplate(mixed $templateIdentifier): ?ServiceTemplate
    {
        if (is_numeric($templateIdentifier)) {
            $template = ServiceTemplate::query()->find((int) $templateIdentifier);

            if ($template instanceof ServiceTemplate) {
                return $template;
            }
        }

        if (! is_string($templateIdentifier) || trim($templateIdentifier) === '') {
            $this->templateValidationError = 'Provide a valid service template id or exact name.';

            return null;
        }

        $template = ServiceTemplate::query()
            ->where('name', trim($templateIdentifier))
            ->first();

        if ($template instanceof ServiceTemplate) {
            return $template;
        }

        $this->templateValidationError = 'No service template matched that identifier.';

        return null;
    }
}
