<?php declare(strict_types=1);

namespace YukiTFreeShippingNotice\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use YukiTFreeShippingNotice\Service\FreeShippingProgressResolver;
use YukiTFreeShippingNotice\Struct\FreeShippingProgressStruct;

#[CoversClass(FreeShippingProgressResolver::class)]
class FreeShippingProgressResolverTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sales-channel-id';

    private const CUSTOMER_GROUP_ID = 'customer-group-id';

    private const CURRENCY_ID = 'currency-id';

    private const SHIPPING_METHOD_ID = 'shipping-method-id';

    public function testReturnsRemainingAmountInCurrentCurrency(): void
    {
        $resolver = $this->createResolver([
            'freeShippingThreshold' => 100.0,
        ]);

        $progress = $resolver->resolve(
            $this->createCart(
                positionPrice: 55.0,
                shippingCosts: [5.0],
            ),
            $this->createSalesChannelContext(currencyFactor: 1.1),
        );

        static::assertInstanceOf(FreeShippingProgressStruct::class, $progress);
        static::assertEqualsWithDelta(110.0, $progress->getThreshold(), 0.00001);
        static::assertEqualsWithDelta(55.0, $progress->getCurrentValue(), 0.00001);
        static::assertEqualsWithDelta(55.0, $progress->getRemaining(), 0.00001);
    }

    public function testReturnsNullWhenThresholdIsZero(): void
    {
        $resolver = $this->createResolver([
            'freeShippingThreshold' => 0.0,
        ]);

        $progress = $resolver->resolve(
            $this->createCart(
                positionPrice: 50.0,
                shippingCosts: [5.0],
            ),
            $this->createSalesChannelContext(),
        );

        static::assertNull($progress);
    }

    public function testReturnsNullWhenCartHasReachedThreshold(): void
    {
        $resolver = $this->createResolver([
            'freeShippingThreshold' => 100.0,
        ]);

        $progress = $resolver->resolve(
            $this->createCart(
                positionPrice: 100.0,
                shippingCosts: [5.0],
            ),
            $this->createSalesChannelContext(),
        );

        static::assertNull($progress);
    }

    public function testReturnsNullWhenAllDeliveriesAreFree(): void
    {
        $resolver = $this->createResolver([
            'freeShippingThreshold' => 100.0,
        ]);

        $progress = $resolver->resolve(
            $this->createCart(
                positionPrice: 50.0,
                shippingCosts: [0.0],
            ),
            $this->createSalesChannelContext(),
        );

        static::assertNull($progress);
    }

    public function testReturnsNullWhenNoDeliveryExists(): void
    {
        $resolver = $this->createResolver([
            'freeShippingThreshold' => 100.0,
        ]);

        $cart = new Cart('test-token');

        $progress = $resolver->resolve(
            $cart,
            $this->createSalesChannelContext(),
        );

        static::assertNull($progress);
    }

    #[DataProvider('exclusionProvider')]
    public function testReturnsNullForExcludedEntities(
        array $config,
        string $shippingMethodId,
        string $customerGroupId,
        string $currencyId,
    ): void {
        $resolver = $this->createResolver($config);

        $progress = $resolver->resolve(
            $this->createCart(
                positionPrice: 50.0,
                shippingCosts: [5.0],
                shippingMethodIds: [$shippingMethodId],
            ),
            $this->createSalesChannelContext(
                customerGroupId: $customerGroupId,
                currencyId: $currencyId,
            ),
        );

        static::assertNull($progress);
    }

    /**
     * @return iterable<string, array{
     *     config: array<string, mixed>,
     *     shippingMethodId: string,
     *     customerGroupId: string,
     *     currencyId: string
     * }>
     */
    public static function exclusionProvider(): iterable
    {
        yield 'excluded shipping method' => [
            'config' => [
                'freeShippingThreshold' => 100.0,
                'excludedShippingMethodIds' => [self::SHIPPING_METHOD_ID],
            ],
            'shippingMethodId' => self::SHIPPING_METHOD_ID,
            'customerGroupId' => self::CUSTOMER_GROUP_ID,
            'currencyId' => self::CURRENCY_ID,
        ];

        yield 'excluded customer group' => [
            'config' => [
                'freeShippingThreshold' => 100.0,
                'excludedCustomerGroupIds' => [self::CUSTOMER_GROUP_ID],
            ],
            'shippingMethodId' => self::SHIPPING_METHOD_ID,
            'customerGroupId' => self::CUSTOMER_GROUP_ID,
            'currencyId' => self::CURRENCY_ID,
        ];

        yield 'excluded currency' => [
            'config' => [
                'freeShippingThreshold' => 100.0,
                'excludedCurrencyIds' => [self::CURRENCY_ID],
            ],
            'shippingMethodId' => self::SHIPPING_METHOD_ID,
            'customerGroupId' => self::CUSTOMER_GROUP_ID,
            'currencyId' => self::CURRENCY_ID,
        ];
    }

    public function testReturnsNullWhenOneOfMultipleDeliveriesUsesExcludedMethod(): void
    {
        $resolver = $this->createResolver([
            'freeShippingThreshold' => 100.0,
            'excludedShippingMethodIds' => ['excluded-shipping-method-id'],
        ]);

        $progress = $resolver->resolve(
            $this->createCart(
                positionPrice: 50.0,
                shippingCosts: [5.0, 10.0],
                shippingMethodIds: [
                    self::SHIPPING_METHOD_ID,
                    'excluded-shipping-method-id',
                ],
            ),
            $this->createSalesChannelContext(),
        );

        static::assertNull($progress);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createResolver(
        array $config,
    ): FreeShippingProgressResolver {
        $systemConfigService = $this->createMock(
            SystemConfigService::class,
        );

        $systemConfigService
            ->method('getFloat')
            ->willReturnCallback(
                static function (
                    string $key,
                    ?string $salesChannelId,
                ) use ($config): float {
                    static::assertSame(
                        'YukiTFreeShippingNotice.config.freeShippingThreshold',
                        $key,
                    );
                    static::assertSame(self::SALES_CHANNEL_ID, $salesChannelId);

                    return (float) ($config['freeShippingThreshold'] ?? 0.0);
                },
            );

        $systemConfigService
            ->method('get')
            ->willReturnCallback(
                static function (
                    string $key,
                    ?string $salesChannelId,
                ) use ($config): mixed {
                    static::assertSame(self::SALES_CHANNEL_ID, $salesChannelId);

                    $configName = str_replace(
                        'YukiTFreeShippingNotice.config.',
                        '',
                        $key,
                    );

                    return $config[$configName] ?? null;
                },
            );

        return new FreeShippingProgressResolver($systemConfigService);
    }

    /**
     * @param list<float> $shippingCosts
     * @param list<string>|null $shippingMethodIds
     */
    private function createCart(
        float $positionPrice,
        array $shippingCosts,
        ?array $shippingMethodIds = null,
    ): Cart {
        $cart = new Cart('test-token');

        $cart->setPrice(new CartPrice(
            netPrice: $positionPrice,
            totalPrice: $positionPrice,
            positionPrice: $positionPrice,
            calculatedTaxes: new CalculatedTaxCollection(),
            taxRules: new TaxRuleCollection(),
            taxStatus: CartPrice::TAX_STATE_GROSS,
        ));

        $deliveries = new DeliveryCollection();

        foreach ($shippingCosts as $index => $shippingCost) {
            $shippingMethod = new ShippingMethodEntity();
            $shippingMethod->setId(
                $shippingMethodIds[$index] ?? self::SHIPPING_METHOD_ID,
            );

            $delivery = $this->createMock(Delivery::class);
            $delivery
                ->method('getShippingMethod')
                ->willReturn($shippingMethod);
            $delivery
                ->method('getShippingCosts')
                ->willReturn(new CalculatedPrice(
                    unitPrice: $shippingCost,
                    totalPrice: $shippingCost,
                    calculatedTaxes: new CalculatedTaxCollection(),
                    taxRules: new TaxRuleCollection(),
                ));

            $deliveries->add($delivery);
        }

        $cart->setDeliveries($deliveries);

        return $cart;
    }

    private function createSalesChannelContext(
        string $customerGroupId = self::CUSTOMER_GROUP_ID,
        string $currencyId = self::CURRENCY_ID,
        float $currencyFactor = 1.0,
    ): SalesChannelContext {
        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setId($customerGroupId);

        $context = $this->createMock(Context::class);
        $context
            ->method('getCurrencyFactor')
            ->willReturn($currencyFactor);

        $salesChannelContext = $this->createMock(
            SalesChannelContext::class,
        );

        $salesChannelContext
            ->method('getSalesChannelId')
            ->willReturn(self::SALES_CHANNEL_ID);
        $salesChannelContext
            ->method('getCurrentCustomerGroup')
            ->willReturn($customerGroup);
        $salesChannelContext
            ->method('getCurrencyId')
            ->willReturn($currencyId);
        $salesChannelContext
            ->method('getContext')
            ->willReturn($context);

        return $salesChannelContext;
    }
}