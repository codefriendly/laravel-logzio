<?php

namespace Laravel\Logzio;

use LogicException;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\Curl\Util;
use Monolog\Logger;

/**
 * @see    https://support.logz.io/hc/en-us/categories/201158705-Log-Shipping
 * @see    https://app.logz.io/#/dashboard/data-sources/Bulk-HTTPS
 */
final class LogzioHandler extends AbstractProcessingHandler
{
    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @param string $token Log token supplied by Logz.io.
     * @param string $type Host name supplied by Logz.io.
     * @param bool $useSSL Whether or not SSL encryption should be used.
     * @param int|string $level The minimum logging level to trigger this handler.
     * @param bool $bubble Whether or not messages that are handled should bubble up the stack.
     * @throws \LogicException If curl extension is not available.
     */
    public function __construct(
        string $token,
        string $type = 'http-bulk',
        bool $useSSL = true,
        $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        if (!extension_loaded('curl')) {
            throw new LogicException('The curl extension is needed to use the LogzIoHandler');
        }

        $this->token = $token;
        $this->type = $type;
        $this->endpoint = $useSSL
            ? 'https://listener.logz.io:8071/'
            : 'http://listener.logz.io:8070/';

        $this->endpoint .= '?' . http_build_query([
            'token' => $this->token,
            'type' => $this->type,
        ]);

        parent::__construct($level, $bubble);
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record): void
    {
        $this->send($record['formatted']);
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records)
    {
        $level = $this->level;
        $records = array_filter(
            $records,
            function (array $record) use ($level): bool {
                return ($record['level'] >= $level);
            }
        );
        if ($records) {
            $this->send(
                $this->getFormatter()
                    ->formatBatch($records)
            );
        }
    }

    /**
     * Send logging data to server
     *
     * @param mixed $data
     * @return void
     */
    protected function send($data)
    {
        $headers = ['Content-Type: application/json'];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        Util::execute($ch, 3);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LogzioFormatter();
    }
}
