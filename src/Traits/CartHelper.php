<?php

namespace JaworParkiet\ShoppingCart\Traits;

trait CartHelper
{
    /**
     * Get the Formated number
     *
     * @param $value
     * @param int|null $decimals
     * @param null $decimalPoint
     * @param null $thousandSeparator
     *
     * @return string
     */
    private function numberFormat(
        $value,
        ?int $decimals = null,
        $decimalPoint = null,
        $thousandSeparator = null
    ): string {
        if (is_null($decimals)) {
            $decimals = is_null(config('cart.format.decimals')) ? 2 : config('cart.format.decimals');
        }

        if (is_null($decimalPoint)) {
            $decimalPoint = is_null(config('cart.format.decimal_point')) ? '.' : config('cart.format.decimal_point');
        }

        if (is_null($thousandSeparator)) {
            $thousandSeparator = is_null(config('cart.format.thousand_separator')) ? ',' : config('cart.format.thousand_separator');
        }

        return number_format($value, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * Safely parses a formatted number string into a float.
     *
     * @param string $value
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     *
     * @return float
     */
    private function parseFormattedNumber(
        string $value,
        ?string $decimalPoint = null,
        ?string $thousandSeparator = null
    ): float {
        $decimalPoint = $decimalPoint ?? config('cart.format.decimal_point', '.');
        $thousandSeparator = $thousandSeparator ?? config('cart.format.thousand_separator', ',');

        $unlocalized = str_replace($thousandSeparator, '', $value);
        $normalized = str_replace($decimalPoint, '.', $unlocalized);

        return floatval($normalized);
    }
}
