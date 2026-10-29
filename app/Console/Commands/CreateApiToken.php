<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateApiToken extends Command
{
    protected $signature = 'api:token {name : who the token is for, e.g. "Inertia app"} {--revoke : delete that name\'s tokens instead}';

    protected $description = 'Create a read-only API token (shown once) for another system, or revoke its tokens.';

    public function handle(): int
    {
        $name = trim($this->argument('name'));
        $email = 'api-' . Str::slug($name) . '@integration.local';
        $user = User::where('email', $email)->first();

        if ($this->option('revoke')) {
            if (! $user) {
                $this->error("No API access called \"{$name}\".");
                return self::FAILURE;
            }
            $count = $user->tokens()->count();
            $user->tokens()->delete();
            $this->info("Revoked {$count} token(s) for \"{$name}\".");
            return self::SUCCESS;
        }

        // Each system gets its own non-login account (an unknown random password), so an API token never
        // acts as one of the real users and shows up under its own name.
        $user ??= User::create([
            'name' => "API: {$name}",
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
            'role' => 'api',
            'is_active' => true,
        ]);

        $token = $user->createToken($name, ['read'])->plainTextToken;

        $this->info("Token for \"{$name}\" (read-only). Copy it now, it is not shown again:");
        $this->line($token);

        return self::SUCCESS;
    }
}
