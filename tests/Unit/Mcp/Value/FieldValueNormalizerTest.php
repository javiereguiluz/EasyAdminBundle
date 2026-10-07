<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Mcp\Value;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Value\ChoiceValueNormalizer;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Value\DateTimeValueNormalizer;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Value\FieldValueNormalizer;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Value\MoneyValueNormalizer;
use EasyCorp\Bundle\EasyAdminBundle\Mcp\Value\ScalarValueNormalizer;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\TestCase;

class FieldValueNormalizerTest extends TestCase
{
    /**
     * @dataProvider provideValues
     */
    public function testNormalize(FieldInterface $field, mixed $value, mixed $expectedValue): void
    {
        $fieldDto = $this->createFieldDto($field, $value);

        $this->assertSame($expectedValue, $this->createNormalizer()->normalize($fieldDto)->value);
    }

    public static function provideValues(): iterable
    {
        yield 'text' => [TextField::new('name'), 'Product 1', 'Product 1'];
        yield 'null' => [TextField::new('name'), null, null];
        yield 'text with object value' => [TextField::new('name'), new class {
            public function __toString(): string
            {
                return 'never cast';
            }
        }, null];
        yield 'email' => [EmailField::new('email'), 'user@example.com', 'user@example.com'];
        yield 'url' => [UrlField::new('url'), 'https://example.com', 'https://example.com'];
        yield 'integer' => [IntegerField::new('stock'), 7, 7];
        yield 'decimal string keeps precision' => [NumberField::new('weight'), '12.345', '12.345'];
        yield 'boolean' => [BooleanField::new('active'), 1, true];
        yield 'date' => [DateField::new('publishedAt'), new \DateTimeImmutable('2026-10-05 14:30:00'), '2026-10-05'];
        yield 'datetime' => [DateTimeField::new('publishedAt'), new \DateTimeImmutable('2026-10-05 14:30:00', new \DateTimeZone('Europe/Madrid')), '2026-10-05T14:30:00+02:00'];
        yield 'time' => [TimeField::new('startsAt'), new \DateTimeImmutable('2026-10-05 14:30:15'), '14:30:15'];
        yield 'choice' => [ChoiceField::new('status')->setChoices(['Draft' => 'draft']), 'draft', 'draft'];
        yield 'multiple choices' => [ChoiceField::new('roles')->allowMultipleChoices(), ['admin', 'editor'], ['admin', 'editor']];
        yield 'backed enum choice' => [ChoiceField::new('suit'), Suit::Hearts, 'H'];
    }

    public function testMoneyStoredAsCents(): void
    {
        $fieldDto = $this->createFieldDto(MoneyField::new('price')->setCurrency('EUR'), 1250);
        $fieldDto->setFormTypeOption('currency', 'EUR');
        $fieldDto->setFormTypeOption('divisor', 100);
        $fieldDto->setFormTypeOption('scale', 2);

        $this->assertSame(['amount' => '12.50', 'currency' => 'EUR'], $this->createNormalizer()->normalize($fieldDto)->value);
    }

    public function testMoneyObject(): void
    {
        $fieldDto = $this->createFieldDto(MoneyField::new('price'), new Money(1999, new Currency('USD')));
        $fieldDto->setFormTypeOption('currency', 'USD');
        $fieldDto->setFormTypeOption('divisor', 100);
        $fieldDto->setFormTypeOption('scale', 2);

        $this->assertSame(['amount' => '19.99', 'currency' => 'USD'], $this->createNormalizer()->normalize($fieldDto)->value);
    }

    public function testFormatValueOutputWinsOverTheRawValue(): void
    {
        $fieldDto = $this->createFieldDto(TextField::new('cardNumber'), '4111111111111234');
        $fieldDto->setFormatValueCallable(static fn (string $value): string => '**** '.substr($value, -4));
        $fieldDto->setFormattedValue('<span class="masked">**** 1234</span> &amp; more');

        $normalizer = $this->createNormalizer();
        $this->assertSame('**** 1234 & more', $normalizer->normalize($fieldDto)->value);
        $this->assertSame(['type' => 'string', 'description' => 'formatted value'], $normalizer->getSchema($fieldDto));
    }

    public function testLongStringsAreTruncated(): void
    {
        $normalizedValue = $this->createNormalizer(10)->normalize($this->createFieldDto(TextField::new('name'), str_repeat('á', 12)));

        $this->assertSame(str_repeat('á', 10), $normalizedValue->value);
        $this->assertTrue($normalizedValue->truncated);
    }

    /**
     * @dataProvider provideUnsupportedFields
     */
    public function testUnsupportedFields(FieldInterface $field): void
    {
        $fieldDto = $this->createFieldDto($field, 'value');
        $normalizer = $this->createNormalizer();

        $this->assertFalse($normalizer->isSupported($fieldDto));
        $this->assertNull($normalizer->getSchema($fieldDto));

        $this->expectException(\LogicException::class);
        $normalizer->normalize($fieldDto);
    }

    public static function provideUnsupportedFields(): iterable
    {
        yield 'image' => [ImageField::new('photo')];
        yield 'code editor' => [CodeEditorField::new('config')];
    }

    public function testChoiceSchema(): void
    {
        $normalizer = $this->createNormalizer();

        $fieldDto = $this->createFieldDto(ChoiceField::new('status'), null);
        $fieldDto->setFormTypeOption('choices', ['Draft' => 'draft', 'Group' => ['Published' => 'published']]);
        $this->assertSame(['enum' => ['draft', 'published']], $normalizer->getSchema($fieldDto));

        $fieldDto = $this->createFieldDto(ChoiceField::new('suits')->allowMultipleChoices(), null);
        $fieldDto->setFormTypeOption('choices', Suit::cases());
        $this->assertSame(['type' => 'array', 'items' => ['enum' => ['H', 'S']]], $normalizer->getSchema($fieldDto));
    }

    private function createFieldDto(FieldInterface $field, mixed $value): FieldDto
    {
        $fieldDto = $field->getAsDto();
        $fieldDto->setFieldFqcn($field::class);
        $fieldDto->setValue($value);

        return $fieldDto;
    }

    private function createNormalizer(int $maxStringLength = 100): FieldValueNormalizer
    {
        return new FieldValueNormalizer([new ScalarValueNormalizer(), new DateTimeValueNormalizer(), new ChoiceValueNormalizer(), new MoneyValueNormalizer()], $maxStringLength);
    }
}

enum Suit: string
{
    case Hearts = 'H';
    case Spades = 'S';
}
