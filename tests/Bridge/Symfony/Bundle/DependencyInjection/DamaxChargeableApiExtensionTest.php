<?php

declare(strict_types=1);

namespace Damax\ChargeableApi\Tests\Bridge\Symfony\Bundle\DependencyInject;

use Damax\ChargeableApi\Bridge\Symfony\Bundle\DependencyInjection\DamaxChargeableApiExtension;
use Damax\ChargeableApi\Bridge\Symfony\Bundle\Listener\PurchaseListener;
use Damax\ChargeableApi\Bridge\Symfony\Console\Command\WalletBalanceCommand;
use Damax\ChargeableApi\Bridge\Symfony\Console\Command\WalletDepositCommand;
use Damax\ChargeableApi\Bridge\Symfony\Console\Command\WalletWithdrawCommand;
use Damax\ChargeableApi\Bridge\Symfony\EventDispatcher\NotificationStore;
use Damax\ChargeableApi\Bridge\Symfony\HttpFoundation\ProductResolver;
use Damax\ChargeableApi\Bridge\Symfony\Security\TokenIdentityFactory;
use Damax\ChargeableApi\Identity\FixedIdentityFactory;
use Damax\ChargeableApi\Identity\IdentityFactory;
use Damax\ChargeableApi\Processor;
use Damax\ChargeableApi\Product\ChainResolver;
use Damax\ChargeableApi\Product\Product;
use Damax\ChargeableApi\Product\Resolver;
use Damax\ChargeableApi\Store\Store;
use Damax\ChargeableApi\Store\StoreProcessor;
use Damax\ChargeableApi\Wallet\InMemoryWalletFactory;
use Damax\ChargeableApi\Wallet\MongoWalletFactory;
use Damax\ChargeableApi\Wallet\RedisWalletFactory;
use Damax\ChargeableApi\Wallet\WalletFactory;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestMatcher;

class DamaxChargeableApiExtensionTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function it_registers_in_memory_wallet()
    {
        $this->load([
            'wallet' => [
                'john.doe' => 15,
                'jane.doe' => 25,
            ],
        ]);

        $this->assertContainerBuilderHasService(WalletFactory::class, InMemoryWalletFactory::class);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(WalletFactory::class, 0, [
            'john.doe' => 15,
            'jane.doe' => 25,
        ]);
    }

    /**
     * @test
     */
    public function it_registers_redis_wallet()
    {
        $this->load([
            'wallet' => [
                'type' => 'redis',
                'wallet_key' => 'wallet',
                'redis_client_id' => 'snc_redis.default',
            ],
        ]);

        $this->assertContainerBuilderHasService(WalletFactory::class, RedisWalletFactory::class);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(WalletFactory::class, 0, new Reference('snc_redis.default'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(WalletFactory::class, 1, 'wallet');
    }

    /**
     * @test
     */
    public function it_registers_mongo_wallet()
    {
        $this->load([
            'wallet' => [
                'type' => 'mongo',
                'mongo_client_id' => 'mongo_client',
                'db_name' => 'api',
                'collection_name' => 'wallet',
            ],
        ]);

        $this->assertContainerBuilderHasService(WalletFactory::class, MongoWalletFactory::class);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(WalletFactory::class, 0, new Reference('mongo_client'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(WalletFactory::class, 1, 'api');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(WalletFactory::class, 2, 'wallet');
    }

    /**
     * @test
     */
    public function it_registers_custom_wallet_service()
    {
        $this->load([
            'wallet' => [
                'type' => 'service',
                'factory_service_id' => 'wallet_factory_service',
            ],
        ]);

        $this->assertContainerBuilderHasAlias(WalletFactory::class, 'wallet_factory_service');
    }

    /**
     * @test
     */
    public function it_registers_security_identity()
    {
        $this->load();

        $this->assertContainerBuilderHasService(IdentityFactory::class, TokenIdentityFactory::class);
    }

    /**
     * @test
     */
    public function it_registers_fixed_identity()
    {
        $this->load(['identity' => 'john.doe']);

        $this->assertContainerBuilderHasService(IdentityFactory::class, FixedIdentityFactory::class);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(IdentityFactory::class, 0, 'john.doe');
    }

    /**
     * @test
     */
    public function it_registers_custom_identity_service()
    {
        $this->load([
            'identity' => [
                'type' => 'service',
                'factory_service_id' => 'identity_factory_service',
            ],
        ]);

        $this->assertContainerBuilderHasAlias(IdentityFactory::class, 'identity_factory_service');
    }

    /**
     * @test
     */
    public function it_registers_default_product_resolver()
    {
        $this->load(['product' => 5]);

        $this->assertContainerBuilderHasService(ProductResolver::class);
        $this->assertContainerBuilderHasServiceDefinitionWithTag(ProductResolver::class, 'damax.chargeable_api.product_resolver', ['priority' => 0]);

        $calls = $this->container
            ->getDefinition(ProductResolver::class)
            ->getMethodCalls()
        ;

        $this->assertCount(1, $calls);
        $this->assertProductMatcher('API', 5, null, null, null, $calls[0]);
    }

    /**
     * @test
     */
    public function it_registers_product_resolver()
    {
        $this->load([
            'product' => [
                [
                    'name' => 'One',
                    'price' => 10,
                    'matcher' => [
                        'path' => '/one',
                        'ips' => ['192.168.1.100', '192.168.1.101'],
                        'methods' => ['get', 'post'],
                    ],
                ],
                [
                    'name' => 'Two',
                    'price' => 15,
                ],
            ],
        ]);

        $this->assertContainerBuilderHasService(Resolver::class, ChainResolver::class);

        $argument = $this->container
            ->getDefinition(Resolver::class)
            ->getArgument(0)
        ;
        $this->assertInstanceOf(TaggedIteratorArgument::class, $argument);
        $this->assertEquals('damax.chargeable_api.product_resolver', $argument->getTag());

        $calls = $this->container
            ->getDefinition(ProductResolver::class)
            ->getMethodCalls()
        ;

        $this->assertCount(2, $calls);
        $this->assertProductMatcher('One', 10, '/one', ['get', 'post'], ['192.168.1.100', '192.168.1.101'], $calls[0]);
        $this->assertProductMatcher('Two', 15, null, null, null, $calls[1]);
    }

    /**
     * @test
     */
    public function it_registers_store_services()
    {
        $this->load();

        $this->assertContainerBuilderHasService(Store::class, NotificationStore::class);
        $this->assertContainerBuilderHasService(Processor::class, StoreProcessor::class);
    }

    /**
     * @test
     */
    public function it_registers_listener()
    {
        $this->load([
            'listener' => [
                'priority' => 6,
                'matcher' => '^/api/',
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithTag(PurchaseListener::class, 'kernel.event_listener', [
            'event' => 'kernel.request',
            'method' => 'onKernelRequest',
            'priority' => 6,
        ]);

        /** @var Definition $matcher */
        $matcher = $this->container->getDefinition(PurchaseListener::class)->getArgument(0);

        $this->assertEquals(RequestMatcher::class, $matcher->getClass());
        $this->assertEquals('^/api/', $matcher->getArgument(0));
        $this->assertNull($matcher->getArgument(1)); // Host
        $this->assertNull($matcher->getArgument(2)); // Methods
        $this->assertNull($matcher->getArgument(3)); // IPs
    }

    /**
     * @test
     */
    public function it_registers_console_commands()
    {
        $this->load([]);

        $this->assertContainerBuilderHasService(WalletBalanceCommand::class);
        $this->assertContainerBuilderHasService(WalletDepositCommand::class);
        $this->assertContainerBuilderHasService(WalletWithdrawCommand::class);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new DamaxChargeableApiExtension(),
        ];
    }

    private function assertProductMatcher(string $name, int $price, ?string $path, ?array $methods, ?array $ips, array $arguments)
    {
        $this->assertEquals('addProduct', $arguments[0]);

        /** @var Definition[] $definitions */
        $definitions = $arguments[1];

        // Product.
        $this->assertEquals(Product::class, $definitions[0]->getClass());
        $this->assertEquals($name, $definitions[0]->getArgument(0));
        $this->assertEquals($price, $definitions[0]->getArgument(1));

        // Matcher.
        $this->assertEquals(RequestMatcher::class, $definitions[1]->getClass());
        $this->assertNull($definitions[1]->getArgument(1));
        $this->assertSame($path, $definitions[1]->getArgument(0));
        $this->assertSame($methods, $definitions[1]->getArgument(2));
        $this->assertSame($ips, $definitions[1]->getArgument(3));
    }
}
