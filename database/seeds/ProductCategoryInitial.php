<?php

use Illuminate\Database\Seeder;

class ProductCategoryInitial {
    public function run() {
        $path = 'database/seeds/sql/product_category.sql';
        DB::unprepared(file_get_contents($path));
    }
}