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

    public function editBranding(): void
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

        Response::view('admin/settings/branding', [
            'logoPath' => $logoPath,
            'logoUrl' => $logoUrl,
        ]);
    }

    public function updateBranding(): void
    {
        Auth::requireAdmin();

        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Te rog incarca un fisier valid.');
            Response::redirect('/admin/setari/branding');
        }

        $file = $_FILES['logo'];
        $maxSize = 2 * 1024 * 1024;

        if ($file['size'] > $maxSize) {
            Session::flash('error', 'Logo-ul trebuie sa fie sub 2 MB.');
            Response::redirect('/admin/setari/branding');
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
            Response::redirect('/admin/setari/branding');
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
            Response::redirect('/admin/setari/branding');
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

        Session::flash('status', 'Logo actualizat.');
        Response::redirect('/admin/setari/branding');
    }
}
