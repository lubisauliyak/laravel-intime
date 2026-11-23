<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class EnsureOperatorExistsSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure operator role exists (it should, but safety first)
        if (!Role::where('name', 'operator')->exists()) {
            Role::create(['name' => 'operator']);
        }

        $user = User::firstOrCreate(
            ['email' => 'operator@intime.com'],
            [
                'name' => 'Operator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole('operator');
        
        $this->command->info('Operator user created/ensured with password: password');
    }
}
