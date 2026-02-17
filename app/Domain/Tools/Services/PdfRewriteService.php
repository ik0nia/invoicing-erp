<?php

namespace App\Domain\Tools\Services;

class PdfRewriteService
{
    public function extractText(string $pdfAbsolutePath): string
    {
        if (!is_file($pdfAbsolutePath) || !is_readable($pdfAbsolutePath)) {
            return '';
        }

        $binary = @file_get_contents($pdfAbsolutePath);
        if (!is_string($binary) || $binary === '') {
            return '';
        }

        $chunks = [];
        foreach ($this->collectStreamPayloads($binary) as $payload) {
            foreach ($this->decodeStreamPayload($payload) as $decodedStream) {
                $textChunk = trim($this->extractTextOperators($decodedStream));
                if ($textChunk !== '') {
                    $chunks[] = $textChunk;
                }
            }
        }

        $extracted = trim(implode("\n", $chunks));
        if ($extracted === '') {
            $extracted = $this->fallbackExtractLiteralStrings($binary);
        }

        return $this->normalizeExtractedText($extracted);
    }

    public function rewriteSupplierData(
        string $sourceText,
        array $company,
        string $seriesFrom = 'A-MVN',
        string $seriesTo = 'A-DEON'
    ): array {
        $company = $this->normalizeCompany($company);
        $text = $this->normalizeExtractedText($sourceText);
        $changes = [];

        $seriesFrom = trim($seriesFrom);
        $seriesTo = trim($seriesTo);
        if ($seriesFrom !== '' && $seriesTo !== '' && $seriesFrom !== $seriesTo) {
            $replaceCount = 0;
            $text = str_replace($seriesFrom, $seriesTo, $text, $replaceCount);
            if ($replaceCount > 0) {
                $changes[] = 'Prefix "' . $seriesFrom . '" inlocuit cu "' . $seriesTo . '" (' . $replaceCount . ' aparitii).';
            }
        }

        $lines = preg_split('/\n/u', $text) ?: [];
        $result = [];
        $inSupplierSection = false;
        $skipAddressContinuation = false;
        $nameReplaced = false;
        $cuiReplaced = false;
        $regComReplaced = false;
        $addressReplaced = false;
        $countyReplaced = false;
        $countryReplaced = false;
        $emailReplaced = false;
        $phoneReplaced = false;
        $bankReplaced = false;
        $ibanReplaced = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $result[] = '';
                continue;
            }

            if (preg_match('/^Furnizor\b/i', $trimmed) === 1) {
                $inSupplierSection = true;
                $skipAddressContinuation = false;
                $result[] = 'Furnizor';
                continue;
            }

            if (preg_match('/^Client\b/i', $trimmed) === 1) {
                $inSupplierSection = false;
                $skipAddressContinuation = false;
                $result[] = 'Client';
                continue;
            }

            if (!$inSupplierSection) {
                $result[] = $trimmed;
                continue;
            }

            if ($skipAddressContinuation) {
                if ($this->isSupplierLabelLine($trimmed)) {
                    $skipAddressContinuation = false;
                } else {
                    continue;
                }
            }

            if (!$nameReplaced && $company['denumire'] !== '' && $this->isSupplierNameLine($trimmed)) {
                $result[] = $company['denumire'];
                $nameReplaced = true;
                $changes[] = 'Denumirea furnizorului a fost inlocuita.';
                continue;
            }

            if (!$cuiReplaced && stripos($trimmed, 'CUI') !== false && $company['cui'] !== '') {
                $result[] = 'CUI: ' . $company['cui'];
                $cuiReplaced = true;
                $changes[] = 'CUI furnizor inlocuit din Setari.';
                continue;
            }

            if (!$regComReplaced && preg_match('/Reg\.\s*Com/i', $trimmed) === 1 && $company['nr_reg_comertului'] !== '') {
                $result[] = 'Reg. Com.: ' . $company['nr_reg_comertului'];
                $regComReplaced = true;
                $changes[] = 'Nr. Reg. Comertului inlocuit din Setari.';
                continue;
            }

            if (!$addressReplaced && preg_match('/^Adresa\b/i', $trimmed) === 1 && $company['adresa'] !== '') {
                $result[] = 'Adresa: ' . $this->buildCompanyAddress($company);
                $addressReplaced = true;
                $skipAddressContinuation = true;
                $changes[] = 'Adresa furnizorului inlocuita din Setari.';
                continue;
            }

            if (!$countyReplaced && preg_match('/^Judet\b/i', $trimmed) === 1 && $company['judet'] !== '') {
                $result[] = 'Judet: ' . $company['judet'];
                $countyReplaced = true;
                $changes[] = 'Judet furnizor inlocuit din Setari.';
                continue;
            }

            if (!$countryReplaced && preg_match('/^Tara\b/i', $trimmed) === 1 && $company['tara'] !== '') {
                $result[] = 'Tara: ' . $company['tara'];
                $countryReplaced = true;
                $changes[] = 'Tara furnizor inlocuita din Setari.';
                continue;
            }

            if (!$emailReplaced && preg_match('/^Email\b/i', $trimmed) === 1 && $company['email'] !== '') {
                $result[] = 'Email: ' . $company['email'];
                $emailReplaced = true;
                $changes[] = 'Email furnizor inlocuit din Setari.';
                continue;
            }

            if (!$phoneReplaced && preg_match('/^Telefon\b/i', $trimmed) === 1 && $company['telefon'] !== '') {
                $result[] = 'Telefon: ' . $company['telefon'];
                $phoneReplaced = true;
                $changes[] = 'Telefon furnizor inlocuit din Setari.';
                continue;
            }

            if ($this->looksLikeIban($trimmed)) {
                if ($company['iban'] !== '' && !$ibanReplaced) {
                    $result[] = $company['iban'];
                    $ibanReplaced = true;
                    $changes[] = 'IBAN furnizor inlocuit din Setari.';
                }
                continue;
            }

            if ($this->looksLikeBankLine($trimmed)) {
                if ($company['banca'] !== '' && !$bankReplaced) {
                    $result[] = $company['banca'];
                    $bankReplaced = true;
                    $changes[] = 'Banca furnizor inlocuita din Setari.';
                }
                continue;
            }

            if (stripos($trimmed, 'mivinia') !== false && $company['denumire'] !== '') {
                $result[] = $company['denumire'];
                if (!$nameReplaced) {
                    $changes[] = 'Denumirea furnizorului a fost inlocuita.';
                }
                $nameReplaced = true;
                continue;
            }

            $result[] = $trimmed;
        }

        $finalText = $this->normalizeExtractedText(implode("\n", $result));

        return [
            'text' => $finalText,
            'changes' => array_values(array_unique($changes)),
        ];
    }

    public function buildPrintableHtml(string $rewrittenText, array $company, array $meta = []): string
    {
        $company = $this->normalizeCompany($company);
        $rewrittenText = $this->normalizeExtractedText($rewrittenText);
        $sourceName = trim((string) ($meta['source_name'] ?? 'document.pdf'));
        $seriesFrom = trim((string) ($meta['series_from'] ?? 'A-MVN'));
        $seriesTo = trim((string) ($meta['series_to'] ?? 'A-DEON'));
        $changes = $meta['changes'] ?? [];
        if (!is_array($changes)) {
            $changes = [];
        }

        $changesHtml = '';
        if (!empty($changes)) {
            $rows = [];
            foreach ($changes as $change) {
                $rows[] = '<li>' . htmlspecialchars((string) $change, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $changesHtml = '<div class="box"><h3>Modificari aplicate</h3><ul>' . implode('', $rows) . '</ul></div>';
        }

        $companyRows = [
            'Denumire' => $company['denumire'],
            'CUI' => $company['cui'],
            'Reg. Com.' => $company['nr_reg_comertului'],
            'Adresa' => $this->buildCompanyAddress($company),
            'Email' => $company['email'],
            'Telefon' => $company['telefon'],
            'Banca' => $company['banca'],
            'IBAN' => $company['iban'],
        ];

        $companyTableRows = '';
        foreach ($companyRows as $label => $value) {
            $companyTableRows .= '<tr>'
                . '<td class="label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . htmlspecialchars($value !== '' ? $value : '-', ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
        }

        return '<!doctype html>'
            . '<html lang="ro"><head><meta charset="utf-8">'
            . '<style>'
            . 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;color:#0f172a;margin:20px;}'
            . 'h1{font-size:20px;margin:0 0 8px 0;}'
            . 'h2{font-size:14px;margin:16px 0 8px 0;}'
            . 'h3{font-size:13px;margin:0 0 6px 0;}'
            . '.muted{color:#334155;font-size:11px;}'
            . '.box{border:1px solid #cbd5e1;border-radius:8px;padding:10px;margin-top:10px;background:#f8fafc;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . 'td{border:1px solid #e2e8f0;padding:6px 8px;vertical-align:top;}'
            . 'td.label{width:180px;font-weight:700;background:#f1f5f9;}'
            . 'pre{white-space:pre-wrap;border:1px solid #cbd5e1;border-radius:8px;padding:10px;background:#ffffff;line-height:1.35;}'
            . 'ul{margin:0;padding-left:18px;}'
            . '</style></head><body>'
            . '<h1>Prelucrare PDF aviz - emitent DEON</h1>'
            . '<div class="muted">Fisier sursa: '
            . htmlspecialchars($sourceName, ENT_QUOTES, 'UTF-8')
            . '</div>'
            . '<div class="muted">Prefix serie inlocuit: '
            . htmlspecialchars($seriesFrom, ENT_QUOTES, 'UTF-8')
            . ' -> '
            . htmlspecialchars($seriesTo, ENT_QUOTES, 'UTF-8')
            . '</div>'
            . '<div class="muted">Generat la: '
            . htmlspecialchars(date('d.m.Y H:i:s'), ENT_QUOTES, 'UTF-8')
            . '</div>'
            . '<div class="box"><h3>Date emitent folosite din Setari</h3><table>'
            . $companyTableRows
            . '</table></div>'
            . $changesHtml
            . '<h2>Continut extras si prelucrat</h2>'
            . '<pre>' . htmlspecialchars($rewrittenText, ENT_QUOTES, 'UTF-8') . '</pre>'
            . '</body></html>';
    }

    private function collectStreamPayloads(string $pdfBinary): array
    {
        $payloads = [];
        $matches = [];

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdfBinary, $matches) > 0) {
            foreach ($matches[1] as $match) {
                if (is_string($match) && $match !== '') {
                    $payloads[] = $match;
                }
            }
        }

        if (empty($payloads)) {
            $fallbackMatches = [];
            if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $pdfBinary, $fallbackMatches) > 0) {
                foreach ($fallbackMatches[1] as $match) {
                    if (is_string($match) && $match !== '') {
                        $payloads[] = $match;
                    }
                }
            }
        }

        return $payloads;
    }

    private function decodeStreamPayload(string $payload): array
    {
        $decoded = [];
        $this->appendCandidate($decoded, $payload);
        $this->appendCandidate($decoded, ltrim($payload, "\r\n"));

        $inflatedCandidates = [];
        foreach ($decoded as $candidate) {
            $raw = ltrim($candidate, "\r\n");
            if ($raw === '') {
                continue;
            }

            if (function_exists('zlib_decode')) {
                $value = @zlib_decode($raw);
                if (is_string($value) && $value !== '') {
                    $inflatedCandidates[] = $value;
                }
            }

            $value = @gzuncompress($raw);
            if (is_string($value) && $value !== '') {
                $inflatedCandidates[] = $value;
            }

            $value = @gzinflate($raw);
            if (is_string($value) && $value !== '') {
                $inflatedCandidates[] = $value;
            }
        }

        foreach ($inflatedCandidates as $candidate) {
            $this->appendCandidate($decoded, $candidate);
        }

        return $decoded;
    }

    private function appendCandidate(array &$items, string $value): void
    {
        if ($value === '') {
            return;
        }
        $key = md5($value);
        if (isset($items[$key])) {
            return;
        }
        $items[$key] = $value;
    }

    private function extractTextOperators(string $streamContent): string
    {
        if ($streamContent === '') {
            return '';
        }

        $parts = [];
        $matches = [];

        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*Tj/s', $streamContent, $matches) > 0) {
            foreach ($matches[0] as $match) {
                if (preg_match('/\((?:\\\\.|[^\\\\)])*\)/s', $match, $literalMatch) === 1) {
                    $parts[] = $this->decodeLiteralPdfString($literalMatch[0]);
                }
            }
        }

        $arrayMatches = [];
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $streamContent, $arrayMatches) > 0) {
            foreach ($arrayMatches[1] as $arrayBody) {
                $lineParts = [];
                $literalMatches = [];
                if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', (string) $arrayBody, $literalMatches) > 0) {
                    foreach ($literalMatches[0] as $literal) {
                        $decoded = $this->decodeLiteralPdfString($literal);
                        if ($decoded !== '') {
                            $lineParts[] = $decoded;
                        }
                    }
                }
                if (!empty($lineParts)) {
                    $parts[] = implode('', $lineParts);
                }
            }
        }

        $hexMatches = [];
        if (preg_match_all('/<([0-9A-Fa-f]{2,})>\s*Tj/s', $streamContent, $hexMatches) > 0) {
            foreach ($hexMatches[1] as $hexBody) {
                $decoded = $this->decodeHexPdfString((string) $hexBody);
                if ($decoded !== '') {
                    $parts[] = $decoded;
                }
            }
        }

        $parts = array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $parts), static function ($value): bool {
            return $value !== '';
        }));

        return implode("\n", $parts);
    }

    private function decodeLiteralPdfString(string $literal): string
    {
        $literal = trim($literal);
        if ($literal === '') {
            return '';
        }

        if (str_starts_with($literal, '(') && str_ends_with($literal, ')')) {
            $literal = substr($literal, 1, -1);
        }

        $length = strlen($literal);
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $char = $literal[$i];
            if ($char !== '\\') {
                $result .= $char;
                continue;
            }

            $i++;
            if ($i >= $length) {
                break;
            }

            $escaped = $literal[$i];
            if ($escaped === 'n') {
                $result .= "\n";
                continue;
            }
            if ($escaped === 'r') {
                $result .= "\r";
                continue;
            }
            if ($escaped === 't') {
                $result .= "\t";
                continue;
            }
            if ($escaped === 'b') {
                $result .= "\x08";
                continue;
            }
            if ($escaped === 'f') {
                $result .= "\x0C";
                continue;
            }
            if ($escaped === '(' || $escaped === ')' || $escaped === '\\') {
                $result .= $escaped;
                continue;
            }

            if ($escaped >= '0' && $escaped <= '7') {
                $octal = $escaped;
                $step = 0;
                while ($step < 2 && $i + 1 < $length) {
                    $next = $literal[$i + 1];
                    if ($next < '0' || $next > '7') {
                        break;
                    }
                    $octal .= $next;
                    $i++;
                    $step++;
                }
                $result .= chr((int) octdec($octal));
                continue;
            }

            $result .= $escaped;
        }

        return $this->normalizeDecodedText($result);
    }

    private function decodeHexPdfString(string $hex): string
    {
        $hex = preg_replace('/[^0-9a-f]/i', '', $hex);
        if ($hex === null || $hex === '') {
            return '';
        }
        if ((strlen($hex) % 2) !== 0) {
            $hex .= '0';
        }

        $binary = @hex2bin($hex);
        if (!is_string($binary) || $binary === '') {
            return '';
        }

        return $this->normalizeDecodedText($binary);
    }

    private function normalizeDecodedText(string $raw): string
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        if (str_starts_with($raw, "\xFE\xFF")) {
            $decoded = $this->convertUtf16ToUtf8(substr($raw, 2), 'UTF-16BE');
            if ($decoded !== '') {
                $raw = $decoded;
            }
        } elseif (str_starts_with($raw, "\xFF\xFE")) {
            $decoded = $this->convertUtf16ToUtf8(substr($raw, 2), 'UTF-16LE');
            if ($decoded !== '') {
                $raw = $decoded;
            }
        }

        $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw) ?? '';

        return trim($raw);
    }

    private function convertUtf16ToUtf8(string $value, string $encoding): string
    {
        if (!function_exists('iconv')) {
            return '';
        }

        $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
        if (!is_string($converted)) {
            return '';
        }

        return $converted;
    }

    private function fallbackExtractLiteralStrings(string $pdfBinary): string
    {
        $results = [];
        $matches = [];
        if (preg_match_all('/\((?:\\\\.|[^\\\\)]){3,}\)/s', $pdfBinary, $matches) > 0) {
            foreach ($matches[0] as $match) {
                $decoded = $this->decodeLiteralPdfString((string) $match);
                if ($decoded === '') {
                    continue;
                }
                if (preg_match('/[a-zA-Z0-9]/', $decoded) !== 1) {
                    continue;
                }
                $results[] = $decoded;
            }
        }

        return implode("\n", $results);
    }

    private function normalizeExtractedText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+\n/u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function normalizeCompany(array $company): array
    {
        $normalized = [
            'denumire' => trim((string) ($company['denumire'] ?? '')),
            'cui' => trim((string) ($company['cui'] ?? '')),
            'nr_reg_comertului' => trim((string) ($company['nr_reg_comertului'] ?? '')),
            'adresa' => trim((string) ($company['adresa'] ?? '')),
            'localitate' => trim((string) ($company['localitate'] ?? '')),
            'judet' => trim((string) ($company['judet'] ?? '')),
            'tara' => trim((string) ($company['tara'] ?? 'Romania')),
            'email' => trim((string) ($company['email'] ?? '')),
            'telefon' => trim((string) ($company['telefon'] ?? '')),
            'banca' => trim((string) ($company['banca'] ?? '')),
            'iban' => strtoupper(trim((string) ($company['iban'] ?? ''))),
        ];

        if ($normalized['cui'] !== '' && !str_starts_with(strtoupper($normalized['cui']), 'RO')) {
            $digits = preg_replace('/\D+/', '', $normalized['cui']);
            if ($digits !== null && $digits !== '') {
                $normalized['cui'] = 'RO ' . $digits;
            }
        }

        return $normalized;
    }

    private function buildCompanyAddress(array $company): string
    {
        $parts = [];
        if ($company['adresa'] !== '') {
            $parts[] = $company['adresa'];
        }
        if ($company['localitate'] !== '') {
            $parts[] = 'Localitate: ' . $company['localitate'];
        }

        return implode(', ', $parts);
    }

    private function isSupplierLabelLine(string $line): bool
    {
        return preg_match('/^(CUI|Reg\.?\s*Com|Capital|Adresa|Judet|Tara|Email|Telefon|Client)\b/i', $line) === 1;
    }

    private function isSupplierNameLine(string $line): bool
    {
        if ($this->isSupplierLabelLine($line)) {
            return false;
        }
        if ($this->looksLikeIban($line)) {
            return false;
        }
        if ($this->looksLikeBankLine($line)) {
            return false;
        }
        if (preg_match('/^\d+[\s\.,]/', $line) === 1) {
            return false;
        }

        return stripos($line, 'mivinia') !== false
            || preg_match('/^[A-Z0-9\.\-\s]{4,}$/', strtoupper($line)) === 1;
    }

    private function looksLikeIban(string $line): bool
    {
        return preg_match('/^RO[0-9A-Z]{10,}$/', strtoupper(preg_replace('/\s+/', '', $line) ?? '')) === 1;
    }

    private function looksLikeBankLine(string $line): bool
    {
        $normalized = strtoupper(trim($line));
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'BANK')
            || str_contains($normalized, 'BANCA')
            || str_contains($normalized, 'TREZORER');
    }
}
