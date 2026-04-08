<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'name'      => 'TMS Corporación',
            'tax_id'    => 'TMS-001',
            'email'     => 'admin@tms.local',
            'is_active' => true,
        ]);

        User::create([
            'name'       => 'Administrador',
            'email'      => 'admin@tms.local',
            'password'   => Hash::make('password'),
            'role'       => 'admin',
            'company_id' => $company->id,
            'is_active'  => true,
        ]);
    }
}

