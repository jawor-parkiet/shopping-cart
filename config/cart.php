<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default tax rate
    |--------------------------------------------------------------------------
    |
    | This default tax rate will be used when you make a class implement the
    | Taxable interface and use the HasTax trait.
    |
    */

    'tax' => 10,

    /*
    |--------------------------------------------------------------------------
    | Shoppingcart database settings
    |--------------------------------------------------------------------------
    |
    | Here you can set the connection that the shoppingcart should use when
    | storing and restoring a cart.
    |
    */

    'database' => [

        'connection' => null,

        'table' => 'shoppingcart',

    ],

    /*
    |--------------------------------------------------------------------------
    | Destroy the cart on user logout
    |--------------------------------------------------------------------------
    |
    | When this option is set to 'true' the cart will automatically
    | destroy all cart instances when the user logs out.
    |
    */

    'destroy_on_logout' => false,

    /*
    |--------------------------------------------------------------------------
    | Default number format
    |--------------------------------------------------------------------------
    |
    | This defaults will be used for the formated numbers if you don't
    | set them in the method call.
    |
    */

    'format' => [

        // General rule with decimal points is that if it's a unit price then it should be 2 decimals,
        // as that's what is displayed to the user. Once it's multiplied by quantity, it should be 4 decimals to
        // account for that multiplication of qty, and the total tax, subtotal inc tax, and total should all reflect that,
        // and only round to show the user at the absolute final stage.

        'price_ex_tax_decimals' => 2, // base unit price shown to user ex gst should be 2 by default.
        'price_inc_tax_decimals' => 2, // base unit price shown to user ex gst should be 2 by default.
        'fee_total_tax_decimals' => 4, // fee total tax should be 4 decimals.
        'tax_decimals' => 4, // unit price tax can be 4 decimals. Eg. $2.81 * 1.1 = $3.091 inc tax = $0.281 tax.
        'tax_total_decimals' => 4, // total tax can be 4 decimals. Eg. $0.281 * 12 qty = $3.372 total tax.
        'subtotal_ex_tax_decimals' => 4, // subtotal ex tax after qty should be 4 decimals by default, then round to show user after qty.
        'subtotal_inc_tax_decimals' => 4, // subtotal inc tax after qty should be 4 decimals by default, then round to show user after qty
        'total_decimals' => 4, // total after tax should be 4 decimals by default, then round to show user after qty.

        // @deprecated
        'decimals' => 4,

        'decimal_point' => '.',

        'thousand_seperator' => ''

    ],

    /*
    |--------------------------------------------------------------------------
    | Allows you to choose if the discounts applied to fees
    |--------------------------------------------------------------------------
    |
    */
    'discountOnFees' => false,

];
