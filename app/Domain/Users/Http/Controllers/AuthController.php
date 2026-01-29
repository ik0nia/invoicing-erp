<?php

namespace App\Domain\Users\Http\Controllers;

use App\Support\Auth;
use App\Support\Response;
use App\Support\Session;

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            Response::redirect('/admin/dashboard');
        }

        Response::view('auth/login');
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            Session::flash('error', 'Completeaza email si parola.');
            Response::redirect('/login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Email invalid.');
            Response::redirect('/login');
        }

        if (!Auth::attempt($email, $password)) {
            Session::flash('error', 'Datele de autentificare nu sunt corecte.');
            Response::redirect('/login');
        }

        Response::redirect('/admin/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('/login');
    }
}
