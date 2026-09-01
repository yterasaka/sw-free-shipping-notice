<?php declare(strict_types=1);

namespace YukiTFreeShippingNotice\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use YukiTFreeShippingNotice\Struct\FreeShippingProgressStruct;

class FreeShippingProgressResolver
{
    private const CONFIG_PREFIX = 'YukiTFreeShippingNotice.config.';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function resolve(
        Cart $cart,
        SalesChannelContext $context,
    ): ?FreeShippingProgressStruct {
        $salesChannelId = $context->getSalesChannelId();
        $customerGroupId = $context->getCurrentCustomerGroup()->getId();
        $currencyId = $context->getCurrencyId();

        if ($this->isExcluded(
            self::CONFIG_PREFIX . 'excludedCustomerGroupIds',
            $customerGroupId,
            $salesChannelId,
        )) {
            return null;
        }

        if ($this->isExcluded(
            self::CONFIG_PREFIX . 'excludedCurrencyIds',
            $currencyId,
            $salesChannelId,
        )) {
            return null;
        }

        $deliveries = $cart->getDeliveries();

        if ($deliveries->count() === 0) {
            return null;
        }

        // Hide the global notification when any delivery uses an excluded method.
        foreach ($deliveries as $delivery) {
            if ($this->isExcluded(
                self::CONFIG_PREFIX . 'excludedShippingMethodIds',
                $delivery->getShippingMethod()->getId(),
                $salesChannelId,
            )) {
                return null;
            }
        }

        $configuredThreshold = $this->systemConfigService->getFloat(
            self::CONFIG_PREFIX . 'freeShippingThreshold',
            $salesChannelId,
        );

        if (!FloatComparator::greaterThan($configuredThreshold, 0.0)) {
            return null;
        }

        // The configured value is entered in the system default currency.
        $threshold = $configuredThreshold
            * $context->getContext()->getCurrencyFactor();

        $currentValue = $cart->getPrice()->getPositionPrice();

        // Do not display a countdown when all deliveries are already free.
        if (!FloatComparator::greaterThan(
            $deliveries->getShippingCosts()->getTotalPriceAmount(),
            0.0,
        )) {
            return null;
        }

        if (FloatComparator::greaterThanOrEquals(
            $currentValue,
            $threshold,
        )) {
            return null;
        }

        return new FreeShippingProgressStruct(
            threshold: $threshold,
            currentValue: $currentValue,
            remaining: max(0.0, $threshold - $currentValue),
        );
    }

    private function isExcluded(
        string $configKey,
        string $entityId,
        string $salesChannelId,
    ): bool {
        $configuredIds = $this->systemConfigService->get(
            $configKey,
            $salesChannelId,
        );

        if (!\is_array($configuredIds)) {
            return false;
        }

        return \in_array($entityId, $configuredIds, true);
    }
}