<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

use function is_numeric;
use function str_contains;
use function strlen;

/**
 * Represent SQS Message Attribute
 *
 * @final
 */
class SqsMessageAttribute
{
    public function __construct(
        private readonly string $name,
        private readonly string $value,
        private readonly SqsMessageAttributeDataType $dataType,
    ) {
    }


    public function getName(): string
    {
        return $this->name;
    }


    public function getValue(): string|int|float
    {
        if ($this->dataType === SqsMessageAttributeDataType::NUMBER) {
            if (is_numeric($this->value)) {
                if (str_contains($this->value, '.')) {
                    return (float)$this->value;
                }

                return (int)$this->value;
            }
        }

        return $this->value;
    }


    public function getDataType(): SqsMessageAttributeDataType
    {
        return $this->dataType;
    }


    public function getSizeInBytes(): int
    {
        return strlen($this->dataType->value) + strlen($this->value);
    }


    /**
     * @return array{DataType: 'String'|'Number', StringValue: string}|array{DataType: 'Binary', BinaryValue: string}
     */
    public function toArray(): array
    {
        if ($this->dataType === SqsMessageAttributeDataType::BINARY) {
            return [
                SqsMessageAttributeFields::DATA_TYPE->value => $this->dataType->value,
                SqsMessageAttributeFields::BINARY_VALUE->value => $this->value,
            ];
        }

        return [
            SqsMessageAttributeFields::DATA_TYPE->value => $this->dataType->value,
            SqsMessageAttributeFields::STRING_VALUE->value => $this->value,
        ];
    }
}
