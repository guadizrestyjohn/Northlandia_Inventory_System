<?php

namespace Core;

class Display
{
    public static function orderNumber($id): string
    {
        return self::number('ORD-', $id);
    }

    public static function jobOrderNumber($id): string
    {
        return self::number('JO-', $id);
    }

    private static function number(string $prefix, $id): string
    {
        $value = (string) $id;
        if (!ctype_digit($value)) {
            return '';
        }

        return $prefix . str_pad($value, 4, '0', STR_PAD_LEFT);
    }
}
