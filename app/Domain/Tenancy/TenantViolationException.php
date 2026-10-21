<?php

namespace App\Domain\Tenancy;

use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantViolationException extends HttpException
{
    public function __construct(string $message = 'Akses lintas tenant ditolak.')
    {
        parent::__construct(403, $message);
    }
}
