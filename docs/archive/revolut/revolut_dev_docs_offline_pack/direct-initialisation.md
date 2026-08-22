# Merchant Web SDK — Direct initialisation

Source page: https://developer.revolut.com/docs/sdks/merchant-web-sdk/initialisation/direct

## Type signature

```ts
RevolutCheckout.embeddedCheckout(options: {
  publicToken: string
  mode?: 'prod' | 'sandbox'
  locale?: Locale | 'auto'
}): Promise<EmbeddedCheckoutInstance>
```

## Parameters

- `publicToken` — your Merchant API public key (string, required)
- `mode` — API environment `'prod' | 'sandbox'` (optional, default `'prod'`)
- `locale` — language `Locale | 'auto'` (optional)

## Returns

`Promise<EmbeddedCheckoutInstance>` with:

```ts
interface EmbeddedCheckoutInstance {
  destroy: () => void
}
```

- `destroy` — manually destroy the instance and remove the widget from the page

## Example

```js
import RevolutCheckout from '@revolut/checkout'

const { destroy } = await RevolutCheckout.embeddedCheckout({
  publicToken: 'pk_...',
  mode: 'prod',
  locale: 'auto'
})
```
