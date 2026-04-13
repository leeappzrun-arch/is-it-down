<?php

namespace Tests\Unit;

use App\Support\Monitoring\ResponseHeaderData;
use PHPUnit\Framework\TestCase;

class ResponseHeaderDataTest extends TestCase
{
    public function test_it_normalizes_structured_headers_without_losing_name_value_pairs(): void
    {
        $normalized = ResponseHeaderData::normalize([
            ['name' => 'Content-Type', 'value' => 'text/plain'],
            ['name' => 'Retry-After', 'value' => '120'],
        ]);

        $this->assertSame([
            ['name' => 'Content-Type', 'value' => 'text/plain'],
            ['name' => 'Retry-After', 'value' => '120'],
        ], $normalized);
    }

    public function test_it_normalizes_header_maps_from_http_client_responses(): void
    {
        $normalized = ResponseHeaderData::normalize([
            'Retry-After' => ['120'],
            'Content-Type' => ['text/plain'],
        ]);

        $this->assertSame([
            ['name' => 'Content-Type', 'value' => 'text/plain'],
            ['name' => 'Retry-After', 'value' => '120'],
        ], $normalized);
    }
}
