<?php

namespace App\Domain\Settings\Http\Controllers;

use App\Domain\Settings\Services\SettingsService;
use App\Support\Auth;
use App\Support\Response;
use App\Support\Session;

class SettingsController
{
    private SettingsService $settings;

    public function __construct()
    {
        $this->settings = new SettingsService();
    }

    public function edit(): void
    {
        Auth::requireAdmin();

        $logoPath = $this->settings->get('branding.logo_path');
        $logoUrl = null;

        if ($logoPath) {
            $absolutePath = BASE_PATH . '/' . ltrim($logoPath, '/');

            if (file_exists($absolutePath)) {
                $logoUrl = \App\Support\Url::asset($logoPath);
            }
        }

        $fgoApiKey = (string) $this->settings->get('fgo.api_key', '');
        $fgoSecret = (string) $this->settings->get('fgo.secret_key', '');
        $fgoSecretMasked = $fgoSecret !== '' ? str_repeat('*', max(0, strlen($fgoSecret) - 4)) . substr($fgoSecret, -4) : '';

        Response::view('admin/settings/index', [
            'logoPath' => $logoPath,
            'logoUrl' => $logoUrl,
            'fgoApiKey' => $fgoApiKey,
            'fgoSecretMasked' => $fgoSecretMasked,
        ]);
    }

    public function update(): void
    {
        Auth::requireAdmin();

        $logoUpdated = false;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                Session::flash('error', 'Te rog incarca un fisier valid.');
                Response::redirect('/admin/setari');
            }

            $file = $_FILES['logo'];
            $maxSize = 2 * 1024 * 1024;

            if ($file['size'] > $maxSize) {
                Session::flash('error', 'Logo-ul trebuie sa fie sub 2 MB.');
                Response::redirect('/admin/setari');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');

            if ($finfo) {
                finfo_close($finfo);
            }

            $allowed = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/svg+xml' => 'svg',
            ];

            if (!array_key_exists($mime, $allowed)) {
                Session::flash('error', 'Format logo invalid. Acceptam png, jpg sau svg.');
                Response::redirect('/admin/setari');
            }

            $extension = $allowed[$mime];
            $storageDir = BASE_PATH . '/storage/erp';
            $publicDir = BASE_PATH . '/public/storage/erp';

            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0775, true);
            }

            if (!is_dir($publicDir)) {
                mkdir($publicDir, 0775, true);
            }

            $filename = 'logo.' . $extension;
            $targetPath = $storageDir . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                Session::flash('error', 'Nu am putut salva fisierul incarcat.');
                Response::redirect('/admin/setari');
            }

            foreach (['png', 'jpg', 'svg'] as $ext) {
                if ($ext === $extension) {
                    continue;
                }

                @unlink($storageDir . '/logo.' . $ext);
                @unlink($publicDir . '/logo.' . $ext);
            }

            @copy($targetPath, $publicDir . '/' . $filename);

            $this->settings->set('branding.logo_path', 'storage/erp/' . $filename);
            $logoUpdated = true;
        }

        $apiKey = trim($_POST['fgo_api_key'] ?? '');
        $secretKey = trim($_POST['fgo_secret_key'] ?? '');

        $savedSomething = $logoUpdated;

        if ($apiKey !== '') {
            $this->settings->set('fgo.api_key', $apiKey);
            $savedSomething = true;
        }

        if ($secretKey !== '') {
            $this->settings->set('fgo.secret_key', $secretKey);
            $savedSomething = true;
        }

        if ($savedSomething) {
            Session::flash('status', 'Setarile au fost salvate.');
        } else {
            Session::flash('status', 'Nimic de actualizat.');
        }

        Response::redirect('/admin/setari');
    }
}
