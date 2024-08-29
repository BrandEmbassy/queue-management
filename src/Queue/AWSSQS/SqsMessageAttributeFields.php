<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

enum SqsMessageAttributeFields: string
{
    case DATA_TYPE = 'DataType';

    case BINARY_VALUE = 'BinaryValue';

    case STRING_VALUE = 'StringValue';
}
