<?php

declare(strict_types=1);

namespace Tests\Twig;

use Daycry\Twig\Support\PersistenceDecoder;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PersistenceDecoderTest extends TestCase
{
    public function testDecodeReturnsNullForMissingOrInvalid(): void
    {
        $this->assertNull(PersistenceDecoder::decode(null));
        $this->assertNull(PersistenceDecoder::decode(''));
        $this->assertNull(PersistenceDecoder::decode('not-json'));
        $this->assertNull(PersistenceDecoder::decode('"string-root"'));
        $this->assertNull(PersistenceDecoder::decode('42'));
    }

    public function testDecodeReturnsArrayWhenRootIsObject(): void
    {
        $this->assertSame(['a' => 1], PersistenceDecoder::decode('{"a":1}'));
    }

    public function testSchemaRejectsMissingRequiredKey(): void
    {
        $json = '{"foo": 1}';
        $this->assertNull(PersistenceDecoder::decodeWithSchema($json, ['bar' => 'int']));
    }

    public function testSchemaRejectsWrongType(): void
    {
        $json = '{"count": "not-a-number"}';
        $this->assertNull(PersistenceDecoder::decodeWithSchema($json, ['count' => 'int']));
    }

    public function testSchemaAllowsOptionalMissingKey(): void
    {
        $json = '{"count": 1}';
        $this->assertSame(['count' => 1], PersistenceDecoder::decodeWithSchema($json, [
            'count' => 'int',
            'last'  => '?array',
        ]));
    }

    public function testSchemaAcceptsMatchingTypes(): void
    {
        $json = '{"count": 1, "ok": true, "name": "foo", "ratio": 0.5, "list": [1,2]}';
        $out  = PersistenceDecoder::decodeWithSchema($json, [
            'count' => 'int',
            'ok'    => 'bool',
            'name'  => 'string',
            'ratio' => 'float',
            'list'  => 'array',
        ]);
        $this->assertNotNull($out);
        $this->assertSame(1, $out['count']);
    }
}
