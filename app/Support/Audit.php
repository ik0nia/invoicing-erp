<?php

namespace App\Support;

use App\Domain\Users\Models\Role;
use App\Domain\Users\Models\User;

class Audit
{
    public static function record(string $action, string $entityType, ?int $entityId, array $context = []): void
    {
        try {
            $user = Auth::user();
            $actorUserId = $user ? (int) $user->id : null;
            $actorRole = $user ? self::resolveActorRole($user) : null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $actionValue = substr($action, 0, 64);
            $entityTypeValue = substr($entityType, 0, 32);
            $roleValue = $actorRole !== null ? substr($actorRole, 0, 32) : null;
            $ipValue = $ip !== null ? substr((string) $ip, 0, 64) : null;
            $uaValue = $userAgent !== null ? substr((string) $userAgent, 0, 255) : null;

            $contextJson = null;
            if (!empty($context)) {
                $encoded = json_encode($context, JSON_UNESCAPED_UNICODE);
                if ($encoded !== false) {
                    $contextJson = $encoded;
                }
            }

            Database::execute(
                'INSERT INTO audit_log (actor_user_id, actor_role, ip, user_agent, action, entity_type, entity_id, context_json)
                 VALUES (:actor_user_id, :actor_role, :ip, :user_agent, :action, :entity_type, :entity_id, :context_json)',
                [
                    'actor_user_id' => $actorUserId,
                    'actor_role' => $roleValue,
                    'ip' => $ipValue,
                    'user_agent' => $uaValue,
                    'action' => $actionValue,
                    'entity_type' => $entityTypeValue,
                    'entity_id' => $entityId,
                    'context_json' => $contextJson,
                ]
            );
        } catch (\Throwable $exception) {
            Logger::logWarning('audit_log_failed', [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private static function resolveActorRole(User $user): ?string
    {
        $priority = [
            'super_admin',
            'admin',
            'contabil',
            'operator',
            'supplier_user',
            'staff',
            'client_user',
            'intermediary_user',
        ];

        foreach ($priority as $roleKey) {
            if ($user->hasRole($roleKey)) {
                return $roleKey;
            }
        }

        $roles = $user->roles();
        if (empty($roles)) {
            return null;
        }

        $first = $roles[0];
        if ($first instanceof Role) {
            return $first->key;
        }

        return null;
    }
}
