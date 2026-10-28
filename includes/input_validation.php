<?php
/** Request boundaries and limits matching the existing SQL columns. */
const PACKAGE_PRICE_MAX = '99999999.99'; // DECIMAL(10,2)
const INVENTORY_QUANTITY_MAX = 2147483647; // signed INT

function request_text(array $source, string $key, string $default = ''): string {
    $value = $source[$key] ?? $default;
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        http_response_code(400);
        exit('Invalid field format. Please return to the form and enter a single value.');
    }
    return (string)$value;
}

function text_length(string $value): int {
    return preg_match_all('/./us', $value, $matches) === false ? strlen($value) : count($matches[0]);
}

/** Exact decimal string; no float conversion, rounding, exponent or coercion. */
function validated_package_price(mixed $value): ?string {
    if (!is_string($value) && !is_int($value)) return null;
    $value = trim((string)$value);
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)) return null;
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    return (ltrim($whole, '0') ?: '0') . '.' . str_pad($fraction, 2, '0');
}

function validated_inventory_quantity(mixed $value): ?int {
    if (!is_string($value) && !is_int($value)) return null;
    $value = trim((string)$value);
    if (!preg_match('/^\d{1,10}$/D', $value)) return null;
    $number = (int)$value;
    return $number <= INVENTORY_QUANTITY_MAX ? $number : null;
}

function package_price_label(mixed $price): string {
    return (float)$price === 0.0 ? 'Contact for pricing' : '₱' . number_format((float)$price, 2);
}

function csrf_field(): string {
    return '<input type="hidden" name="token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '" />';
}
