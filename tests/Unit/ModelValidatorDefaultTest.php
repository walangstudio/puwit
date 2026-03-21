<?php

declare(strict_types=1);

namespace Puwit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puwit\Model\ModelValidator;

class ModelValidatorDefaultTest extends TestCase
{
    private ModelValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ModelValidator();
    }

    public function testIntFieldWithStringDefaultFails(): void
    {
        $errors = $this->validator->validate([
            'name'   => 'thing',
            'fields' => [['name' => 'qty', 'type' => 'int', 'default' => 'not-a-number']],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('integer', implode(' ', $errors));
    }

    public function testFloatFieldWithNonNumericDefaultFails(): void
    {
        $errors = $this->validator->validate([
            'name'   => 'thing',
            'fields' => [['name' => 'price', 'type' => 'float', 'default' => 'cheap']],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('numeric', implode(' ', $errors));
    }

    public function testBooleanFieldWithStringDefaultFails(): void
    {
        $errors = $this->validator->validate([
            'name'   => 'thing',
            'fields' => [['name' => 'active', 'type' => 'boolean', 'default' => 'yes']],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('boolean', implode(' ', $errors));
    }

    public function testIntFieldWithValidDefaultPasses(): void
    {
        $errors = $this->validator->validate([
            'name'   => 'thing',
            'fields' => [['name' => 'qty', 'type' => 'int', 'default' => 0]],
        ]);
        $this->assertEmpty($errors);
    }

    public function testBooleanFieldWithZeroDefaultPasses(): void
    {
        $errors = $this->validator->validate([
            'name'   => 'thing',
            'fields' => [['name' => 'active', 'type' => 'boolean', 'default' => 0]],
        ]);
        $this->assertEmpty($errors);
    }
}
