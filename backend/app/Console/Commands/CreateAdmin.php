<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin {email} {--name=} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or promote a classroom administrator account.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) ($this->option('name') ?: $this->ask('Administrator name'));
        $password = $this->option('password') ?: $this->secret('Administrator password (minimum 8 characters)');
        validator(['email' => $email, 'name' => $name, 'password' => $password], [
            'email' => ['required', 'email', 'max:255'], 'name' => ['required', 'string', 'max:120'], 'password' => ['required', Password::min(8)],
        ])->validate();
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = Hash::make($password);
        $user->is_admin = true;
        $user->save();
        $this->info('Administrator account provisioned.');

        return self::SUCCESS;
    }
}
