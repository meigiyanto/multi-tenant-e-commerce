<?php

namespace App\Contracts\Stores;

interface DomainOwnershipVerifier
{
    public function verify(string $domain, string $token): bool;
}
