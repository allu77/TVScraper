<?php

declare(strict_types=1);

namespace modules\Logger;

require_once('vendor/autoload.php');
require_once('Logger.php');

use Psr\Log\LogLevel;
use \Logger;

trait LoggerApplicationTrait
{
    protected NewLogger $logger;

    public function setLogger(Logger $logger): void
    {
        $this->logger = new NewLogger();
        $this->logger->setOldLogger($logger);
    }

    public function setLogFile(string $logFile, $severity = LOGGER_DEBUG): void
    {
        $this->logger = new NewLogger();
        $this->logger->setOldLogger(new Logger($logFile, $severity));
    }

    protected function log(string $msg, $severity = LogLevel::DEBUG)
    {
        if ($this->logger) $this->logger->log($severity, $msg);
    }

    protected function error($msg)
    {
        if ($this->logger) $this->logger->error($msg);
        return FALSE;
    }
}
