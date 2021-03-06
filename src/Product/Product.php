<?php

declare(strict_types=1);

namespace Damax\ChargeableApi\Product;

use Damax\ChargeableApi\Credit;

final class Product
{
    private $name;
    private $price;

    public function __construct(string $name, int $price)
    {
        $this->name = $name;
        $this->price = $price;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): Credit
    {
        return Credit::fromInteger($this->price);
    }
}
