<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Нарушено бизнес-правило (замок статуса, нет прав, истёк срок…).
 * code — машинный код для приложения, message — текст для человека.
 */
class WorkflowException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
