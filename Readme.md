# TYPO3 Extension `cart_stripe`

This extension provides a stripe payment provider for the `cart` extension.

This extension is currently more a proof of concept and not ready for production!

## Usage

- Install the extension
- Provide the stripe API key (best is to set it in ENV variables)
- Configure shipping handling: Enable "Handle shipping fee as shipping option" to use Stripe shipping options (without VAT). By default (disabled), shipping costs are treated as regular line items (with VAT) to match EXT:cart calculations.
- Copy the TypoScript of the extension + Adopt the payment per country options

## How a payment is confirmed

The return from Stripe is never trusted on its own. `success_url` and `cancel_url`
carry Stripe's `{CHECKOUT_SESSION_ID}` placeholder, and `PaymentController` uses that
id to retrieve the Checkout Session server-side. An order is only set to `paid` when
Stripe reports `payment_status === 'paid'` **and** the session's `metadata.cartSHash`
matches the cart the return URL was called for. The second condition is what stops a
session that was paid for one cart from settling another.

Do not change this to rely on the request arguments alone -- the buyer knows the cart
hash (it is part of the `cancel_url` they land on when aborting) and controls when the
success URL is called.

## Todo

- Testing
- Implement `notifyAction()`: the action is registered in `ext_localconf.php` and a
  template exists, but there is no webhook handler yet. A signed webhook
  (`Webhook::constructEvent`) would also settle orders when the buyer closes the
  browser before being redirected back.
- Narrow the `allowed_classes` allow list in `PaymentController::loadCartByHash()`.
  It currently passes `true` (all classes); restricting it needs the full object graph
  of a serialized `Extcode\Cart\Domain\Model\Cart\Cart`.
