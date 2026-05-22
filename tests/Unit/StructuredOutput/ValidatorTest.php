<?php

declare(strict_types=1);

namespace Token27\NexusAI\Tests\Unit\StructuredOutput;

use Attribute;
use PHPUnit\Framework\TestCase;
use Token27\NexusAI\StructuredOutput\Validation\ValidationRuleInterface;
use Token27\NexusAI\StructuredOutput\Validation\Validator;

final class ValidatorTest extends TestCase
{
    public function testValidObjectReturnsNoViolations(): void
    {
        $dto = new ValidatorValidDto();
        $dto->name = 'Alice';

        $violations = Validator::validate($dto);
        $this->assertSame([], $violations);
    }

    public function testInvalidObjectReturnsViolations(): void
    {
        $dto = new ValidatorInvalidDto();
        $dto->name = '';

        $violations = Validator::validate($dto);
        $this->assertNotEmpty($violations);
    }

    public function testUninitializedPropertyPassesNullToRule(): void
    {
        $dto = new ValidatorRequiredDto();
        // $dto->name is not initialized

        $violations = Validator::validate($dto);
        $this->assertNotEmpty($violations);
    }
}

class ValidatorValidDto
{
    public string $name = 'default';
}

class ValidatorInvalidDto
{
    #[NotEmpty]
    public string $name;
}

class ValidatorRequiredDto
{
    #[NotEmpty]
    public string $name;
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class NotEmpty implements ValidationRuleInterface
{
    public function validate(mixed $value, string $propertyName): ?string
    {
        if ($value === null || $value === '') {
            return "{$propertyName} must not be empty";
        }
        return null;
    }
}
