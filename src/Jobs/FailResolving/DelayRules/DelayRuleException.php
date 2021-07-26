<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\DelayRules;

use Exception;

final class DelayRuleException extends Exception
{
    public static function byMissingDefinitionForZero(): self
    {
        return new self('Missing definition for 0 attempts');
    }


    public static function byIncorrectDefinitionOrder(): self
    {
        return new self('Delays definition keys must be sorted descending');
    }
}
