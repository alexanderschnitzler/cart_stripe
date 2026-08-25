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
carry Stripe's `{CHECKOUT_SESSION_ID}` placeholder in the `cartStripeSessionId`
parameter, and `PaymentController` uses that id to retrieve the Checkout Session
server-side. An order is only set to `paid` when Stripe reports the session as
`complete` with a `payment_status` of `paid` (or `no_payment_required`, which is what
a coupon covering the full amount produces) **and** the session's `metadata.cartSHash`
matches the cart the return URL was called for. The last condition is what stops a
session that was paid for one cart from settling another.

Do not change this to rely on the request arguments alone -- the buyer knows the cart
hash (it is part of the `cancel_url` they land on when aborting) and controls when the
success URL is called.

Two things this depends on, both easy to break by accident:

- `cartStripeSessionId` is registered in `FE/cacheHash/excludedParameters` in
  `ext_localconf.php`. The placeholder has to reach Stripe unencoded, so the parameter
  is appended after the UriBuilder has calculated the cHash and is therefore not
  covered by it. Without the exclusion `PageArgumentValidator` answers every return
  from Stripe with a 404.
- Payment methods with delayed notification (SEPA direct debit, most bank redirects)
  report `payment_status = 'unpaid'` at redirect time and only settle later. Until
  `notifyAction()` exists there is nothing to pick that up, so restrict the Checkout
  Session to immediate payment methods.

## Restoring the serialized cart

`PaymentController::loadCartByHash()` restores the cart for a return URL from the
`serialized_cart` column. Where `TYPO3\CMS\Core\Serializer\PolymorphicDeserializer`
exists (TYPO3 v13.4.23 and above) the class names in the payload are validated against
an allow list before anything is instantiated; on older releases the payload is
restored as before.

The allow list holds interfaces rather than concrete classes because EXT:cart resolves
products, services and tax classes through factories. It does not cover the open
`additionals` container, whose contents come from other cart extensions -- a setup that
stores objects there will have to extend the list.

## Todo

- Testing
- Implement `notifyAction()`: the action is registered in `ext_localconf.php` and a
  template exists, but there is no webhook handler yet. A signed webhook
  (`Webhook::constructEvent`) would also settle orders when the buyer closes the
  browser before being redirected back.
