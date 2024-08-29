<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\AWSSQS;

enum SqsMessageAttributeDataType: string
{
    case STRING = 'String';

    case NUMBER = 'Number';

    case BINARY = 'Binary';
}
