<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('admin:create {email? : Adresse e-mail} {--name= : Nom complet} {--password= : Mot de passe}')]
#[Description('Créer un compte administrateur MecaDispo')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = Str::lower((string) ($this->argument('email') ?: $this->ask('Adresse e-mail')));
        $name = (string) ($this->option('name') ?: $this->ask('Nom complet'));
        $password = (string) ($this->option('password') ?: $this->secret('Mot de passe (8 caractères minimum)'));
        $validator = Validator::make(compact('email', 'name', 'password'), [
            'email' => ['required', 'email', 'unique:admins,email'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        Admin::create($validator->validated());
        $this->info('Le compte administrateur a été créé.');

        return self::SUCCESS;
    }
}
