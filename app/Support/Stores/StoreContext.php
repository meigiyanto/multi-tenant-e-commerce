<?php

namespace App\Support\Stores;

use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\Team;

class StoreContext
{
    private ?Store $store = null;

    private ?StoreDomain $domain = null;

    public function set(Store $store, StoreDomain $domain): void
    {
        $this->store = $store;
        $this->domain = $domain;
    }

    public function store(): ?Store
    {
        return $this->store;
    }

    public function domain(): ?StoreDomain
    {
        return $this->domain;
    }

    public function team(): ?Team
    {
        return $this->store?->team;
    }

    public function isResolved(): bool
    {
        return $this->store !== null && $this->domain !== null;
    }
}
