<?php declare(strict_types = 1);

namespace BE\QueueManagement\Queue\RabbitMQ;

use function stream_context_create;

final class SslContextFactory
{
    /**
     * @var mixed[]
     */
    private $sslOptions;


    public function __construct(array $sslOptions)
    {
        $this->sslOptions = $sslOptions;
    }


    /**
     * @return resource
     */
    public function create()
    {
        $sslContext = stream_context_create();
        foreach ($this->sslOptions as $key => $value) {
            stream_context_set_option($sslContext, 'ssl', $key, $value);
        }

        return $sslContext;
    }
}
