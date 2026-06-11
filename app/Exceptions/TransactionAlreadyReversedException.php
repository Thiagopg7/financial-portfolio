<?php

namespace App\Exceptions;

use Exception;

class TransactionAlreadyReversedException extends Exception
{
    protected $message = 'Esta operação já foi revertida.';
}
