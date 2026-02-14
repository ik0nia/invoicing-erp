<?php

namespace App\Domain\Public\Services;

use App\Support\Database;
use App\Support\TokenService;

class PublicLinkResolver
{
    public function resolve(string $token): ?array
    {
        $hash = TokenService::hashToken($token);

        $portal = $this->findPortalLink($hash);
        if ($portal) {
            return [
                'mode' => 'portal',
                'hash' => $hash,
                'link' => $portal,
                'permissions' => $this->decodePermissions($portal['permissions_json'] ?? null),
                'owner_type' => (string) ($portal['owner_type'] ?? ''),
                'owner_cui' => (string) ($portal['owner_cui'] ?? ''),
                'relation_supplier_cui' => (string) ($portal['relation_supplier_cui'] ?? ''),
                'relation_client_cui' => (string) ($portal['relation_client_cui'] ?? ''),
            ];
        }

        $enrollment = $this->findEnrollmentLink($hash);
        if ($enrollment) {
            return [
                'mode' => 'enrollment',
                'hash' => $hash,
                'link' => $enrollment,
                'permissions' => [
                    'can_view' => true,
                    'can_upload_signed' => true,
                    'can_upload_custom' => false,
                ],
                'enroll_type' => (string) ($enrollment['type'] ?? ''),
                'supplier_cui' => (string) ($enrollment['supplier_cui'] ?? ''),
            ];
        }

        return null;
    }

    private function findPortalLink(string $hash): ?array
    {
        if (!Database::tableExists('portal_links')) {
            return null;
        }

        $row = Database::fetchOne(
            'SELECT * FROM portal_links WHERE token_hash = :hash LIMIT 1',
            ['hash' => $hash]
        );
        if (!$row) {
            return null;
        }
        if (($row['status'] ?? '') !== 'active') {
            return null;
        }
        $expires = $row['expires_at'] ?? null;
        if ($expires && strtotime((string) $expires) < time()) {
            return null;
        }

        return $row;
    }

    private function findEnrollmentLink(string $hash): ?array
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
        if (($row['status'] ?? '') !== 'active') {
            return null;
        }
        $expires = $row['expires_at'] ?? null;
        if ($expires && strtotime((string) $expires) < time()) {
            return null;
        }
        $maxUses = (int) ($row['max_uses'] ?? 1);
        $uses = (int) ($row['uses'] ?? 0);
        if ($maxUses > 0 && $uses >= $maxUses && empty($row['confirmed_at'])) {
            return null;
        }

        return $row;
    }

    private function decodePermissions(mixed $raw): array
    {
        if (!$raw) {
            return [
                'can_view' => false,
                'can_upload_signed' => false,
                'can_upload_custom' => false,
            ];
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [
                'can_view' => false,
                'can_upload_signed' => false,
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
