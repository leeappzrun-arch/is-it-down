<?php

namespace App\Models;

use App\Support\Services\ServiceTemplateData;
use Database\Factories\ServiceTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'configuration',
])]
class ServiceTemplate extends Model
{
    /** @use HasFactory<ServiceTemplateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'configuration' => 'array',
        ];
    }

    /**
     * Get the normalized service configuration stored on the template.
     *
     * @return array{name: string, interval_seconds: int, expect_type: ?string, expect_value: ?string, additional_headers: array<int, array{name: string, value: string}>, ssl_expiry_notifications_enabled: bool, service_group_ids: array<int, int>, recipient_group_ids: array<int, int>, recipient_ids: array<int, int>}
     */
    public function serviceConfiguration(): array
    {
        return ServiceTemplateData::normalizeConfiguration($this->configuration);
    }

    /**
     * Get the form state used to start a new service from this template.
     *
     * @return array{name: string, url: string, intervalSeconds: int, expectType: string, expectValue: string, additionalHeaders: array<int, array{name: string, value: string}>, sslExpiryNotificationsEnabled: bool, selectedServiceGroupIds: array<int, string>, selectedRecipientGroupIds: array<int, string>, selectedRecipientIds: array<int, string>}
     */
    public function serviceFormState(): array
    {
        return ServiceTemplateData::serviceFormState($this->serviceConfiguration());
    }

    /**
     * Get the service name stored on this template.
     */
    public function serviceName(): string
    {
        return $this->serviceConfiguration()['name'];
    }

    /**
     * Get the service interval stored on this template.
     */
    public function intervalSeconds(): int
    {
        return $this->serviceConfiguration()['interval_seconds'];
    }

    /**
     * Get the human-readable interval label.
     */
    public function intervalLabel(): string
    {
        return Service::intervalOptions()[$this->intervalSeconds()] ?? 'Custom interval';
    }

    /**
     * Get the expectation type stored on this template.
     */
    public function expectType(): string
    {
        return $this->serviceConfiguration()['expect_type'] ?? Service::EXPECT_NONE;
    }

    /**
     * Get the expectation value stored on this template.
     */
    public function expectValue(): ?string
    {
        return $this->serviceConfiguration()['expect_value'];
    }

    /**
     * Determine whether the template has an expectation configured.
     */
    public function hasExpectation(): bool
    {
        return filled($this->expectValue()) && $this->expectType() !== Service::EXPECT_NONE;
    }

    /**
     * Get the configured additional headers stored on the template.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function configuredAdditionalHeaders(): array
    {
        return $this->serviceConfiguration()['additional_headers'];
    }

    /**
     * Get the configured additional header summary.
     */
    public function additionalHeadersSummary(): string
    {
        $headerCount = count($this->configuredAdditionalHeaders());

        if ($headerCount === 0) {
            return 'No additional headers';
        }

        return trim(trans_choice('{1} :count additional header|[2,*] :count additional headers', $headerCount, ['count' => $headerCount]));
    }

    /**
     * Determine whether SSL expiry notifications are enabled on the template.
     */
    public function sslExpiryNotificationsEnabled(): bool
    {
        return $this->serviceConfiguration()['ssl_expiry_notifications_enabled'];
    }

    /**
     * Get the configured expectation summary.
     */
    public function expectSummary(): string
    {
        if (! $this->hasExpectation()) {
            return 'No expectation';
        }

        $label = $this->expectType() === Service::EXPECT_REGEX ? 'Regex' : 'Text';

        return $label.': '.Str::limit((string) $this->expectValue(), 80);
    }

    /**
     * Get the saved service group ids.
     *
     * @return array<int, int>
     */
    public function selectedServiceGroupIds(): array
    {
        return $this->serviceConfiguration()['service_group_ids'];
    }

    /**
     * Get the saved recipient group ids.
     *
     * @return array<int, int>
     */
    public function selectedRecipientGroupIds(): array
    {
        return $this->serviceConfiguration()['recipient_group_ids'];
    }

    /**
     * Get the saved direct recipient ids.
     *
     * @return array<int, int>
     */
    public function selectedRecipientIds(): array
    {
        return $this->serviceConfiguration()['recipient_ids'];
    }
}
