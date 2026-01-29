<?php

namespace App\Support;

use App\Domain\Users\Models\User;

class Auth
{
    public static function user(): ?User
    {
        $userId = Session::get('user_id');

        if (!$userId) {
            return null;
        }

        return User::find((int) $userId);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user->password)) {
            return false;
        }

        Session::put('user_id', $user->id);

        return true;
    }

    public static function logout(): void
    {
        Session::forget('user_id');
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            Response::redirect('/login');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();

        $user = self::user();

        if (!$user || !$user->isAdmin()) {
            Response::abort(403, 'Acces interzis.');
        }
    }
}
