<?php declare(strict_types = 1);

namespace BE\QueueManagement\Jobs\FailResolving\DelayRules;

use Exception;

final class DelayRuleException extends Exception
{
    public static function byNotAbleToCalculateDelay(): self
    {
        return new self('Unable to resolve delay, probably missing validation for definition');
    }


    public static function byMissingDefinitionForZeroAttempts(): self
    {
        return new self('Missing definition for 0 attempts');
    }


    public static function byIncorrectDefinitionOrder(): self
    {
        return new self('Delays definition keys must be sorted descending');
    }
}
