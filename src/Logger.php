<?php
// src/Logger.php

namespace App;

use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger
{
    
    public function log($level, string|\Stringable $message, array $context = []): void {
        
    }
}