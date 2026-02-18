<?php

namespace App\Domain\Tools\Http\Controllers;

use App\Domain\Contracts\Services\ContractPdfService;
use App\Domain\Settings\Services\SettingsService;
use App\Domain\Tools\Services\PdfRewriteService;
use App\Support\Auth;
use App\Support\Response;
use App\Support\Session;

class PdfToolsController
{
    private const MAX_UPLOAD_BYTES = 15 * 1024 * 1024;

    private SettingsService $settings;
    private PdfRewriteService $rewriteService;
    private ContractPdfService $pdfService;

    public function __construct()
    {
        $this->settings = new SettingsService();
        $this->rewriteService = new PdfRewriteService();
        $this->pdfService = new ContractPdfService();
    }

    public function index(): void
    {
        $this->requireToolsAccess();

        Response::view('admin/tools/pdf_rewrite', [
            'company' => $this->loadCompanyFromSettings(),
            'defaultSeriesFrom' => 'A-MVN',
            'defaultSeriesTo' => 'A-DEON',
        ]);
    }

    public function process(): void
    {
        $this->requireToolsAccess();

        $file = $_FILES['source_pdf'] ?? null;
        if (!$this->isValidUpload($file)) {
            Session::flash('error', 'Te rog sa incarci un fisier PDF valid (maxim 15 MB).');
            Response::redirect('/admin/utile/prelucrare-pdf');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $sourceName = (string) ($file['name'] ?? 'document.pdf');
        $seriesFrom = trim((string) ($_POST['series_from'] ?? 'A-MVN'));
        $seriesTo = trim((string) ($_POST['series_to'] ?? 'A-DEON'));
        if ($seriesFrom === '') {
            $seriesFrom = 'A-MVN';
        }
        if ($seriesTo === '') {
            $seriesTo = 'A-DEON';
        }

        $company = $this->loadCompanyFromSettings();
        $primaryText = $this->rewriteService->extractText($tmpPath);
        if ($primaryText === '') {
            $primaryText = $this->rewriteService->extractTextWithOcr($tmpPath);
        }
        if ($primaryText === '') {
            Session::flash('error', 'Nu am putut extrage text din PDF-ul incarcat, nici cu OCR.');
            Response::redirect('/admin/utile/prelucrare-pdf');
        }

        $aviz = $this->rewriteService->parseAvizData($primaryText);
        $selectedText = $primaryText;
        $primaryScore = $this->scoreParsedAviz($aviz, $primaryText);

        if ($this->shouldUseOcrFallback($aviz, $primaryText) && $this->rewriteService->isOcrAvailable()) {
            $ocrText = $this->rewriteService->extractTextWithOcr($tmpPath);
            if ($ocrText !== '') {
                $ocrAviz = $this->rewriteService->parseAvizData($ocrText);
                $ocrScore = $this->scoreParsedAviz($ocrAviz, $ocrText);
                if ($ocrScore > $primaryScore) {
                    $aviz = $ocrAviz;
                    $selectedText = $ocrText;
                }
            }
        }
        $changes = [];
        $documentNo = trim((string) ($aviz['document_number'] ?? ''));
        if ($documentNo !== '' && $seriesFrom !== '' && $seriesTo !== '' && $seriesFrom !== $seriesTo) {
            $replaceCount = 0;
            $updatedNo = str_replace($seriesFrom, $seriesTo, $documentNo, $replaceCount);
            if ($replaceCount > 0) {
                $aviz['document_number'] = $updatedNo;
                $changes[] = 'Serie aviz inlocuita: ' . $seriesFrom . ' -> ' . $seriesTo . '.';
            }
        } elseif ($documentNo === '' && $seriesTo !== '') {
            $aviz['document_number'] = $seriesTo;
        }

        $html = $this->rewriteService->buildAvizHtml($aviz, $company, [
            'source_name' => $sourceName,
            'series_from' => $seriesFrom,
            'series_to' => $seriesTo,
            'changes' => $changes,
        ]);

        $pdfBinary = $this->pdfService->generatePdfBinaryFromHtml($html, 'prelucrare-aviz');
        if ($pdfBinary === '') {
            $downloadName = $this->buildDownloadName($sourceName, 'txt');
            header('Content-Type: text/plain; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            header('X-Content-Type-Options: nosniff');
            echo $selectedText;
            exit;
        }

        $downloadName = $this->buildDownloadName($sourceName, 'pdf');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . strlen($pdfBinary));
        header('X-Content-Type-Options: nosniff');
        echo $pdfBinary;
        exit;
    }

    private function requireToolsAccess(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (!$user || !$user->hasRole(['super_admin', 'admin', 'operator'])) {
            Response::abort(403, 'Acces interzis.');
        }
    }

    private function isValidUpload(mixed $file): bool
    {
        if (!is_array($file)) {
            return false;
        }

        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return false;
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_UPLOAD_BYTES) {
            return false;
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return false;
        }

        $originalName = strtolower((string) ($file['name'] ?? ''));
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        if ($extension !== 'pdf') {
            return false;
        }

        $mime = $this->detectMimeType($tmpPath);
        if ($mime !== '' && $mime !== 'application/pdf' && $mime !== 'application/x-pdf' && $mime !== 'application/octet-stream') {
            return false;
        }

        return true;
    }

    private function detectMimeType(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }

        if (!function_exists('finfo_open')) {
            return '';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return '';
        }

        $mime = (string) finfo_file($finfo, $path);
        finfo_close($finfo);

        return strtolower(trim($mime));
    }

    private function loadCompanyFromSettings(): array
    {
        return [
            'denumire' => (string) $this->settings->get('company.denumire', ''),
            'cui' => (string) $this->settings->get('company.cui', ''),
            'nr_reg_comertului' => (string) $this->settings->get('company.nr_reg_comertului', ''),
            'adresa' => (string) $this->settings->get('company.adresa', ''),
            'localitate' => (string) $this->settings->get('company.localitate', ''),
            'judet' => (string) $this->settings->get('company.judet', ''),
            'tara' => (string) $this->settings->get('company.tara', 'Romania'),
            'email' => (string) $this->settings->get('company.email', ''),
            'telefon' => (string) $this->settings->get('company.telefon', ''),
            'banca' => (string) $this->settings->get('company.banca', ''),
            'iban' => (string) $this->settings->get('company.iban', ''),
        ];
    }

    private function buildDownloadName(string $sourceName, string $extension): string
    {
        $base = strtolower(pathinfo($sourceName, PATHINFO_FILENAME));
        $base = preg_replace('/[^a-z0-9_-]+/', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'document';
        }

        return $base . '-deon.' . $extension;
    }

    private function shouldUseOcrFallback(array $aviz, string $sourceText): bool
    {
        $items = is_array($aviz['items'] ?? null) ? $aviz['items'] : [];
        $itemCount = count($items);
        $totalWithVat = (float) ($aviz['total_with_vat'] ?? 0);
        $totalWithoutVat = (float) ($aviz['total_without_vat'] ?? 0);
        $vatHints = preg_match_all('/\(?[0-9]{1,2}(?:[\.,][0-9]{1,2})?%\)?/', $sourceText);

        if ($itemCount === 0) {
            return true;
        }
        if ($totalWithVat <= 0 || $totalWithoutVat <= 0) {
            return true;
        }
        if ($itemCount < 2 && $vatHints !== false && $vatHints > 1) {
            return true;
        }

        $first = $items[0] ?? [];
        $firstUnitPrice = (float) ($first['unit_price'] ?? 0);
        $firstTotal = (float) ($first['total_with_vat'] ?? 0);
        if ($itemCount === 1 && ($firstUnitPrice <= 0 || $firstTotal <= 0) && $vatHints !== false && $vatHints > 1) {
            return true;
        }

        return false;
    }

    private function scoreParsedAviz(array $aviz, string $sourceText): int
    {
        $items = is_array($aviz['items'] ?? null) ? $aviz['items'] : [];
        $score = count($items) * 100;

        $totalWithoutVat = (float) ($aviz['total_without_vat'] ?? 0);
        $totalWithVat = (float) ($aviz['total_with_vat'] ?? 0);
        if ($totalWithoutVat > 0) {
            $score += 80;
        }
        if ($totalWithVat > 0) {
            $score += 80;
        }

        $validRows = 0;
        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $total = (float) ($item['total_with_vat'] ?? 0);
            $name = trim((string) ($item['name'] ?? ''));
            if ($name !== '' && preg_match('/^Pozitie\s+\d+$/i', $name) !== 1) {
                $score += 10;
            } elseif ($name !== '') {
                $score -= 20;
            }
            if ($qty > 0 && $price > 0 && $total > 0) {
                $validRows++;
            }
        }
        $score += ($validRows * 25);

        $vatHints = preg_match_all('/\(?[0-9]{1,2}(?:[\.,][0-9]{1,2})?%\)?/', $sourceText);
        if ($vatHints !== false && $vatHints > 0) {
            $score += min(200, $vatHints * 5);
        }

        return $score;
    }
}
