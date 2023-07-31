<?php

declare(strict_types=1);

namespace modules\Logger;

require_once('vendor/autoload.php');

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

use \Logger;

class NewLogger
{
    use LoggerTrait;

    private Logger $oldLogger;

    private $LOGLEVEL_MAPPING = [
        LogLevel::EMERGENCY => LOGGER_EMERGENCY,
        LogLevel::ALERT => LOGGER_ALERT,
        LogLevel::CRITICAL => LOGGER_CRITICAL,
        LogLevel::ERROR => LOGGER_ERROR,
        LogLevel::WARNING => LOGGER_WARNING,
        LogLevel::NOTICE => LOGGER_NOTICE,
        LogLevel::INFO => LOGGER_INFO,
        LogLevel::DEBUG => LOGGER_DEBUG,
    ];

    public function setOldLogger(Logger $oldLogger): NewLogger
    {
        $this->oldLogger = $oldLogger;
        return $this;
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->oldLogger->log($message, $this->LOGLEVEL_MAPPING[$level]);
    }
}
