<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['user', 'it_operator', 'administrator'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
