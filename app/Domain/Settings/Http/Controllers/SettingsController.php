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
        $fgoSeries = (string) $this->settings->get('fgo.series', '');
        $fgoSeriesList = $this->settings->get('fgo.series_list', []);
        if (!is_array($fgoSeriesList)) {
            $fgoSeriesList = [];
        }
        $fgoSeriesListText = implode(', ', $fgoSeriesList);
        $fgoBaseUrl = (string) $this->settings->get('fgo.base_url', '');
        $company = [
            'denumire' => (string) $this->settings->get('company.denumire', ''),
            'tip_firma' => (string) $this->settings->get('company.tip_firma', ''),
            'cui' => (string) $this->settings->get('company.cui', ''),
            'nr_reg_comertului' => (string) $this->settings->get('company.nr_reg_comertului', ''),
            'platitor_tva' => (bool) $this->settings->get('company.platitor_tva', false),
            'adresa' => (string) $this->settings->get('company.adresa', ''),
            'localitate' => (string) $this->settings->get('company.localitate', ''),
            'judet' => (string) $this->settings->get('company.judet', ''),
            'tara' => (string) $this->settings->get('company.tara', 'Romania'),
            'email' => (string) $this->settings->get('company.email', ''),
            'telefon' => (string) $this->settings->get('company.telefon', ''),
            'banca' => (string) $this->settings->get('company.banca', ''),
            'iban' => (string) $this->settings->get('company.iban', ''),
        ];

        Response::view('admin/settings/index', [
            'logoPath' => $logoPath,
            'logoUrl' => $logoUrl,
            'fgoApiKey' => $fgoApiKey,
            'fgoSeries' => $fgoSeries,
            'fgoSeriesList' => $fgoSeriesList,
            'fgoSeriesListText' => $fgoSeriesListText,
            'fgoBaseUrl' => $fgoBaseUrl,
            'company' => $company,
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
        $series = trim($_POST['fgo_series'] ?? '');
        $seriesListRaw = trim($_POST['fgo_series_list'] ?? '');
        $baseUrl = trim($_POST['fgo_base_url'] ?? '');
        $companyData = [
            'denumire' => trim($_POST['company_denumire'] ?? ''),
            'tip_firma' => trim($_POST['company_tip_firma'] ?? ''),
            'cui' => trim($_POST['company_cui'] ?? ''),
            'nr_reg_comertului' => trim($_POST['company_nr_reg_comertului'] ?? ''),
            'platitor_tva' => ($_POST['company_platitor_tva'] ?? '') === '1',
            'adresa' => trim($_POST['company_adresa'] ?? ''),
            'localitate' => trim($_POST['company_localitate'] ?? ''),
            'judet' => trim($_POST['company_judet'] ?? ''),
            'tara' => trim($_POST['company_tara'] ?? ''),
            'email' => trim($_POST['company_email'] ?? ''),
            'telefon' => trim($_POST['company_telefon'] ?? ''),
            'banca' => trim($_POST['company_banca'] ?? ''),
            'iban' => trim($_POST['company_iban'] ?? ''),
        ];

        $savedSomething = $logoUpdated;

        if ($apiKey !== '') {
            $this->settings->set('fgo.api_key', $apiKey);
            $savedSomething = true;
        }

        $seriesList = [];
        if ($seriesListRaw !== '') {
            $parts = preg_split('/[,\n;]+/', $seriesListRaw);
            foreach ($parts as $part) {
                $value = trim((string) $part);
                if ($value === '') {
                    continue;
                }
                $seriesList[$value] = true;
            }
        }
        $seriesList = array_keys($seriesList);

        if ($series !== '' && !in_array($series, $seriesList, true)) {
            $seriesList[] = $series;
        }

        if ($seriesListRaw !== '') {
            $this->settings->set('fgo.series_list', $seriesList);
            $savedSomething = true;
        }

        if ($series !== '') {
            $this->settings->set('fgo.series', $series);
            $savedSomething = true;
        }

        if ($baseUrl !== '') {
            $this->settings->set('fgo.base_url', $baseUrl);
            $savedSomething = true;
        }

        foreach ($companyData as $key => $value) {
            $this->settings->set('company.' . $key, $value);
        }
        $savedSomething = true;

        if ($savedSomething) {
            Session::flash('status', 'Setarile au fost salvate.');
        } else {
            Session::flash('status', 'Nimic de actualizat.');
        }

        Response::redirect('/admin/setari');
    }
}
