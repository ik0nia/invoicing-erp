<?php

namespace App\Domain\Public\Services;

use App\Support\Database;
use App\Support\TokenService;

class PublicLinkResolver
{
    public function resolve(string $token): ?array
    {
        $hash = TokenService::hashToken($token);
        $enrollment = $this->findEnrollmentLink($hash, true);
        if (!$enrollment) {
            return null;
        }

        return [
            'hash' => $hash,
            'link' => $enrollment,
            'permissions' => $this->decodePermissions($enrollment['permissions_json'] ?? null),
        ];
    }

    public function resolveAny(string $token): ?array
    {
        $hash = TokenService::hashToken($token);
        $row = $this->findEnrollmentLink($hash, false);
        if (!$row) {
            return null;
        }

        return [
            'hash' => $hash,
            'link' => $row,
            'permissions' => $this->decodePermissions($row['permissions_json'] ?? null),
        ];
    }

    private function findEnrollmentLink(string $hash, bool $onlyActive): ?array
    {
        if (!Database::tableExists('enrollment_links')) {
            return null;
        }

        $row = Database::fetchOne(
            'SELECT * FROM enrollment_links WHERE token_hash = :hash LIMIT 1',
            ['hash' => $hash]
        );
        if (!$row) {
            return null;
        }
        if ($onlyActive) {
            if (($row['status'] ?? '') !== 'active') {
                return null;
            }
            $expires = $row['expires_at'] ?? null;
            if ($expires && strtotime((string) $expires) < time()) {
                return null;
            }
        }

        return $row;
    }

    private function decodePermissions(mixed $raw): array
    {
        if (!$raw) {
            return [
                'can_view' => true,
                'can_upload_signed' => true,
                'can_upload_custom' => false,
            ];
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [
                'can_view' => true,
                'can_upload_signed' => true,
                'can_upload_custom' => false,
            ];
        }

        return [
            'can_view' => !empty($decoded['can_view']),
            'can_upload_signed' => !empty($decoded['can_upload_signed']),
            'can_upload_custom' => !empty($decoded['can_upload_custom']),
        ];
    }
}
