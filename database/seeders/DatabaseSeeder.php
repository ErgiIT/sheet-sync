<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('sheets.admin.email');
        $password = config('sheets.admin.password');

        if (empty($email) || empty($password)) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding.');
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => config('sheets.admin.name'), 'password' => Hash::make($password)],
        );

        $this->command?->info("Admin user ready: {$email}");
    }
}
