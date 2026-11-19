# Changelog

## 2.1.0 – 2026-11-19
- Fix: admin fields moved to their own group in *Product data → General* (they were nested inside the price group and overlapped).
- Fix: fixed width for the *Unitate* column in the products list (it was squeezed and overlapped the next column); hidden on small screens.
- Removed the 1.x meta-key fallback (`_domix_*`); all products use `_ci_*` keys.

## 2.0.0 – 2026-11-19
- Renamed to **Pack & Unit Pricing for WooCommerce** (Code Ignition SRL), slug `ci-pack-pricing`.
- New meta keys `_ci_sale_unit`, `_ci_pack_qty`, `_ci_min_qty` (1.x keys are still read as fallback).
- Automatic updates from GitHub releases.

## 1.1.0
- Minimum order quantity (field, product notice, quantity input, cart/checkout/Store API validation).

## 1.0.0
- Sale unit piece/set, pieces per set, price per piece + price per set display, cart/order line info.
