<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $attributes = [
            'name' => 'Giordano Berwig',
            'password' => '12345678',
        ];

        if (Schema::hasColumn('users', 'username')) {
            $attributes['username'] = 'giordanoberwig';
        }

        if (Schema::hasColumn('users', 'phone')) {
            $attributes['phone'] = null;
        }

        if (Schema::hasColumn('users', 'cpf')) {
            $attributes['cpf'] = null;
        }

        if (Schema::hasColumn('users', 'admin')) {
            $attributes['admin'] = true;
        }

        User::query()->updateOrCreate([
            'email' => 'giordanoberwig@proton.me',
        ], $attributes);
    }
}
