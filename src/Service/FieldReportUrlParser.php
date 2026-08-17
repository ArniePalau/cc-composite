<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Service;

use InvalidArgumentException;

final class FieldReportUrlParser
{
    /** @return array{source_url: string, api_url: string, code: string} */
    public function parse(string $url): array
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            throw new InvalidArgumentException('Enter a valid HTTP or HTTPS report URL.');
        }

        $host = strtolower((string) $parts['host']);
        $this->assertPublicHost($host);
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if (!preg_match('~/(?:r|api/public/report)/([A-Za-z0-9_-]{3,64})$~', $path, $matches)) {
            throw new InvalidArgumentException('The URL must point to a public report (/r/CODE or /api/public/report/CODE).');
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $origin = sprintf('%s://%s%s', $parts['scheme'], $host, $port);
        $code = $matches[1];

        return [
            'source_url' => $origin . '/r/' . $code,
            'api_url' => $origin . '/api/public/report/' . $code,
            'code' => $code,
        ];
    }

    private function assertPublicHost(string $host): void
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new InvalidArgumentException('Private and reserved report hosts are not allowed.');
            }
            return;
        }

        $addresses = gethostbynamel($host);
        if ($addresses === false || $addresses === []) {
            throw new InvalidArgumentException('The report host could not be resolved.');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new InvalidArgumentException('The report host resolves to a private or reserved address.');
            }
        }
    }
}
