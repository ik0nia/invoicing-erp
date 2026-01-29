<?php

namespace App\Domain\Users\Models;

use App\Domain\Companies\Models\Company;
use App\Support\Database;

class User
{
    public int $id;
    public ?int $company_id;
    public string $name;
    public string $email;
    public string $password;
    public ?string $remember_token;
    public ?string $created_at;
    public ?string $updated_at;

    public static function fromArray(array $row): self
    {
        $user = new self();
        $user->id = (int) $row['id'];
        $user->company_id = $row['company_id'] !== null ? (int) $row['company_id'] : null;
        $user->name = $row['name'];
        $user->email = $row['email'];
        $user->password = $row['password'];
        $user->remember_token = $row['remember_token'] ?? null;
        $user->created_at = $row['created_at'] ?? null;
        $user->updated_at = $row['updated_at'] ?? null;

        return $user;
    }

    public static function find(int $id): ?self
    {
        $row = Database::fetchOne('SELECT * FROM users WHERE id = :id LIMIT 1', [
            'id' => $id,
        ]);

        return $row ? self::fromArray($row) : null;
    }

    public static function findByEmail(string $email): ?self
    {
        $row = Database::fetchOne('SELECT * FROM users WHERE email = :email LIMIT 1', [
            'email' => $email,
        ]);

        return $row ? self::fromArray($row) : null;
    }

    public static function exists(): bool
    {
        $row = Database::fetchOne('SELECT id FROM users LIMIT 1');

        return $row !== null;
    }

    public static function create(array $data): self
    {
        $now = date('Y-m-d H:i:s');

        Database::execute(
            'INSERT INTO users (company_id, name, email, password, created_at, updated_at)
             VALUES (:company_id, :name, :email, :password, :created_at, :updated_at)',
            [
                'company_id' => $data['company_id'] ?? null,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => password_hash($data['password'], PASSWORD_BCRYPT),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return self::find((int) Database::lastInsertId());
    }

    public function roles(): array
    {
        return Role::forUser($this->id);
    }

    public function hasRole(string|Role|array $role): bool
    {
        if ($role instanceof Role) {
            $role = $role->key;
        }

        $keys = is_array($role) ? $role : [$role];

        foreach ($this->roles() as $assignedRole) {
            if (in_array($assignedRole->key, $keys, true)) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function assignRole(string $roleKey): void
    {
        Role::assignToUser($this->id, $roleKey);
    }

    public function company(): ?Company
    {
        if ($this->company_id === null) {
            return null;
        }

        return Company::find($this->company_id);
    }
}
