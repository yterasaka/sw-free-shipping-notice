<?php declare(strict_types=1);

namespace YukiTFreeShippingNotice\Subscriber;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopware\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YukiTFreeShippingNotice\Service\FreeShippingProgressResolver;

class FreeShippingNoticeSubscriber implements EventSubscriberInterface
{
    private const CONFIG_PREFIX = 'YukiTFreeShippingNotice.config.';

    public function __construct(
        private readonly FreeShippingProgressResolver $resolver,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OffcanvasCartPageLoadedEvent::class => 'onOffcanvasLoaded',
            CheckoutCartPageLoadedEvent::class => 'onCartLoaded',
            CheckoutConfirmPageLoadedEvent::class => 'onConfirmLoaded',
        ];
    }

    public function onOffcanvasLoaded(
        OffcanvasCartPageLoadedEvent $event,
    ): void {
        if (!$this->isDisplayEnabled(
            'showInOffcanvas',
            $event->getSalesChannelContext(),
        )) {
            return;
        }

        $this->addProgress(
            $event->getPage(),
            $event->getPage()->getCart(),
            $event->getSalesChannelContext(),
        );
    }

    public function onCartLoaded(
        CheckoutCartPageLoadedEvent $event,
    ): void {
        if (!$this->isDisplayEnabled(
            'showInCart',
            $event->getSalesChannelContext(),
        )) {
            return;
        }

        $this->addProgress(
            $event->getPage(),
            $event->getPage()->getCart(),
            $event->getSalesChannelContext(),
        );
    }

    public function onConfirmLoaded(
        CheckoutConfirmPageLoadedEvent $event,
    ): void {
        if (!$this->isDisplayEnabled(
            'showInCheckout',
            $event->getSalesChannelContext(),
        )) {
            return;
        }

        $this->addProgress(
            $event->getPage(),
            $event->getPage()->getCart(),
            $event->getSalesChannelContext(),
        );
    }

    private function addProgress(
        Page $page,
        Cart $cart,
        SalesChannelContext $context,
    ): void {
        $progress = $this->resolver->resolve($cart, $context);

        if ($progress === null) {
            return;
        }

        $page->addExtension('freeShippingProgress', $progress);
    }

    private function isDisplayEnabled(
        string $configName,
        SalesChannelContext $context,
    ): bool {
        return $this->systemConfigService->get(
                self::CONFIG_PREFIX . $configName,
                $context->getSalesChannelId(),
            ) === true;
    }
}