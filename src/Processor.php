<?php

declare(strict_types=1);

namespace Damax\ChargeableApi;

interface Processor
{
    /**
     * @throws InsufficientFunds
     */
    public function processRequest($request): void;
}
