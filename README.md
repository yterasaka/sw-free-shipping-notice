# Free Shipping Notice

Free Shipping Notice displays the amount customers still need to add to their cart to qualify for free delivery.

The notification can be shown in the:

- Offcanvas cart
- Cart page
- Checkout confirmation page

## Requirements

- Shopware 6.7
- Shopware Storefront

## Installation

Install and activate the plugin through **Extensions > My extensions**, or use the command line:

```bash
bin/console plugin:refresh
bin/console plugin:install --activate YukiTFreeShippingNotice
bin/console cache:clear
bin/console theme:compile
```

## Configuration

Open the plugin configuration under **Extensions > My extensions** and select the sales channel you want to configure.

### Free shipping threshold

Enter the cart value required for free delivery in the system default currency. A value of `0` disables the notification.

This setting only controls the notification. It does not change the actual shipping costs. Configure the same free-delivery threshold in the corresponding Shopware shipping method settings.

For other currencies, the configured threshold is converted using the currency factor configured in Shopware.

### Display locations

Enable the notification separately for:

- The offcanvas cart
- The cart page
- The checkout confirmation page

All display locations are disabled by default.

### Exclusions

The notification can be hidden for selected:

- Shipping methods
- Customer groups
- Currencies

The exclusions use an opt-out approach. If no entries are selected, the notification is available for all entities of that type. If a cart contains multiple deliveries and any delivery uses an excluded shipping method, the notification is hidden.

## Customizing the storefront text

The notification uses the following snippet:

```text
yukiTFreeShippingNotice.remainingUntilFreeDelivery
```

Default English text:

```text
Only %freeShipping% until free delivery.
```

The `%freeShipping%` placeholder is replaced with the localized and formatted remaining amount.

Edit or translate the text under **Settings > Snippets**. English and German defaults are included. Additional languages can be added through Shopware's snippet management.

## Display behavior

The notification is displayed only when all of the following conditions are met:

- The selected display location is enabled.
- The configured threshold is greater than zero.
- The cart contains at least one delivery.
- The current shipping method, customer group, and currency are not excluded.
- The cart value is below the configured threshold.
- The calculated shipping costs are greater than zero.

The remaining amount is calculated from the cart position price:

```text
remaining amount = converted threshold - cart position price
```

The plugin deliberately does not derive its threshold from Shopware's shipping price matrix. This keeps the displayed target predictable even when a shop uses tiered, quantity-based, weight-based, or rule-based shipping prices.

## Development

Run the unit tests from the Shopware project root:

```bash
vendor/bin/phpunit -c custom/plugins/YukiTFreeShippingNotice/phpunit.xml
```

## License

MIT