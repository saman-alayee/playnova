<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\User\Services\AuthRegistrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EnsureAdminUserCommand extends Command
{
    protected $signature = 'admin:ensure
                            {mobile : Admin mobile (e.g. 09051770091)}
                            {--password= : Plain password (min 6 chars). Generated if omitted.}
                            {--username= : Username when creating a new user}';

    protected $description = 'Create or update an admin user by mobile number';

    public function handle(AuthRegistrationService $registration): int
    {
        $mobile = $registration->normalizeMobileForLookup((string) $this->argument('mobile'));
        if (! $mobile) {
            $this->error('Invalid mobile number.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(12, letters: true, numbers: true, symbols: false));
        if (strlen($password) < 6) {
            $this->error('Password must be at least 6 characters.');

            return self::FAILURE;
        }

        $user = User::query()->where('mobile', $mobile)->first();
        $created = false;

        if (! $user) {
            $username = trim((string) $this->option('username'));
            if ($username === '') {
                $username = 'admin_' . substr($mobile, -4);
            }

            $baseUsername = $username;
            $suffix = 1;
            while (User::query()->where('username', $username)->exists()) {
                $username = $baseUsername . $suffix;
                $suffix++;
            }

            $user = User::create([
                'name' => $username,
                'username' => $username,
                'email' => null,
                'mobile' => $mobile,
                'password' => Hash::make($password),
                'cod_id' => 'admin_' . Str::lower(Str::random(8)),
                'referral_code' => User::generateReferralCode(),
                'is_admin' => true,
            ]);
            $created = true;
        } else {
            $user->password = Hash::make($password);
            $user->is_admin = true;
            $user->save();
        }

        $this->info($created ? 'Admin user created.' : 'Existing user promoted / password updated.');
        $this->line('Mobile: ' . $mobile);
        $this->line('Username: ' . $user->username);
        $this->line('Password: ' . $password);

        return self::SUCCESS;
    }
}
