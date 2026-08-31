<?php declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

$loader = (new TestBootstrapper())
    ->addCallingPlugin()
    ->addActivePlugins('YukiTFreeShippingNotice')
    ->setForceInstallPlugins(true)
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('YukiTFreeShippingNotice\\Tests\\', __DIR__);
