<?php

namespace Core;

class Validation
{
    public static function customer(array $data): ?string
    {
        return trim((string) ($data['customer_name'] ?? '')) === ''
            ? 'Customer name is required.'
            : null;
    }

    public static function order(array $data, bool $updating, $customers, ?string $currentStatus = null): ?string
    {
        foreach (['customer_id', 'order_date', 'product_name', 'quantity'] as $required) {
            if (($data[$required] ?? '') === '') return 'Please complete the required fields.';
        }
        if (!ctype_digit((string) $data['customer_id']) || !$customers->findByID($data['customer_id'])) {
            return 'Select a valid customer.';
        }
        $orderTotal = (string) ($data['order_total'] ?? '');
        $downpayment = (string) ($data['downpayment'] ?? '0');
        if (!self::isCurrencyAmount($orderTotal) || self::currencyCents($orderTotal) <= 0) {
            return 'Order Total must be a valid amount greater than zero.';
        }
        if (!self::isCurrencyAmount($downpayment)) {
            return 'Downpayment must be a valid non-negative amount.';
        }
        if (self::currencyCents($downpayment) > self::currencyCents($orderTotal)) {
            return 'Downpayment cannot exceed the order total.';
        }
        if (!is_numeric($data['quantity']) || !is_finite((float) $data['quantity']) || (float) $data['quantity'] <= 0) {
            return 'Quantity must be a valid number greater than 0.';
        }
        $date = \DateTime::createFromFormat('Y-m-d', $data['order_date']);
        if (!$date || $date->format('Y-m-d') !== $data['order_date']) return 'Enter a valid order date.';
        if (!$updating && $data['order_date'] < date('Y-m-d')) return 'Order date cannot be earlier than today.';
        if ($updating) {
            $status = $data['status'] ?? '';
            $allowedStatuses = in_array($currentStatus, ['Pending', 'Cancelled'], true)
                ? ['Pending', 'Cancelled']
                : [$currentStatus];
            if (!in_array($status, $allowedStatuses, true)) return 'Invalid order status.';
        }
        if ($updating && ($data['order_date'] < '2000-01-01' || $data['order_date'] > '2100-12-31')) return 'Invalid order date.';
        return null;
    }

    private static function isCurrencyAmount(string $amount): bool
    {
        return (bool) preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/', $amount);
    }

    private static function currencyCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function inventory(array $data, $categories): ?string
    {
        if (($data['item_name'] ?? '') === '' || ($data['category_id'] ?? '') === '' || ($data['unit'] ?? '') === '') {
            return 'Please fill in all required fields.';
        }
        if (!ctype_digit((string) $data['category_id']) || !$categories->findByID($data['category_id'])) {
            return 'Select a valid category.';
        }
        return null;
    }

    public static function productionEndDate(string $endDate, ?string $startDate): ?string
    {
        $date = \DateTime::createFromFormat('Y-m-d', $endDate);
        if (!$date || $date->format('Y-m-d') !== $endDate) return 'Enter a valid production end date.';
        if ($startDate && $endDate < $startDate) return 'Production end date cannot be earlier than the start date.';
        return null;
    }
}
