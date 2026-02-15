<?php

namespace App\Domain\Enrollment\Http\Controllers;

use App\Support\Response;

class PublicEnrollmentController
{
    public function show(): void
    {
        $token = $this->resolveToken();
        $this->redirectToWizard($token, $this->queryWithoutToken());
    }

    public function submit(): void
    {
        $token = $this->resolveToken();
        $this->redirectToWizard($token, $this->queryWithoutToken());
    }

    public function lookup(): void
    {
        $token = $this->resolveToken();
        $query = $this->queryWithoutToken();
        $lookupCui = preg_replace('/\D+/', '', (string) ($query['cui'] ?? ''));
        unset($query['cui']);
        $query['lookup'] = '1';
        if ($lookupCui !== '') {
            $query['lookup_cui'] = $lookupCui;
        }

        $this->redirectToWizard($token, $query);
    }

    private function resolveToken(): string
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::abort(404);
        }

        return $token;
    }

    private function queryWithoutToken(): array
    {
        $query = $_GET;
        unset($query['token']);
        return $query;
    }

    private function redirectToWizard(string $token, array $query): void
    {
        $target = '/p/' . rawurlencode($token);
        if (!empty($query)) {
            $target .= '?' . http_build_query($query);
        }

        Response::redirect($target);
    }
}
