<?php

namespace App\Support;

class ApiDocumentation
{
    /**
     * Get the supported API endpoints used by the docs and playground.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function endpoints(): array
    {
        return [
            [
                'key' => 'recipients.index',
                'label' => 'List recipients',
                'method' => 'GET',
                'path' => '/api/v1/recipients',
                'permission' => 'recipients:read',
                'description' => 'Return the current recipients with optional search, endpoint-type filtering, and group filtering.',
                'query_parameters' => [
                    ['name' => 'search', 'required' => false, 'description' => 'Filter by recipient name, endpoint, auth type, or group name.'],
                    ['name' => 'endpoint_type', 'required' => false, 'description' => 'Use `mail` or `webhook`.'],
                    ['name' => 'group_id', 'required' => false, 'description' => 'Limit results to one recipient group.'],
                    ['name' => 'per_page', 'required' => false, 'description' => 'Set the paginator size up to 100.'],
                ],
                'body_example' => null,
            ],
            [
                'key' => 'recipients.store',
                'label' => 'Create recipient',
                'method' => 'POST',
                'path' => '/api/v1/recipients',
                'permission' => 'recipients:write',
                'description' => 'Create a recipient using the same validation rules as the Recipients page.',
                'query_parameters' => [],
                'body_example' => [
                    'name' => 'Operations webhook',
                    'endpoint_type' => 'webhook',
                    'endpoint_target' => 'https://example.com/hooks/ops',
                    'webhook_auth_type' => 'bearer',
                    'webhook_auth_token' => 'secret-token',
                    'group_ids' => [1],
                ],
            ],
            [
                'key' => 'recipients.show',
                'label' => 'Show recipient',
                'method' => 'GET',
                'path' => '/api/v1/recipients/{recipient}',
                'permission' => 'recipients:read',
                'description' => 'Return one recipient with its assigned groups.',
                'query_parameters' => [],
                'body_example' => null,
            ],
            [
                'key' => 'recipients.update',
                'label' => 'Update recipient',
                'method' => 'PATCH',
                'path' => '/api/v1/recipients/{recipient}',
                'permission' => 'recipients:write',
                'description' => 'Update a recipient using the same body shape as creation.',
                'query_parameters' => [],
                'body_example' => [
                    'name' => 'Operations inbox',
                    'endpoint_type' => 'mail',
                    'endpoint_target' => 'ops@example.com',
                    'group_ids' => [1, 2],
                ],
            ],
            [
                'key' => 'recipients.destroy',
                'label' => 'Delete recipient',
                'method' => 'DELETE',
                'path' => '/api/v1/recipients/{recipient}',
                'permission' => 'recipients:write',
                'description' => 'Delete a recipient and return an empty `204 No Content` response.',
                'query_parameters' => [],
                'body_example' => null,
            ],
            [
                'key' => 'recipient-groups.index',
                'label' => 'List recipient groups',
                'method' => 'GET',
                'path' => '/api/v1/recipient-groups',
                'permission' => 'recipients:read',
                'description' => 'Return recipient groups with recipient counts and search support.',
                'query_parameters' => [
                    ['name' => 'search', 'required' => false, 'description' => 'Filter by group name.'],
                    ['name' => 'per_page', 'required' => false, 'description' => 'Set the paginator size up to 100.'],
                ],
                'body_example' => null,
            ],
            [
                'key' => 'recipient-groups.store',
                'label' => 'Create recipient group',
                'method' => 'POST',
                'path' => '/api/v1/recipient-groups',
                'permission' => 'recipients:write',
                'description' => 'Create a recipient group.',
                'query_parameters' => [],
                'body_example' => [
                    'name' => 'Operations',
                ],
            ],
            [
                'key' => 'recipient-groups.show',
                'label' => 'Show recipient group',
                'method' => 'GET',
                'path' => '/api/v1/recipient-groups/{recipient_group}',
                'permission' => 'recipients:read',
                'description' => 'Return a recipient group with its current members.',
                'query_parameters' => [],
                'body_example' => null,
            ],
            [
                'key' => 'recipient-groups.update',
                'label' => 'Update recipient group',
                'method' => 'PATCH',
                'path' => '/api/v1/recipient-groups/{recipient_group}',
                'permission' => 'recipients:write',
                'description' => 'Rename a recipient group.',
                'query_parameters' => [],
                'body_example' => [
                    'name' => 'Leadership',
                ],
            ],
            [
                'key' => 'recipient-groups.destroy',
                'label' => 'Delete recipient group',
                'method' => 'DELETE',
                'path' => '/api/v1/recipient-groups/{recipient_group}',
                'permission' => 'recipients:write',
                'description' => 'Delete a recipient group and return `204 No Content`.',
                'query_parameters' => [],
                'body_example' => null,
            ],
            [
                'key' => 'services.index',
                'label' => 'List services',
                'method' => 'GET',
                'path' => '/api/v1/services',
                'permission' => 'services:read',
                'description' => 'Return monitored services with search, status filters, and assignment filters.',
                'query_parameters' => [
                    ['name' => 'search', 'required' => false, 'description' => 'Filter by service details, expectation, additional headers, or assigned groups and recipients.'],
                    ['name' => 'status', 'required' => false, 'description' => 'Use `up`, `down`, or `pending`.'],
                    ['name' => 'service_group_id', 'required' => false, 'description' => 'Limit results to one service group.'],
                    ['name' => 'recipient_group_id', 'required' => false, 'description' => 'Limit results to one direct recipient group.'],
                    ['name' => 'recipient_id', 'required' => false, 'description' => 'Limit results to one direct recipient.'],
                    ['name' => 'per_page', 'required' => false, 'description' => 'Set the paginator size up to 100.'],
                ],
                'body_example' => null,
            ],
            [
                'key' => 'services.store',
                'label' => 'Create service',
                'method' => 'POST',
                'path' => '/api/v1/services',
                'permission' => 'services:write',
                'description' => 'Create a monitored service using the same validation rules as the Services page. You can also pass a saved service template id or exact name and then override any fields you want to change, including additional request headers and SSL expiry notifications.',
                'query_parameters' => [],
                'body_example' => [
                    'template' => 'Marketing site starter',
                    'name' => 'Marketing Site',
                    'url' => 'https://example.com/status',
                    'interval_seconds' => 60,
                    'expect_type' => 'text',
                    'expect_value' => 'Healthy',
                    'additional_headers' => [
                        ['name' => 'X-Monitor', 'value' => 'is-it-down'],
                    ],
                    'ssl_expiry_notifications_enabled' => true,
                    'service_group_ids' => [1],
                    'recipient_group_ids' => [1],
                    'recipient_ids' => [1],
                ],
            ],
            [
                'key' => 'services.show',
                'label' => 'Show service',
                'method' => 'GET',
                'path' => '/api/v1/services/{service}',
                'permission' => 'services:read',
                'description' => 'Return one service with its assignments and monitoring state.',
                'query_parameters' => [],
                'body_example' => null,
            ],
            [
                'key' => 'services.update',
                'label' => 'Update service',
                'method' => 'PATCH',
                'path' => '/api/v1/services/{service}',
                'permission' => 'services:write',
                'description' => 'Update a service using the same body shape as creation.',
                'query_parameters' => [],
                'body_example' => [
                    'name' => 'Marketing Site',
                    'url' => 'example.com/status',
                    'interval_seconds' => 300,
                    'expect_type' => 'none',
                    'expect_value' => '',
                    'additional_headers' => [],
                    'ssl_expiry_notifications_enabled' => false,
                    'service_group_ids' => [1],
                    'recipient_group_ids' => [],
                    'recipient_ids' => [],
                ],
            ],
            [
                'key' => 'services.destroy',
                'label' => 'Delete service',
                'method' => 'DELETE',
                'path' => '/api/v1/services/{service}',
                'permission' => 'services:write',
                'description' => 'Delete a service and return `204 No Content`.',
                'query_parameters' => [],
                'body_example' => null,
            ],
            [
                'key' => 'service-templates.index',
                'label' => 'List service templates',
                'method' => 'GET',
                'path' => '/api/v1/service-templates',
                'permission' => 'templates:read',
                'description' => 'Return saved service templates with search support and assignment filters.',
                'query_parameters' => [
                    ['name' => 'search', 'required' => false, 'description' => 'Filter by template name, default service name, expectation, or saved additional headers.'],
                    ['name' => 'service_group_id', 'required' => false, 'description' => 'Limit results to templates that include one service group id.'],
                    ['name' => 'recipient_group_id', 'required' => false, 'description' => 'Limit results to templates that include one recipient group id.'],
                    ['name' => 'recipient_id', 'required' => false, 'description' => 'Limit results to templates that include one direct recipient id.'],
                    ['name' => 'per_page', 'required' => false, 'description' => 'Set the paginator size up to 100.'],
                ],
                'body_example' => null,
            ],
            [
                'key' => 'service-templates.store',
                'label' => 'Create service template',
                'method' => 'POST',
                'path' => '/api/v1/service-templates',
                'permission' => 'templates:write',
                'description' => 'Create a reusable service template without a URL.',
                'query_parameters' => [],
                'body_example' => [
                    'name' => 'Marketing site starter',
                    'service_name' => 'Marketing Site',
                    'interval_seconds' => 60,
                    'expect_type' => 'text',
                    'expect_value' => 'Healthy',
                    'additional_headers' => [
                        ['name' => 'X-Monitor', 'value' => 'is-it-down'],
                    ],
                    'ssl_expiry_notifications_enabled' => true,
                    'service_group_ids' => [1],
                    'recipient_group_ids' => [1],
                    'recipient_ids' => [1],
                ],
            ],
            [
                'key' => 'service-templates.show',
                'label' => 'Show service template',
                'method' => 'GET',
                'path' => '/api/v1/service-templates/{service_template}',
                'permission' => 'templates:read',
                'description' => 'Return one saved service template and its stored assignment ids.',
                'query_parameters' => [],
                'body_example' => null,
            ],
            [
                'key' => 'service-templates.update',
                'label' => 'Update service template',
                'method' => 'PATCH',
                'path' => '/api/v1/service-templates/{service_template}',
                'permission' => 'templates:write',
                'description' => 'Update a service template using the same body shape as creation.',
                'query_parameters' => [],
                'body_example' => [
                    'name' => 'Marketing site starter',
                    'service_name' => 'Marketing Site',
                    'interval_seconds' => 300,
                    'expect_type' => 'none',
                    'expect_value' => '',
                    'additional_headers' => [],
                    'ssl_expiry_notifications_enabled' => false,
                    'service_group_ids' => [1],
                    'recipient_group_ids' => [],
                    'recipient_ids' => [],
                ],
            ],
            [
                'key' => 'service-templates.destroy',
                'label' => 'Delete service template',
                'method' => 'DELETE',
                'path' => '/api/v1/service-templates/{service_template}',
                'permission' => 'templates:write',
                'description' => 'Delete a service template and return `204 No Content`.',
                'query_parameters' => [],
                'body_example' => null,
            ],
            [
                'key' => 'service-groups.index',
                'label' => 'List service groups',
                'method' => 'GET',
                'path' => '/api/v1/service-groups',
                'permission' => 'services:read',
                'description' => 'Return service groups with search support and assignment filters.',
                'query_parameters' => [
                    ['name' => 'search', 'required' => false, 'description' => 'Filter by group, service, recipient group, or recipient details.'],
                    ['name' => 'service_id', 'required' => false, 'description' => 'Limit results to one linked service.'],
                    ['name' => 'recipient_group_id', 'required' => false, 'description' => 'Limit results to one recipient group assignment.'],
                    ['name' => 'recipient_id', 'required' => false, 'description' => 'Limit results to one direct recipient assignment.'],
                    ['name' => 'per_page', 'required' => false, 'description' => 'Set the paginator size up to 100.'],
                ],
                'body_example' => null,
            ],
            [
                'key' => 'service-groups.store',
                'label' => 'Create service group',
                'method' => 'POST',
                'path' => '/api/v1/service-groups',
                'permission' => 'services:write',
                'description' => 'Create a service group and attach recipient groups or direct recipients.',
                'query_parameters' => [],
                'body_example' => [
                    'name' => 'Production',
                    'recipient_group_ids' => [1],
                    'recipient_ids' => [1],
                ],
            ],
            [
                'key' => 'service-groups.show',
                'label' => 'Show service group',
                'method' => 'GET',
                'path' => '/api/v1/service-groups/{service_group}',
                'permission' => 'services:read',
                'description' => 'Return a service group with its linked services and routing ingredients.',
                'query_parameters' => [],
                'body_example' => null,
            ],
            [
                'key' => 'service-groups.update',
                'label' => 'Update service group',
                'method' => 'PATCH',
                'path' => '/api/v1/service-groups/{service_group}',
                'permission' => 'services:write',
                'description' => 'Update a service group using the same body shape as creation.',
                'query_parameters' => [],
                'body_example' => [
                    'name' => 'Production',
                    'recipient_group_ids' => [1, 2],
                    'recipient_ids' => [1],
                ],
            ],
            [
                'key' => 'service-groups.destroy',
                'label' => 'Delete service group',
                'method' => 'DELETE',
                'path' => '/api/v1/service-groups/{service_group}',
                'permission' => 'services:write',
                'description' => 'Delete a service group and return `204 No Content`.',
                'query_parameters' => [],
                'body_example' => null,
            ],
            [
                'key' => 'users.index',
                'label' => 'List users',
                'method' => 'GET',
                'path' => '/api/v1/users',
                'permission' => 'users:read',
                'description' => 'Return users with search and role filters.',
                'query_parameters' => [
                    ['name' => 'search', 'required' => false, 'description' => 'Filter by name, email, or role.'],
                    ['name' => 'role', 'required' => false, 'description' => 'Limit results to one role such as `admin` or `user`.'],
                    ['name' => 'per_page', 'required' => false, 'description' => 'Set the paginator size up to 100.'],
                ],
                'body_example' => null,
            ],
            [
                'key' => 'users.store',
                'label' => 'Create user',
                'method' => 'POST',
                'path' => '/api/v1/users',
                'permission' => 'users:write',
                'description' => 'Create a user using the same creation validation rules as the Users page.',
                'query_parameters' => [],
                'body_example' => [
                    'name' => 'Example User',
                    'email' => 'user@example.com',
                    'password' => 'password',
                    'password_confirmation' => 'password',
                    'role' => 'user',
                ],
            ],
            [
                'key' => 'users.show',
                'label' => 'Show user',
                'method' => 'GET',
                'path' => '/api/v1/users/{user}',
                'permission' => 'users:read',
                'description' => 'Return one user record.',
                'query_parameters' => [],
                'body_example' => null,
            ],
            [
                'key' => 'users.update',
                'label' => 'Update user',
                'method' => 'PATCH',
                'path' => '/api/v1/users/{user}',
                'permission' => 'users:write',
                'description' => 'Update a user. The last remaining admin cannot be downgraded.',
                'query_parameters' => [],
                'body_example' => [
                    'name' => 'Example User',
                    'email' => 'user@example.com',
                    'role' => 'admin',
                ],
            ],
            [
                'key' => 'users.destroy',
                'label' => 'Delete user',
                'method' => 'DELETE',
                'path' => '/api/v1/users/{user}',
                'permission' => 'users:write',
                'description' => 'Delete a standard user. Admin accounts cannot be deleted through this endpoint.',
                'query_parameters' => [],
                'body_example' => null,
            ],
        ];
    }

    /**
     * Get the default endpoint key for the playground.
     */
    public static function defaultEndpointKey(): string
    {
        return self::endpoints()[0]['key'];
    }

    /**
     * Get a single endpoint definition by key.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::endpoints() as $endpoint) {
            if ($endpoint['key'] === $key) {
                return $endpoint;
            }
        }

        return null;
    }
}
