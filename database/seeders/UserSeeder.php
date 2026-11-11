<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        User::create([
            'name' => 'Andrea Sanchez',
            'email' => 'andreasanchez@test.com',
            'password' => bcrypt('12345678'),
            'role_id' => 1, // Administrador
            'activo' => true,
        ]);
        User::create([
            'name' => 'Felipe Martinez',
            'email' => 'felipemartinez@test.com',
            'password' => bcrypt('12345678'),
            'role_id' => 2, // Cocinero
            'activo' => true,
        ]);
        User::create([
            'name' => 'Sebastián Gómez',
            'email' => 'sebastiangomez@test.com',
            'password' => bcrypt('12345678'),
            'role_id' => 3, // Repartidor
            'activo' => true,
        ]);
        User::create([
            'name' => 'Florencia Berra',
            'email' => 'florenciaberra.fb@gmail.com',
            'password' => bcrypt('12345678'),
            'role_id' => 1, // Administrador
            'activo' => true,
        ]);
        User::create([
            'name' => 'Aguistina Berra',
            'email' => 'agustinaberra.ab@gmail.com',
            'password' => bcrypt('12345678'),
            'role_id' => 1, // Administrador
            'activo' => true,
        ]);
        User::create([
            'name' => 'Benjamin Valdez',
            'email' => 'benjavaldez62@gmail.com',
            'password' => bcrypt('12345678'),
            'role_id' => 2, // Cocinero
            'activo' => true,
        ]);
        User::create([
            'name' => 'Fabricio Alanis',
            'email' => 'studyfabri.a@gmail.com',
            'password' => bcrypt('12345678'),
            'role_id' => 3, // Repartidor
            'activo' => true,
        ]);
    }
}