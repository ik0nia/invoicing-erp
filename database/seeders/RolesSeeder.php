<?php

namespace Database\Seeders;

use App\Domain\Users\Models\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['key' => 'admin', 'label' => 'Administrator'],
            ['key' => 'staff', 'label' => 'Angajat firma'],
            ['key' => 'supplier_user', 'label' => 'Utilizator furnizor'],
            ['key' => 'client_user', 'label' => 'Utilizator client'],
            ['key' => 'intermediary_user', 'label' => 'Utilizator intermediar'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['key' => $role['key']], $role);
        }
    }
}
