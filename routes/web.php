<?php

/*
|--------------------------------------------------------------------------
| Application $routers
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'product-item'], function($router) {
    $router->post('/', 'ProductItemController@index');
    $router->post('/detail', 'ProductItemController@detail');
    $router->post('/store', 'ProductItemController@store');
    $router->post('/update', 'ProductItemController@update');
    $router->post('/delete', 'ProductItemController@delete');
});

$router->group(['prefix' => 'product-category'], function($router) {
    $router->post('/', 'ProductCategoryController@index');
});