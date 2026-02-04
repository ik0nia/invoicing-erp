<?php

namespace App\Support;

use App\Domain\Users\Models\Role;
use App\Domain\Users\Models\User;

class Auth
{
    public static function user(): ?User
    {
        $userId = Session::get('user_id');

        if (!$userId) {
            return null;
        }

        Role::ensureDefaults();

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
        Role::ensureDefaults();

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

        if (!$user || !$user->isPlatformUser()) {
            Response::abort(403, 'Acces interzis.');
        }
    }

    public static function requireAdminWithoutOperator(): void
    {
        self::requireLogin();

        $user = self::user();

        if (!$user || !$user->hasRole(['super_admin', 'admin', 'contabil'])) {
            Response::abort(403, 'Acces interzis.');
        }
    }

    public static function requireSagaRole(): void
    {
        self::requireLogin();

        $user = self::user();

        if (!$user || !$user->hasRole(['super_admin', 'contabil'])) {
            Response::abort(403, 'Acces interzis.');
        }
    }

    public static function requireSuperAdmin(): void
    {
        self::requireLogin();

        $user = self::user();

        if (!$user || !$user->isSuperAdmin()) {
            Response::abort(403, 'Acces interzis.');
        }
    }
}
