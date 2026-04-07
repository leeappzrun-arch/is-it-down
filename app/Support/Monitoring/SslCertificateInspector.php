<?php

namespace App\Support\Monitoring;

use App\Models\Service;
use Carbon\CarbonImmutable;

class SslCertificateInspector
{
    /**
     * Inspect the configured SSL certificate for a service.
     */
    public function inspect(Service $service): ?SslCertificateInspectionResult
    {
        if (! $service->usesHttps()) {
            return null;
        }

        $host = parse_url($service->url, PHP_URL_HOST);
        $port = (int) (parse_url($service->url, PHP_URL_PORT) ?: 443);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $client = @stream_socket_client(
            'ssl://'.$host.':'.$port,
            $errorCode,
            $errorMessage,
            10,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($client === false) {
            return null;
        }

        $contextOptions = stream_context_get_params($client);

        fclose($client);

        $certificate = $contextOptions['options']['ssl']['peer_certificate'] ?? null;

        if ($certificate === null) {
            return null;
        }

        $parsedCertificate = openssl_x509_parse($certificate);
        $validToTimestamp = $parsedCertificate['validTo_time_t'] ?? null;

        if (! is_int($validToTimestamp) && ! ctype_digit((string) $validToTimestamp)) {
            return null;
        }

        return new SslCertificateInspectionResult(
            expiresAt: CarbonImmutable::createFromTimestampUTC((int) $validToTimestamp),
        );
    }
}
