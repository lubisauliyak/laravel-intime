<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Level;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class EnsureSpecificUsersSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Roles
        $roles = ['admin', 'operator', 'super_admin'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
        
        // 2. Ensure Level for Groups
        // Level 1 = Tingkat terendah/dasar (Kelompok Lokal)
        // Level lebih tinggi = angka lebih besar (2 = Cabang, 3 = Wilayah, dst)
        $level = Level::firstOrCreate(
            ['level_number' => 1], 
            ['name' => 'Kelompok', 'code' => 'KEL']
        );

        // 3. Ensure Groups & Users
        $this->ensureUser('admin@gedangan.com', 'Admin Gedangan', 'admin', 'Gedangan', $level->id);
        $this->ensureUser('operator@sidoarjo.com', 'Operator Sidoarjo Tengah', 'operator', 'Sidoarjo Tengah', $level->id);
    }

    private function ensureUser($email, $name, $role, $groupName, $levelId)
    {
        $groupId = null;
        if ($groupName) {
            $group = Group::firstOrCreate(
                ['name' => $groupName],
                [
                    'status' => 1, 
                    'level_id' => $levelId
                ]
            );
            $groupId = $group->id;
        }

        $user = User::withTrashed()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'group_id' => $groupId,
                'status' => 1, 
                'role' => $role, // Populate legacy/enum role column
            ]
        );
        
        if ($user->trashed()) {
            $user->restore();
        }

        $user->assignRole($role);
        
        $this->command->info("User ensured: {$email} ({$role}) - Group: " . ($groupName ?? 'None'));
    }
}
