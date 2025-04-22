<?php

namespace JaworParkiet\Tests\ShoppingCart;

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use JaworParkiet\ShoppingCart\Cart;
use JaworParkiet\ShoppingCart\CartItem;
use JaworParkiet\ShoppingCart\Exceptions\InvalidRowIDException;
use JaworParkiet\ShoppingCart\Exceptions\UnknownModelException;
use JaworParkiet\ShoppingCart\ShoppingCartServiceProvider;
use JaworParkiet\Tests\ShoppingCart\Support\Fixtures\BuyableProduct;
use JaworParkiet\Tests\ShoppingCart\Support\Fixtures\ProductModel;
use Mockery;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Assert as PHPUnit;
use ReflectionClass;

class CartTest extends TestCase
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

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cart.database.connection', 'testing');

        $app['config']->set('session.driver', 'array');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->afterResolving('migrator', function ($migrator) {
            $migrator->path(realpath(__DIR__.'/../database/migrations'));
        });
    }

    public function test_it_has_a_default_instance(): void
    {
        $cart = $this->getCart();

        $this->assertEquals(Cart::DEFAULT_INSTANCE, $cart->currentInstance());
    }

    public function test_it_can_have_multiple_instances(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item'));

        $cart->instance('wishlist')->add(new BuyableProduct(2, 'Second item'));

        $this->assertItemsInCart(1, $cart->instance(Cart::DEFAULT_INSTANCE));
        $this->assertItemsInCart(1, $cart->instance('wishlist'));
    }
    
    public function test_it_can_add_an_item(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    public function test_it_will_return_the_cart_item_of_the_added_item(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(new BuyableProduct);

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals('027c91341fd5cf4d2579b49c4b6a90da', $cartItem->rowId);

        Event::assertDispatched('cart.added');
    }

    public function test_it_can_add_multiple_buyable_items_at_once(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add([new BuyableProduct(1), new BuyableProduct(2)]);

        $this->assertEquals(2, $cart->count());

        Event::assertDispatched('cart.added');
    }

    public function test_it_will_return_an_array_of_cartitems_when_you_add_multiple_items_at_once(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItems = $cart->add([new BuyableProduct(1), new BuyableProduct(2)]);

        $this->assertTrue(is_array($cartItems));
        $this->assertCount(2, $cartItems);
        $this->assertContainsOnlyInstancesOf(CartItem::class, $cartItems);

        Event::assertDispatched('cart.added');
    }

    public function test_it_can_add_an_item_from_attributes(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 10.00);

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    public function test_it_can_add_an_item_from_an_array(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(['id' => 1, 'name' => 'Test item', 'qty' => 1, 'price' => 10.00]);

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    public function test_it_can_add_multiple_array_items_at_once(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add([
            ['id' => 1, 'name' => 'Test item 1', 'qty' => 1, 'price' => 10.00],
            ['id' => 2, 'name' => 'Test item 2', 'qty' => 1, 'price' => 10.00]
        ]);

        $this->assertEquals(2, $cart->count());

        Event::assertDispatched('cart.added');
    }

    public function test_it_can_add_an_item_with_options(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $options = ['size' => 'XL', 'color' => 'red'];

        $cart->add(new BuyableProduct, 1, $options);

        $cartItem = $cart->get('07d5da5550494c62daf9993cf954303f');

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals('XL', $cartItem->options->size);
        $this->assertEquals('red', $cartItem->options->color);

        Event::assertDispatched('cart.added');
    }

    public function test_it_will_validate_the_identifier(): void
    {
        $this->expectExceptionMessage('Please supply a valid identifier.');
        $this->expectException(InvalidArgumentException::class);

        $cart = $this->getCart();

        $cart->add(null, 'Some title', 1, 10.00);
    }

    public function test_it_will_validate_the_name(): void
    {
        $this->expectExceptionMessage('Please supply a valid name.');
        $this->expectException(InvalidArgumentException::class);

        $cart = $this->getCart();

        $cart->add(1, null, 1, 10.00);
    }

    public function test_it_will_validate_the_quantity(): void
    {
        $this->expectExceptionMessage('Please supply a valid quantity.');
        $this->expectException(InvalidArgumentException::class);

        $cart = $this->getCart();

        $cart->add(1, 'Some title', 'invalid', 10.00);
    }

    public function test_it_will_validate_the_price(): void
    {
        $this->expectExceptionMessage('Please supply a valid price.');
        $this->expectException(InvalidArgumentException::class);

        $cart = $this->getCart();

        $cart->add(1, 'Some title', 1, 'invalid');
    }

    public function test_it_will_update_the_cart_if_the_item_already_exists_in_the_cart(): void
    {
        $cart = $this->getCart();

        $item = new BuyableProduct;

        $cart->add($item);
        $cart->add($item);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    public function test_it_will_keep_updating_the_quantity_when_an_item_is_added_multiple_times(): void
    {
        $cart = $this->getCart();

        $item = new BuyableProduct;

        $cart->add($item);
        $cart->add($item);
        $cart->add($item);

        $this->assertItemsInCart(3, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    public function test_it_can_update_the_quantity_of_an_existing_item_in_the_cart(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', 2);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);

        Event::assertDispatched('cart.updated');
    }

    public function test_it_can_update_an_existing_item_in_the_cart_from_a_buyable(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', new BuyableProduct(1, 'Different description'));

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('Different description', $cart->get('027c91341fd5cf4d2579b49c4b6a90da')->name);

        Event::assertDispatched('cart.updated');
    }

    public function test_it_can_update_an_existing_item_in_the_cart_from_an_array(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', ['name' => 'Different description']);

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('Different description', $cart->get('027c91341fd5cf4d2579b49c4b6a90da')->name);

        Event::assertDispatched('cart.updated');
    }

    public function test_it_will_throw_an_exception_if_a_rowid_was_not_found(): void
    {
        $this->expectException(InvalidRowIDException::class);

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('none-existing-rowid', new BuyableProduct(1, 'Different description'));
    }

    public function test_it_will_regenerate_the_rowid_if_the_options_changed(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct, 1, ['color' => 'red']);

        $cart->update('ea65e0bdcd1967c4b3149e9e780177c0', ['options' => ['color' => 'blue']]);

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('7e70a1e9aaadd18c72921a07aae5d011', $cart->content()->first()->rowId);
        $this->assertEquals('blue', $cart->get('7e70a1e9aaadd18c72921a07aae5d011')->options->color);
    }

    public function test_it_will_add_the_item_to_an_existing_row_if_the_options_changed_to_an_existing_rowid(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct, 1, ['color' => 'red']);
        $cart->add(new BuyableProduct, 1, ['color' => 'blue']);

        $cart->update('7e70a1e9aaadd18c72921a07aae5d011', ['options' => ['color' => 'red']]);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    public function test_it_can_remove_an_item_from_the_cart(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->remove('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    public function test_it_will_remove_the_item_if_its_quantity_was_set_to_zero(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', 0);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    public function test_it_will_remove_the_item_if_its_quantity_was_set_negative(): void
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', -1);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    public function test_it_can_get_an_item_from_the_cart_by_its_rowid():void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertInstanceOf(CartItem::class, $cartItem);
    }

    public function test_it_can_get_the_content_of_the_cart(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1));
        $cart->add(new BuyableProduct(2));

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertCount(2, $content);
    }

    public function test_it_will_return_an_empty_collection_if_the_cart_is_empty(): void
    {
        $cart = $this->getCart();

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertCount(0, $content);
    }

    public function test_it_will_include_the_tax_and_subtotal_when_converted_to_an_array(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1));
        $cart->add(new BuyableProduct(2));

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertEquals([
            '027c91341fd5cf4d2579b49c4b6a90da' => [
                'rowId' => '027c91341fd5cf4d2579b49c4b6a90da',
                'id' => 1,
                'name' => 'Item name',
                'qty' => 1,
                'price' => 10.00,
                'tax' => 2.3000,
                'subtotal' => 10.0000,
                'isSaved' => false,
                'options' => [],
            ],
            '370d08585360f5c568b18d1f2e4ca1df' => [
                'rowId' => '370d08585360f5c568b18d1f2e4ca1df',
                'id' => 2,
                'name' => 'Item name',
                'qty' => 1,
                'price' => 10.00,
                'tax' => 2.3000,
                'subtotal' => 10.0000,
                'isSaved' => false,
                'options' => [],
            ]
        ], $content->toArray());
    }

    public function test_it_can_destroy_a_cart(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $this->assertItemsInCart(1, $cart);

        $cart->destroy();

        $this->assertItemsInCart(0, $cart);
    }

    public function test_it_can_get_the_total_price_of_the_cart_content(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item', 10.00));
        $cart->add(new BuyableProduct(2, 'Second item', 25.00), 2);

        $this->assertItemsInCart(3, $cart);
        $this->assertEquals(60.00, $cart->subtotal());
    }

    public function test_it_can_return_a_formatted_total(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item', 1000.00));
        $cart->add(new BuyableProduct(2, 'Second item', 2500.00), 2);

        $this->assertItemsInCart(3, $cart);
        $this->assertEquals('6.000,00', $cart->subtotal(2, ',', '.'));
    }

    public function test_it_can_search_the_cart_for_a_specific_item(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some item'));
        $cart->add(new BuyableProduct(2, 'Another item'));

        $cartItem = $cart->search(function ($cartItem, $rowId) {
            return $cartItem->name == 'Some item';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
        $this->assertCount(1, $cartItem);
        $this->assertInstanceOf(CartItem::class, $cartItem->first());
        $this->assertEquals(1, $cartItem->first()->id);
    }

    public function test_it_can_search_the_cart_for_multiple_items(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some item'));
        $cart->add(new BuyableProduct(2, 'Some item'));
        $cart->add(new BuyableProduct(3, 'Another item'));

        $cartItem = $cart->search(function ($cartItem, $rowId) {
            return $cartItem->name == 'Some item';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
    }

    public function test_it_can_search_the_cart_for_a_specific_item_with_options(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some item'), 1, ['color' => 'red']);
        $cart->add(new BuyableProduct(2, 'Another item'), 1, ['color' => 'blue']);

        $cartItem = $cart->search(function ($cartItem, $rowId) {
            return $cartItem->options->color == 'red';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
        $this->assertCount(1, $cartItem);
        $this->assertInstanceOf(CartItem::class, $cartItem->first());
        $this->assertEquals(1, $cartItem->first()->id);
    }

    public function test_it_will_associate_the_cart_item_with_a_model_when_you_add_a_buyable(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $associatedModel = (new ReflectionClass($cartItem))->getProperty('associatedModel')->getValue($cartItem);

        $this->assertEquals(BuyableProduct::class, $associatedModel);
    }

    public function test_it_can_associate_the_cart_item_with_a_model(): void
    {
        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 10.00);

        $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', new ProductModel);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $associatedModel = (new ReflectionClass($cartItem))->getProperty('associatedModel')->getValue($cartItem);

        $this->assertEquals(ProductModel::class, $associatedModel);
    }

    public function test_it_will_throw_an_exception_when_a_non_existing_model_is_being_associated(): void
    {
        $this->expectExceptionMessage('The supplied model SomeModel does not exist.');
        $this->expectException(UnknownModelException::class);

        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 10.00);

        $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', 'SomeModel');
    }

    public function test_it_can_get_the_associated_model_of_a_cart_item(): void
    {
        $cart = $this->getCart();

        $cart->add(1, 'Test item', 1, 10.00);

        $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', new ProductModel);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertInstanceOf(ProductModel::class, $cartItem->model);
        $this->assertEquals('Some value', $cartItem->model->someValue);
    }

    public function test_it_can_calculate_the_subtotal_of_a_cart_item(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 9.99), 3);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(29.97, $cartItem->subtotal);
    }

    public function test_it_can_return_a_formatted_subtotal(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 500), 3);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('1.500,00', $cartItem->subtotal(2, ',', '.'));
    }

    public function test_it_can_calculate_tax_based_on_the_default_tax_rate_in_the_config(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(2.3000, $cartItem->tax);
    }

    public function test_it_can_calculate_tax_based_on_the_specified_tax(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);

        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(1.90, $cartItem->tax);
    }

    public function test_it_can_return_the_calculated_tax_formatted(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10000.00), 1);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('2.300,00', $cartItem->tax(2, ',', '.'));
    }

    public function test_it_can_calculate_the_total_tax_for_all_cart_items(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 20.00), 2);

        $this->assertEquals(11.50, $cart->tax);
    }

    public function test_it_can_return_formatted_total_tax(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('1.150,00', $cart->tax(2, ',', '.'));
    }

    public function test_it_can_return_the_subtotal(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 10.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 20.00), 2);

        $this->assertEquals(50.00, $cart->subtotal);
    }

    public function test_it_can_return_formatted_subtotal(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('5000,00', $cart->subtotal(2, ',', ''));
    }

    public function test_it_can_return_cart_formated_numbers_by_config_values(): void
    {
        $this->setConfigFormat(23, 4, ',', '');

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 1000.00), 1);
        $cart->add(new BuyableProduct(2, 'Some title', 2000.00), 2);

        $this->assertEquals('5000,0000', $cart->subtotal());
        $this->assertEquals('1150,0000', $cart->tax());
        $this->assertEquals('6150,0000', $cart->total());

        $this->assertEquals('5000,0000', $cart->subtotal);
        $this->assertEquals('1150,0000', $cart->tax);
        $this->assertEquals('6150,0000', $cart->total);
    }

    public function test_it_can_return_cart_item_formated_numbers_by_config_values(): void
    {
        $this->setConfigFormat(23, 2, ',', '');

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'Some title', 2000.00), 2);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('2000,00', $cartItem->price());
        $this->assertEquals('2460,00', $cartItem->priceTax());
        $this->assertEquals('4000,0000', $cartItem->subtotal());
        $this->assertEquals('4920,0000', $cartItem->total());
        $this->assertEquals('460,0000', $cartItem->tax());
        $this->assertEquals('920,0000', $cartItem->taxTotal());
    }

    public function test_it_can_store_the_cart_in_a_database(): void
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->store($identifier = 123);

        $serialized = serialize([
            'items' => $cart->content(),
            'fees' => collect(),
        ]);

        $this->assertDatabaseHas('cart', ['identifier' => $identifier, 'instance' => 'default', 'content' => $serialized]);

        Event::assertDispatched('cart.stored');
    }

    public function test_it_can_update_the_cart_in_database(): void
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->store($identifier = 123);

        $serialized = serialize([
            'items' => $cart->content(),
            'fees' => collect(),
        ]);

        $this->assertDatabaseHas('cart', ['identifier' => $identifier, 'instance' => 'default', 'content' => $serialized]);
        
        Event::assertDispatched('cart.stored');
    }

    public function test_it_can_restore_a_cart_from_the_database(): void
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $cart = $this->getCart();

        $cart->add(new BuyableProduct);

        $cart->store($identifier = 123);

        $cart->destroy();

        $this->assertItemsInCart(0, $cart);

        $cart->restore($identifier);

        $this->assertItemsInCart(1, $cart);       

        Event::assertDispatched('cart.restored');
    }

    public function test_it_will_just_keep_the_current_instance_if_no_cart_with_the_given_identifier_was_stored(): void
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        $cart = $this->getCart();

        $cart->restore($identifier = 123);

        $this->assertItemsInCart(0, $cart);
    }

    public function test_it_can_calculate_all_values(): void
    {
        $cart = $this->getCart();

        $cart->add(new BuyableProduct(1, 'First item', 10.00), 2);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $cart->setTax('027c91341fd5cf4d2579b49c4b6a90da', 19);

        $this->assertEquals(10.00, $cartItem->price(2));
        $this->assertEquals(11.90, $cartItem->priceTax(2));
        $this->assertEquals(20.00, $cartItem->subtotal(2));
        $this->assertEquals(23.80, $cartItem->total(2));
        $this->assertEquals(1.90, $cartItem->tax(2));
        $this->assertEquals(3.80, $cartItem->taxTotal(2));

        $this->assertEquals(20.00, $cart->subtotal(2));
        $this->assertEquals(23.80, $cart->total(2));
        $this->assertEquals(3.80, $cart->tax(2));
    }

    public function test_it_will_destroy_the_cart_when_the_user_logs_out_and_the_config_setting_was_set_to_true(): void
    {
        $this->app['config']->set('cart.destroy_on_logout', true);

        $this->app->instance(SessionManager::class, Mockery::mock(SessionManager::class, function ($mock) {
            $mock->shouldReceive('forget')->once()->with('cart');
        }));

        $user = Mockery::mock(Authenticatable::class);

        $guard = $this->app->make('auth');

        event(new Logout($guard, $user));
    }

    /**
     * Get an instance of the cart.
     *
     * @return \JaworParkiet\ShoppingCart\Cart
     */
    private function getCart(): Cart
    {
        $session = $this->app->make('session');
        $events = $this->app->make('events');

        return new Cart($session, $events);
    }

    /**
     * Set the config number format.
     * 
     * @param int    $tax
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeperator
     */
    private function setConfigFormat(int $tax, $decimals, $decimalPoint, $thousandSeperator): void
    {
        $this->app['config']->set('cart.tax', $tax);
        $this->app['config']->set('cart.format.decimals', $decimals);
        $this->app['config']->set('cart.format.decimal_point', $decimalPoint);
        $this->app['config']->set('cart.format.thousand_separator', $thousandSeperator);
    }

    /**
     * Assert that the cart contains the given number of items.
     *
     * @param int|float                     $items
     * @param \JaworParkiet\ShoppingCart\Cart $cart
     */
    private function assertItemsInCart($items, Cart $cart)
    {
        $actual = $cart->count();

        PHPUnit::assertEquals($items, $cart->count(), "Expected the cart to contain {$items} items, but got {$actual}.");
    }

    /**
     * Assert that the cart contains the given number of rows.
     *
     * @param int                           $rows
     * @param \JaworParkiet\ShoppingCart\Cart $cart
     */
    private function assertRowsInCart($rows, Cart $cart)
    {
        $actual = $cart->content()->count();

        PHPUnit::assertCount($rows, $cart->content(), "Expected the cart to contain {$rows} rows, but got {$actual}.");
    }
}
