<?php

declare(strict_types=1);

namespace Tests\Twig;

use Daycry\Twig\Support\TemplateNameValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class TemplateNameValidatorTest extends TestCase
{
    public function testRejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TemplateNameValidator::assertValid('');
    }

    public function testRejectsNullByte(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TemplateNameValidator::assertValid("foo\0bar");
    }

    #[DataProvider('provideRejectsTraversal')]
    public function testRejectsTraversal(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        TemplateNameValidator::assertValid($name);
    }

    /**
     * @return iterable<string,array{0:string}>
     */
    public static function provideRejectsTraversal(): iterable
    {
        yield 'parent reference' => ['..'];

        yield 'leading parent' => ['../foo'];

        yield 'embedded parent' => ['foo/../bar'];

        yield 'trailing parent' => ['foo/..'];

        yield 'absolute unix' => ['/etc/passwd'];

        yield 'absolute windows' => ['\\Windows\\System32'];
    }

    #[DataProvider('provideAcceptsValidNames')]
    public function testAcceptsValidNames(string $name): void
    {
        $this->assertSame($name, TemplateNameValidator::assertValid($name));
    }

    /**
     * @return iterable<string,array{0:string}>
     */
    public static function provideAcceptsValidNames(): iterable
    {
        yield 'simple' => ['welcome'];

        yield 'subfolder' => ['layout/main'];

        yield 'namespaced' => ['@admin/dashboard'];

        yield 'with hyphens' => ['emails/order-receipt'];

        yield 'underscores' => ['user_profile'];
    }

    public function testFilterValidDropsBadOnesAndCallsCallback(): void
    {
        $errors = [];
        $valid  = TemplateNameValidator::filterValid(
            ['ok', '../bad', 'also_ok', "with\0null"],
            static function (string $raw, string $msg) use (&$errors): void {
                $errors[] = $raw;
            },
        );
        $this->assertSame(['ok', 'also_ok'], $valid);
        $this->assertSame(['../bad', "with\0null"], $errors);
    }

    public function testNamespaceValidationStripsAtSign(): void
    {
        $this->assertSame('@admin', TemplateNameValidator::assertValidNamespace('@admin'));
        $this->assertSame('admin', TemplateNameValidator::assertValidNamespace('admin'));
        $this->assertNull(TemplateNameValidator::assertValidNamespace(null));
        $this->assertNull(TemplateNameValidator::assertValidNamespace(''));
    }

    public function testNamespaceRejectsInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TemplateNameValidator::assertValidNamespace('not/valid');
    }
}
