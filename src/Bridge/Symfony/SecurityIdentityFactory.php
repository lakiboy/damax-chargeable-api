<?php

declare(strict_types=1);

namespace Damax\ChargeableApi\Bridge\Symfony;

use Damax\ChargeableApi\Identity\Identity;
use Damax\ChargeableApi\Identity\IdentityFactory;
use Damax\ChargeableApi\Identity\UserIdentity;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SecurityIdentityFactory implements IdentityFactory
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function create(): Identity
    {
        return new UserIdentity($this->tokenStorage->getToken()->getUsername());
    }
}
