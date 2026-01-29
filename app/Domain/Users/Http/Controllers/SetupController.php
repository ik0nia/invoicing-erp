<?php

namespace App\Domain\Users\Http\Controllers;

use App\Domain\Users\Models\Role;
use App\Domain\Users\Models\User;
use App\Support\Response;
use App\Support\Session;

class SetupController
{
    public function show(): void
    {
        if (User::exists()) {
            Response::redirect('/login');
        }

        Response::view('auth/setup');
    }

    public function create(): void
    {
        if (User::exists()) {
            Response::redirect('/login');
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirmation'] ?? '';

        if ($name === '' || $email === '' || $password === '') {
            Session::flash('error', 'Completeaza toate campurile.');
            Response::redirect('/setup');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Email invalid.');
            Response::redirect('/setup');
        }

        if ($password !== $passwordConfirm) {
            Session::flash('error', 'Parolele nu coincid.');
            Response::redirect('/setup');
        }

        Role::ensureDefaults();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $user->assignRole('admin');

        Session::flash('status', 'Contul de administrator a fost creat.');
        Response::redirect('/login');
    }
}
