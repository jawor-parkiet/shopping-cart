<?php

namespace JaworParkiet\Tests\ShoppingCart\Support\Fixtures;

class ProductModel
{
    public $someValue = 'Some value';

    public function find($id)
    {
        return $this;
    }
}