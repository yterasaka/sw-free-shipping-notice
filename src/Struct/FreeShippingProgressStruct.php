<?php declare(strict_types=1);

namespace YukiTFreeShippingNotice\Struct;

use Shopware\Core\Framework\Struct\Struct;

class FreeShippingProgressStruct extends Struct
{
    public function __construct(
        protected float $threshold,
        protected float $currentValue,
        protected float $remaining,
    ) {
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    public function getCurrentValue(): float
    {
        return $this->currentValue;
    }

    public function getRemaining(): float
    {
        return $this->remaining;
    }

    public function getApiAlias(): string
    {
        return 'free_shipping_progress';
    }
}