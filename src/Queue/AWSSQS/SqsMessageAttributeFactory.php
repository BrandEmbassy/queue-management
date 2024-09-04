<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

/**
 * @final
 */
class SqsMessageAttributeFactory
{
    /**
     * @param array{DataType: 'String'|'Number', StringValue: string}|array{DataType: 'Binary', BinaryValue: string} $value
     */
    public function createFromArray(string $name, array $value): SqsMessageAttribute
    {
        $dataType = SqsMessageAttributeDataType::from($value[SqsMessageAttributeFields::DATA_TYPE->value]);
        $valueKey = $dataType === SqsMessageAttributeDataType::BINARY
            ? SqsMessageAttributeFields::BINARY_VALUE->value
            : SqsMessageAttributeFields::STRING_VALUE->value;

        return new SqsMessageAttribute(
            $name,
            $value[$valueKey],
            $dataType,
        );
    }
}
