<?php

namespace App\Services\Stores;

use App\Contracts\Stores\DomainOwnershipVerifier;

class DnsTxtRecordVerifier implements DomainOwnershipVerifier
{
    public function verify(string $domain, string $token): bool
    {
        $records = @dns_get_record('_store-verification.'.$domain, DNS_TXT);

        if ($records === false) {
            return false;
        }

        foreach ($records as $record) {
            if (isset($record['txt']) && hash_equals($token, (string) $record['txt'])) {
                return true;
            }
        }

        return false;
    }
}
