<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class UserCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создать пользователя dsc-23@yandex.ru (пароль: 123123123, имя: Джон Уик)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = 'dsc-23@yandex.ru';
        $password = '123123123';
        $name = 'Джон Уик';

        $existing = User::where('email', $email)->first();
        if ($existing) {
            $this->warn("Пользователь с email {$email} уже существует.");
            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Пользователь создан: {$name} ({$email})");
        return self::SUCCESS;
    }
}
