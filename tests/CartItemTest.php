<?php

namespace JaworParkiet\Tests\ShoppingCart;

use Orchestra\Testbench\TestCase;
use JaworParkiet\ShoppingCart\CartItem;
use JaworParkiet\ShoppingCart\ShoppingCartServiceProvider;

class CartItemTest extends TestCase
{
    /**
     * Set the package service provider.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [ShoppingCartServiceProvider::class];
    }

    public function test_it_can_be_cast_to_an_array(): void
    {
        $cartItem = new CartItem(1, 'Some item', 10.00, ['size' => 'XL', 'color' => 'red']);
        $cartItem->setQuantity(2);

        $this->assertEquals([
            'id' => 1,
            'name' => 'Some item',
            'price' => 10.00,
            'rowId' => '07d5da5550494c62daf9993cf954303f',
            'qty' => 2,
            'options' => [
                'size' => 'XL',
                'color' => 'red'
            ],
            'tax' => 0.0,
            'subtotal' => 20.00,
            'isSaved' => false
        ], $cartItem->toArray());
    }

    public function test_it_can_be_cast_to_json(): void
    {
        $cartItem = new CartItem(1, 'Some item', 10.00, ['size' => 'XL', 'color' => 'red']);
        $cartItem->setQuantity(2);

        $this->assertJson($cartItem->toJson());

        $json = '{"rowId":"07d5da5550494c62daf9993cf954303f","id":1,"name":"Some item","qty":2,"price":10,"options":{"size":"XL","color":"red"},"tax":"0.0000","isSaved":false,"subtotal":"20.0000"}';

        $this->assertEquals($json, $cartItem->toJson());
    }
}
