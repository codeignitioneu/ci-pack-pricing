# Pack & Unit Pricing for WooCommerce

**By [Code Ignition SRL](https://github.com/codeignitioneu)**

Sell WooCommerce products by the **piece** or by the **pack/set**, show the customer both the **price per piece** and the **price per pack**, and enforce a **minimum order quantity** – without touching cart math.

## How it works

- The WooCommerce price of a product is the price of **one sale unit** (one piece, or one complete pack). Cart, checkout, coupons, invoices and product feeds (Meta, Google) all use that real price.
- The plugin only adds information and quantity rules:
  - **Sale unit** – `piece` or `set`
  - **Pieces per set** – used to show the price per piece
  - **Minimum quantity** – minimum number of sale units per order

| Product meta | Values |
|---|---|
| `_ci_sale_unit` | `piece` \| `set` |
| `_ci_pack_qty` | integer ≥ 2 |
| `_ci_min_qty` | integer ≥ 1 (empty = no minimum) |

The fields appear in *Product data → General*, under the price, and can be set through the WooCommerce REST API (`meta_data`).

## Front-end

- Product page & shop: `2,50 lei / buc` + `Set 12 buc: 30,00 lei` (which price is shown large: *WooCommerce → Settings → Products → Pack & Unit Pricing*).
- Notice box on the product page: pack contents and minimum order (with minimum total).
- Quantity input starts at the minimum; add-to-cart, cart update, cart blocks (Store API) and checkout validate the minimum.
- Cart and order line items show the pack size and the minimum.

## Updates

The plugin updates itself from this repository's GitHub releases (via [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker), MIT). Each release has a `ci-pack-pricing.zip` asset.

## Requirements

WordPress 6.0+, WooCommerce 8.2+, PHP 7.4+.

## License

GPL-2.0-or-later. Bundled Plugin Update Checker library: MIT.
