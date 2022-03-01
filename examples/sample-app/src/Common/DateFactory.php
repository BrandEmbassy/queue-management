<?php declare(strict_types = 1);

namespace BE\QueueExample\Common;

use BrandEmbassy\DateTime\DateTimeImmutableFactory;

// https://www.php.net/manual/en/class.datetimeimmutable.php
use DateTimeImmutable;

class DateFactory implements DateTimeImmutableFactory {
    public function getNow(): DateTimeImmutable {
        return new DateTimeImmutable();
    }
}