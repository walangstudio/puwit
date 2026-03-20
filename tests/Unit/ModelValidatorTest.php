<?php

declare(strict_types=1);

namespace Puwit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Puwit\Model\ModelValidator;

class ModelValidatorTest extends TestCase
{
    private ModelValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ModelValidator();
    }

    public function testValidModel(): void
    {
        $errors = $this->validator->validate([
            'name'   => 'product',
            'fields' => [
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'price', 'type' => 'float'],
            ],
        ]);

        $this->assertEmpty($errors);
    }

    public function testMissingName(): void
    {
        $errors = $this->validator->validate(['fields' => [['name' => 'x', 'type' => 'string']]]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('name', $errors[0]);
    }

    public function testReservedFieldName(): void
    {
        $errors = $this->validator->validate([
            'name'   => 'thing',
            'fields' => [['name' => 'id', 'type' => 'int']],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('reserved', $errors[0]);
    }

    public function testInvalidFieldType(): void
    {
        $errors = $this->validator->validate([
            'name'   => 'thing',
            'fields' => [['name' => 'foo', 'type' => 'uuid']],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('uuid', $errors[0]);
    }

    public function testRelationRequiresTarget(): void
    {
        $errors = $this->validator->validate([
            'name'   => 'thing',
            'fields' => [['name' => 'category_id', 'type' => 'relation']],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('relation', $errors[0]);
    }

    public function testDuplicateFieldName(): void
    {
        $errors = $this->validator->validate([
            'name'   => 'thing',
            'fields' => [
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'title', 'type' => 'text'],
            ],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('duplicated', implode(' ', $errors));
    }
}
