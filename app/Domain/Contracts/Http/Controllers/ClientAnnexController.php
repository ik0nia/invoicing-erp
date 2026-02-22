<?php

namespace App\Domain\Contracts\Http\Controllers;

class ClientAnnexController extends SupplierAnnexController
{
    protected function isClientMode(): bool
    {
        return true;
    }
}
