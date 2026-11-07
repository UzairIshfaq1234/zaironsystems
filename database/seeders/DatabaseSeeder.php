<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Roles, plus the first super admin when SUPERADMIN_EMAIL is set in .env.
     * (No default accounts with known passwords are ever created.)
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if ($email = env('SUPERADMIN_EMAIL')) {
            Artisan::call('zairon:superadmin', array_filter([
                'email' => $email,
                '--name' => env('SUPERADMIN_NAME', 'Super Admin'),
                '--password' => env('SUPERADMIN_PASSWORD'),
            ]));
            $this->command?->getOutput()->write(Artisan::output());
        }
    }
}
