<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
            ]
        );

        $customer = User::query()->updateOrCreate(
            ['email' => 'customer@example.com'],
            [
                'name' => 'Customer User',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CUSTOMER,
            ]
        );

        $products = [
            [
                'name' => 'Laravel Advanced Book',
                'sku' => 'LARAVEL-BOOK-001',
                'price' => 150000,
                'stock' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'Redis Performance Course',
                'sku' => 'REDIS-COURSE-001',
                'price' => 250000,
                'stock' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'Senior Developer Toolkit',
                'sku' => 'DEV-TOOLKIT-001',
                'price' => 500000,
                'stock' => 20,
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['sku' => $product['sku']],
                $product
            );
        }
    }
}
