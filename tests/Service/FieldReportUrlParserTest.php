<?php

declare(strict_types=1);

namespace ArniePalau\CcComposite\Tests\Service;

use ArniePalau\CcComposite\Service\FieldReportUrlParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FieldReportUrlParserTest extends TestCase
{
    public function testConvertsPublicReportPageToApiEndpoint(): void
    {
        $result = (new FieldReportUrlParser())->parse('http://188.165.210.53:8080/r/3C3LTu5Qqc');

        self::assertSame('3C3LTu5Qqc', $result['code']);
        self::assertSame('http://188.165.210.53:8080/r/3C3LTu5Qqc', $result['source_url']);
        self::assertSame('http://188.165.210.53:8080/api/public/report/3C3LTu5Qqc', $result['api_url']);
    }

    public function testAcceptsApiReportUrl(): void
    {
        $result = (new FieldReportUrlParser())->parse('https://1.1.1.1/api/public/report/example_123');

        self::assertSame('https://1.1.1.1/r/example_123', $result['source_url']);
        self::assertSame('https://1.1.1.1/api/public/report/example_123', $result['api_url']);
    }

    public function testRejectsPrivateHosts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FieldReportUrlParser())->parse('http://127.0.0.1:8080/r/secret');
    }

    public function testRejectsUnrecognizedPaths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FieldReportUrlParser())->parse('https://1.1.1.1/not-a-report');
    }
}
