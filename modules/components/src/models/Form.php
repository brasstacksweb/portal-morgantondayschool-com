<?php

namespace modules\components\models;

use craft\base\Model;

class Form extends Model
{
    public string $actionText = 'Submit';

    public function getAttributeType(string $name): string
    {
        return $this->attributeTypes()[$name] ?? 'text';
    }

    public function attributePlaceholders(): array
    {
        return [];
    }

    public function getAttributePlaceholder(string $name): string
    {
        return $this->attributePlaceholders()[$name] ?? '';
    }

    public function attributeSizes(): array
    {
        return [];
    }

    public function getAttributeSize(string $name): string
    {
        return $this->attributeSizes()[$name] ?? 'full';
    }

    public function attributeOptions(): array
    {
        return [];
    }

    public function getAttributeOptions(string $name): array
    {
        return $this->attributeOptions()[$name] ?? [];
    }

    public function attributePatterns(): array
    {
        return [];
    }

    public function getAttributePattern(string $name): string
    {
        return $this->attributePatterns()[$name] ?? '';
    }

    public function attributeConditionals(): array
    {
        return [];
    }

    public function getAttributeInputConditional(string $name): array
    {
        if (!method_exists($this, 'attributeConditionals')) {
            return [];
        }

        return $this->attributeConditionals()[$name] ?? [];
    }

    public function getSubmitText(): string
    {
        return $this->actionText ?: 'Submit';
    }
}
