<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\AuthController;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * php artisan zairon:superadmin admin@zairon.com --name="Muhammad Uzair Ishfaq"
 * Creates (or promotes) a super admin. Without --password a strong one is generated and shown once.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'zairon:superadmin {email : Sign-in email} {--name=Super Admin : Display name} {--password= : Leave empty to generate one}';

    protected $description = 'Create or promote a ZAIRON Systems admin portal super admin';

    public function handle(): int
    {
        $email = Str::lower((string) $this->argument('email'));
        $password = $this->option('password') ?: Str::password(18, symbols: false);

        $validator = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email:rfc,strict', 'max:190'],
            'password' => ['required', AuthController::passwordRule()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->callSilently('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);

        $user = User::firstOrNew(['email' => $email]);
        $created = ! $user->exists;
        $user->fill(['name' => $user->name ?: $this->option('name'), 'is_active' => true]);
        if ($created || $this->option('password')) {
            $user->password = $password;
        }
        $user->email_verified_at ??= now();
        $user->save();
        $user->roles()->syncWithoutDetaching(Role::where('slug', config('permissions.super_admin_role'))->pluck('id'));

        $this->components->info(($created ? 'Created' : 'Promoted').' super admin '.$email);
        if ($created && ! $this->option('password')) {
            $this->components->twoColumnDetail('Generated password', $password);
            $this->components->warn('Shown only once. Sign in at /admin and change it under Profile.');
        }

        return self::SUCCESS;
    }
}
