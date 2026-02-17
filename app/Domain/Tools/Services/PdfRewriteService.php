<?php

namespace App\Domain\Tools\Services;

class PdfRewriteService
{
    private const OCR_DPI = 300;
    private const OCR_LANGUAGE = 'ron+eng';

    public function extractText(string $pdfAbsolutePath): string
    {
        if (!is_file($pdfAbsolutePath) || !is_readable($pdfAbsolutePath)) {
            return '';
        }

        $binary = @file_get_contents($pdfAbsolutePath);
        if (!is_string($binary) || $binary === '') {
            return '';
        }

        $decodedStreams = $this->collectDecodedStreams($binary);
        if (empty($decodedStreams)) {
            return '';
        }

        $cmapCandidates = $this->extractCMapCandidates($decodedStreams);
        $lines = [];
        foreach ($decodedStreams as $stream) {
            if (strpos($stream, 'BT') === false || strpos($stream, 'ET') === false) {
                continue;
            }
            foreach ($this->extractTextLinesFromContentStream($stream, $cmapCandidates) as $line) {
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        $text = $this->normalizeExtractedText(implode("\n", $lines));
        if ($text === '') {
            $text = $this->fallbackExtractLiteralStrings($binary);
        }

        return $this->normalizeExtractedText($text);
    }

    public function isOcrAvailable(): bool
    {
        return $this->resolveBinaryPath('tesseract') !== ''
            && $this->resolveBinaryPath('pdftoppm') !== '';
    }

    public function extractTextWithOcr(string $pdfAbsolutePath): string
    {
        if (!$this->isOcrAvailable()) {
            return '';
        }
        if (!is_file($pdfAbsolutePath) || !is_readable($pdfAbsolutePath)) {
            return '';
        }

        $tesseract = $this->resolveBinaryPath('tesseract');
        $pdftoppm = $this->resolveBinaryPath('pdftoppm');
        if ($tesseract === '' || $pdftoppm === '') {
            return '';
        }

        $tmpRoot = rtrim(sys_get_temp_dir(), '/');
        try {
            $workDir = $tmpRoot . '/erp_ocr_' . bin2hex(random_bytes(6));
        } catch (\Throwable $exception) {
            return '';
        }
        if (!@mkdir($workDir, 0775, true) && !is_dir($workDir)) {
            return '';
        }

        $prefix = $workDir . '/page';
        $renderCommand = escapeshellarg($pdftoppm)
            . ' -r ' . (int) self::OCR_DPI
            . ' -png '
            . escapeshellarg($pdfAbsolutePath)
            . ' '
            . escapeshellarg($prefix)
            . ' 2>/dev/null';
        @shell_exec($renderCommand);

        $images = glob($prefix . '-*.png');
        if (!is_array($images) || empty($images)) {
            $this->cleanupOcrArtifacts($workDir, []);
            return '';
        }
        sort($images, SORT_NATURAL);

        $chunks = [];
        foreach ($images as $imagePath) {
            if (!is_string($imagePath) || !is_file($imagePath)) {
                continue;
            }
            $ocrCommand = escapeshellarg($tesseract)
                . ' '
                . escapeshellarg($imagePath)
                . ' stdout'
                . ' -l ' . escapeshellarg(self::OCR_LANGUAGE)
                . ' --psm 6 2>/dev/null';
            $output = @shell_exec($ocrCommand);
            if (!is_string($output) || trim($output) === '') {
                $fallbackCommand = escapeshellarg($tesseract)
                    . ' '
                    . escapeshellarg($imagePath)
                    . ' stdout -l eng --psm 6 2>/dev/null';
                $output = @shell_exec($fallbackCommand);
            }
            if (is_string($output) && trim($output) !== '') {
                $chunks[] = trim($output);
            }
        }

        $this->cleanupOcrArtifacts($workDir, $images);
        if (empty($chunks)) {
            return '';
        }

        return $this->normalizeExtractedText(implode("\n", $chunks));
    }

    public function parseAvizData(string $sourceText): array
    {
        $text = $this->normalizeExtractedText($sourceText);
        $lines = $this->splitNonEmptyLines($text);

        $documentNo = $this->extractDocumentNumber($text);
        $issueDate = $this->extractDateByLabel($text, 'Data emitere');
        $dueDate = $this->extractDateByLabel($text, 'Data scadenta');

        $clientLines = $this->extractClientLines($lines);
        $client = $this->parseClient($clientLines);

        $items = $this->parseItems($lines);
        if (empty($items)) {
            $fallbackItem = $this->parseMainItem($text, $lines);
            if ($fallbackItem['name'] === '') {
                $fallbackItem['name'] = 'Produse conform avizului sursa';
            }
            if ($fallbackItem['unit'] === '') {
                $fallbackItem['unit'] = 'BUC';
            }
            if ($fallbackItem['quantity'] <= 0) {
                $fallbackItem['quantity'] = 1.0;
            }
            $items = [$fallbackItem];
        }

        $totals = $this->parseTotals($lines, $items, $text);
        if ($totals['vat_percent'] <= 0) {
            foreach ($items as $item) {
                $vatPercent = (float) ($item['vat_percent'] ?? 0);
                if ($vatPercent > 0) {
                    $totals['vat_percent'] = $vatPercent;
                    break;
                }
            }
        }

        return [
            'document_number' => $documentNo,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'client' => $client,
            'items' => $items,
            'total_without_vat' => $totals['without_vat'],
            'total_vat' => $totals['vat'],
            'total_with_vat' => $totals['with_vat'],
            'vat_percent' => $totals['vat_percent'] > 0 ? $totals['vat_percent'] : 21.0,
            'source_text' => $text,
        ];
    }

    public function buildAvizHtml(array $aviz, array $company, array $meta = []): string
    {
        $company = $this->normalizeCompany($company);
        $client = is_array($aviz['client'] ?? null) ? $aviz['client'] : [];
        $items = is_array($aviz['items'] ?? null) ? $aviz['items'] : [];

        $documentNo = trim((string) ($aviz['document_number'] ?? 'A-DEON'));
        $issueDate = trim((string) ($aviz['issue_date'] ?? ''));
        $dueDate = trim((string) ($aviz['due_date'] ?? ''));

        $totalWithoutVat = (float) ($aviz['total_without_vat'] ?? 0);
        $totalVat = (float) ($aviz['total_vat'] ?? 0);
        $totalWithVat = (float) ($aviz['total_with_vat'] ?? 0);

        if ($totalWithoutVat <= 0 || $totalWithVat <= 0) {
            $sumWithout = 0.0;
            $sumWith = 0.0;
            foreach ($items as $row) {
                $sumWithout += (float) ($row['total_without_vat'] ?? 0);
                $sumWith += (float) ($row['total_with_vat'] ?? 0);
            }
            if ($totalWithoutVat <= 0) {
                $totalWithoutVat = $sumWithout;
            }
            if ($totalWithVat <= 0) {
                $totalWithVat = $sumWith;
            }
            if ($totalVat <= 0 && $totalWithVat > 0 && $totalWithoutVat > 0) {
                $totalVat = round($totalWithVat - $totalWithoutVat, 2);
            }
        }

        $clientAddress = [];
        if (($client['adresa'] ?? '') !== '') {
            $clientAddress[] = (string) $client['adresa'];
        }
        if (($client['localitate'] ?? '') !== '') {
            $clientAddress[] = 'Localitate: ' . (string) $client['localitate'];
        }
        $clientAddressDisplay = implode(', ', $clientAddress);

        $companyAddress = [];
        if ($company['adresa'] !== '') {
            $companyAddress[] = $company['adresa'];
        }
        if ($company['localitate'] !== '') {
            $companyAddress[] = 'Localitate: ' . $company['localitate'];
        }
        $companyAddressDisplay = implode(', ', $companyAddress);

        $itemRows = '';
        if (!empty($items)) {
            $index = 1;
            foreach ($items as $row) {
                $itemRows .= '<tr>'
                    . '<td>' . $index . '</td>'
                    . '<td>' . htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td>' . htmlspecialchars((string) ($row['unit'] ?? 'BUC'), ENT_QUOTES, 'UTF-8') . '</td>'
                    . '<td class="num">' . $this->formatNumber((float) ($row['quantity'] ?? 0), 2) . '</td>'
                    . '<td class="num">' . $this->formatNumber((float) ($row['unit_price'] ?? 0), 2) . '</td>'
                    . '<td class="num">' . $this->formatNumber((float) ($row['total_without_vat'] ?? 0), 2) . '</td>'
                    . '<td class="num">' . $this->formatNumber((float) ($row['vat_percent'] ?? 0), 2) . '%</td>'
                    . '<td class="num">' . $this->formatNumber((float) ($row['total_with_vat'] ?? 0), 2) . '</td>'
                    . '</tr>';
                $index++;
            }
        } else {
            $itemRows = '<tr><td colspan="8">Nu au fost identificate linii de produse in PDF-ul sursa.</td></tr>';
        }

        return '<!doctype html>'
            . '<html lang="ro"><head><meta charset="utf-8">'
            . '<style>'
            . 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#111827;margin:20px;}'
            . 'h1{font-size:20px;margin:0;}'
            . '.meta{font-size:11px;color:#334155;margin-top:4px;}'
            . '.box{border:1px solid #cbd5e1;border-radius:8px;padding:10px;margin-top:10px;background:#f8fafc;}'
            . '.label{font-weight:700;color:#0f172a;}'
            . '.party-table{width:100%;border-collapse:separate;border-spacing:10px 0;margin-top:12px;}'
            . '.party-table td{width:50%;vertical-align:top;border:none;padding:0;}'
            . '.party-card{border:1px solid #cbd5e1;border-radius:8px;padding:10px;background:#f8fafc;}'
            . '.items-wrap{margin-top:12px;width:90%;max-width:940px;}'
            . '.items-table{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . '.items-table th,.items-table td{border:1px solid #cbd5e1;padding:6px;vertical-align:top;}'
            . '.items-table th{background:#f1f5f9;text-align:left;white-space:normal;}'
            . '.items-table td{white-space:normal;word-break:normal;overflow-wrap:break-word;}'
            . '.num{text-align:right;}'
            . '.items-table th.num,.items-table td.num{white-space:nowrap;word-break:normal;overflow-wrap:normal;}'
            . '.items-table th:nth-child(2),.items-table td:nth-child(2){width:38%;}'
            . '.items-table th:nth-child(1),.items-table td:nth-child(1),'
            . '.items-table th:nth-child(3),.items-table td:nth-child(3),'
            . '.items-table th:nth-child(4),.items-table td:nth-child(4),'
            . '.items-table th:nth-child(7),.items-table td:nth-child(7){text-align:center;}'
            . '.totals{margin-top:10px;width:340px;margin-left:0;border-collapse:collapse;}'
            . '.totals th,.totals td{border:1px solid #cbd5e1;padding:6px;}'
            . '.totals th{background:#f1f5f9;text-align:left;}'
            . '.footer{margin-top:16px;font-size:10px;color:#475569;}'
            . 'ul{margin:6px 0 0 18px;padding:0;}'
            . '</style></head><body>'
            . '<div><h1>AVIZ INSOTIRE MARFA ' . htmlspecialchars($documentNo, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<div class="meta">Data emitere: ' . htmlspecialchars($issueDate, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div class="meta">Data scadenta: ' . htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div>'
            . '<table class="party-table"><tr>'
            . '<td><div class="party-card">'
            . '<div class="label">Furnizor</div>'
            . '<div>' . htmlspecialchars($company['denumire'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>CUI: ' . htmlspecialchars($company['cui'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>Reg. Com.: ' . htmlspecialchars($company['nr_reg_comertului'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>Adresa: ' . htmlspecialchars($companyAddressDisplay, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>Judet: ' . htmlspecialchars($company['judet'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>Tara: ' . htmlspecialchars($company['tara'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>Banca: ' . htmlspecialchars($company['banca'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>IBAN: ' . htmlspecialchars($company['iban'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>Email: ' . htmlspecialchars($company['email'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>Telefon: ' . htmlspecialchars($company['telefon'], ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div></td>'
            . '<td><div class="party-card">'
            . '<div class="label">Client</div>'
            . '<div>' . htmlspecialchars((string) ($client['denumire'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>CUI: ' . htmlspecialchars((string) ($client['cui'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>Reg. Com.: ' . htmlspecialchars((string) ($client['reg_com'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>Adresa: ' . htmlspecialchars($clientAddressDisplay, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>Judet: ' . htmlspecialchars((string) ($client['judet'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div>Tara: ' . htmlspecialchars((string) ($client['tara'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>'
            . '</div></td>'
            . '</tr></table>'
            . '<div class="items-wrap"><table class="items-table">'
            . '<colgroup>'
            . '<col style="width:3%">'
            . '<col style="width:38%">'
            . '<col style="width:4%">'
            . '<col style="width:5%">'
            . '<col style="width:8%">'
            . '<col style="width:7%">'
            . '<col style="width:5%">'
            . '<col style="width:30%">'
            . '</colgroup>'
            . '<thead><tr>'
            . '<th>#</th>'
            . '<th>Produs / serviciu</th>'
            . '<th>U.M.</th>'
            . '<th class="num">Cant.</th>'
            . '<th class="num">Pret unitar</th>'
            . '<th class="num">Valoare</th>'
            . '<th class="num">TVA%</th>'
            . '<th class="num">Total</th>'
            . '</tr></thead><tbody>'
            . $itemRows
            . '</tbody></table></div>'
            . '<table class="totals">'
            . '<tr><th>Total fara TVA</th><td class="num">' . $this->formatNumber($totalWithoutVat, 2) . ' RON</td></tr>'
            . '<tr><th>Total TVA</th><td class="num">' . $this->formatNumber($totalVat, 2) . ' RON</td></tr>'
            . '<tr><th>Total cu TVA</th><td class="num">' . $this->formatNumber($totalWithVat, 2) . ' RON</td></tr>'
            . '</table>'
            . '<div class="footer">Factura circula fara semnatura si stampila cf. art. V alin. (2) din OG 17/2015 si art. 319 alin. (29) din Legea 227/2015.</div>'
            . '</body></html>';
    }

    private function collectDecodedStreams(string $pdfBinary): array
    {
        $streams = [];
        $matches = [];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdfBinary, $matches) <= 0) {
            return [];
        }

        foreach ($matches[1] as $rawPayload) {
            if (!is_string($rawPayload) || $rawPayload === '') {
                continue;
            }
            foreach ($this->decodePayloadCandidates($rawPayload) as $decoded) {
                $key = md5($decoded);
                if (!isset($streams[$key])) {
                    $streams[$key] = $decoded;
                }
            }
        }

        return array_values($streams);
    }

    private function decodePayloadCandidates(string $payload): array
    {
        $result = [];
        $this->appendUnique($result, $payload);
        $payloadTrimmed = ltrim($payload, "\r\n");
        $this->appendUnique($result, $payloadTrimmed);

        $decoded = [];
        foreach ($result as $candidate) {
            $raw = ltrim($candidate, "\r\n");
            if ($raw === '') {
                continue;
            }
            if (function_exists('zlib_decode')) {
                $value = @zlib_decode($raw);
                if (is_string($value) && $value !== '') {
                    $decoded[] = $value;
                }
            }
            $value = @gzuncompress($raw);
            if (is_string($value) && $value !== '') {
                $decoded[] = $value;
            }
            $value = @gzinflate($raw);
            if (is_string($value) && $value !== '') {
                $decoded[] = $value;
            }
        }

        foreach ($decoded as $value) {
            $this->appendUnique($result, $value);
        }

        return array_values($result);
    }

    private function appendUnique(array &$store, string $value): void
    {
        if ($value === '') {
            return;
        }
        $store[md5($value)] = $value;
    }

    private function extractCMapCandidates(array $streams): array
    {
        $maps = [];
        foreach ($streams as $stream) {
            if (!is_string($stream) || stripos($stream, 'begincmap') === false) {
                continue;
            }
            $map = $this->parseCMapFromStream($stream);
            if (!empty($map)) {
                $maps[] = $map;
            }
        }

        usort($maps, static function (array $a, array $b): int {
            return count($b) <=> count($a);
        });

        return $maps;
    }

    private function parseCMapFromStream(string $stream): array
    {
        $map = [];
        $lines = preg_split('/\r\n|\r|\n/', $stream) ?: [];
        $countLines = count($lines);
        $index = 0;
        while ($index < $countLines) {
            $line = trim((string) $lines[$index]);

            if (preg_match('/^(\d+)\s+beginbfchar$/i', $line, $match) === 1) {
                $rows = (int) $match[1];
                $index++;
                for ($r = 0; $r < $rows && $index < $countLines; $r++, $index++) {
                    $row = trim((string) $lines[$index]);
                    if (preg_match('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $row, $charMatch) !== 1) {
                        continue;
                    }
                    $src = strtoupper($charMatch[1]);
                    $dst = $this->unicodeHexToUtf8($charMatch[2]);
                    if ($src !== '' && $dst !== '') {
                        $map[$src] = $dst;
                    }
                }
                continue;
            }

            if (preg_match('/^(\d+)\s+beginbfrange$/i', $line, $match) === 1) {
                $rows = (int) $match[1];
                $index++;
                for ($r = 0; $r < $rows && $index < $countLines; $r++, $index++) {
                    $row = trim((string) $lines[$index]);
                    if ($row === '') {
                        continue;
                    }
                    if (str_contains($row, '[') && !str_contains($row, ']')) {
                        while ($index + 1 < $countLines) {
                            $index++;
                            $row .= ' ' . trim((string) $lines[$index]);
                            if (str_contains($row, ']')) {
                                break;
                            }
                        }
                    }
                    if (
                        preg_match('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[(.+)\]\s*$/', $row, $rangeListMatch) === 1
                    ) {
                        $start = (int) hexdec($rangeListMatch[1]);
                        $values = [];
                        preg_match_all('/<([0-9A-Fa-f]+)>/', $rangeListMatch[3], $valueMatches);
                        if (!empty($valueMatches[1])) {
                            $values = $valueMatches[1];
                        }
                        foreach ($values as $offset => $hexValue) {
                            $src = strtoupper(str_pad(dechex($start + $offset), strlen($rangeListMatch[1]), '0', STR_PAD_LEFT));
                            $dst = $this->unicodeHexToUtf8((string) $hexValue);
                            if ($dst !== '') {
                                $map[$src] = $dst;
                            }
                        }
                        continue;
                    }

                    if (
                        preg_match('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>$/', $row, $rangeMatch) === 1
                    ) {
                        $start = (int) hexdec($rangeMatch[1]);
                        $end = (int) hexdec($rangeMatch[2]);
                        $destStart = (int) hexdec($rangeMatch[3]);
                        $srcLen = strlen($rangeMatch[1]);
                        for ($code = $start; $code <= $end; $code++) {
                            $src = strtoupper(str_pad(dechex($code), $srcLen, '0', STR_PAD_LEFT));
                            $destCode = $destStart + ($code - $start);
                            $dst = $this->unicodeCodepointToUtf8($destCode);
                            if ($dst !== '') {
                                $map[$src] = $dst;
                            }
                        }
                    }
                }
                continue;
            }

            $index++;
        }

        return $map;
    }

    private function extractTextLinesFromContentStream(string $stream, array $cmapCandidates): array
    {
        $lines = [];
        $blocks = [];
        if (preg_match_all('/BT(.*?)ET/s', $stream, $blocks) <= 0) {
            return [];
        }

        foreach ($blocks[1] as $block) {
            $current = '';
            $tokens = [];
            if (
                preg_match_all(
                    '/\[(?:[^\]]*)\]\s*TJ|<[^>]+>\s*Tj|\((?:\\\\.|[^\\\\)])*\)\s*Tj|-?\d+(?:\.\d+)?\s+-?\d+(?:\.\d+)?\s+Td|T\*/s',
                    (string) $block,
                    $tokens
                ) <= 0
            ) {
                continue;
            }

            foreach ($tokens[0] as $tokenRaw) {
                $token = trim((string) $tokenRaw);
                if ($token === '') {
                    continue;
                }

                if ($token === 'T*') {
                    $this->flushLine($lines, $current);
                    continue;
                }

                if (str_ends_with($token, 'Td')) {
                    if (preg_match('/(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+Td$/', $token, $tdMatch) === 1) {
                        $dy = (float) $tdMatch[2];
                        if (abs($dy) > 0.01) {
                            $this->flushLine($lines, $current);
                        }
                    }
                    continue;
                }

                if (str_ends_with($token, 'TJ')) {
                    $arrayContent = trim(substr($token, 0, -2));
                    $this->appendDecodedArrayText($current, $arrayContent, $cmapCandidates);
                    continue;
                }

                if (str_ends_with($token, 'Tj')) {
                    $value = trim(substr($token, 0, -2));
                    if (str_starts_with($value, '<') && str_ends_with($value, '>')) {
                        $current .= $this->decodeHexToken(substr($value, 1, -1), $cmapCandidates);
                    } elseif (str_starts_with($value, '(') && str_ends_with($value, ')')) {
                        $current .= $this->decodeLiteralPdfString($value);
                    }
                }
            }

            $this->flushLine($lines, $current);
        }

        return $lines;
    }

    private function appendDecodedArrayText(string &$current, string $arrayContent, array $cmapCandidates): void
    {
        $parts = [];
        preg_match_all('/<([0-9A-Fa-f]+)>|\((?:\\\\.|[^\\\\)])*\)|-?\d+(?:\.\d+)?/', $arrayContent, $parts);
        if (empty($parts[0])) {
            return;
        }

        foreach ($parts[0] as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<' && str_ends_with($part, '>')) {
                $current .= $this->decodeHexToken(substr($part, 1, -1), $cmapCandidates);
                continue;
            }
            if ($part[0] === '(' && str_ends_with($part, ')')) {
                $current .= $this->decodeLiteralPdfString($part);
                continue;
            }
            if (is_numeric($part)) {
                $adjust = (float) $part;
                if ($adjust < -120 && $current !== '' && !str_ends_with($current, ' ')) {
                    $current .= ' ';
                }
            }
        }
    }

    private function decodeHexToken(string $hex, array $cmapCandidates): string
    {
        $hex = strtoupper(trim(preg_replace('/[^0-9A-Fa-f]/', '', $hex) ?? ''));
        if ($hex === '') {
            return '';
        }

        $bestText = '';
        $bestScore = -PHP_INT_MAX;
        foreach ($cmapCandidates as $map) {
            if (!is_array($map) || empty($map)) {
                continue;
            }
            $decoded = $this->decodeHexWithMap($hex, $map);
            $score = $this->scoreDecodedText($decoded['text'], $decoded['decoded'], $decoded['missing']);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestText = $decoded['text'];
            }
        }

        if ($bestText !== '') {
            return $bestText;
        }

        $raw = @hex2bin((strlen($hex) % 2) === 0 ? $hex : ($hex . '0'));
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        if (str_starts_with($raw, "\xFE\xFF")) {
            return $this->unicodeHexToUtf8(substr($hex, 4));
        }

        if (strpos($raw, "\x00") !== false) {
            $converted = $this->convertUtf16ToUtf8($raw, 'UTF-16BE');
            if ($converted !== '') {
                return $converted;
            }
        }

        return $this->stripControlChars($raw);
    }

    private function decodeHexWithMap(string $hex, array $map): array
    {
        $keys = array_keys($map);
        if (empty($keys)) {
            return ['text' => '', 'decoded' => 0, 'missing' => 0];
        }

        $codeLen = strlen((string) $keys[0]);
        if ($codeLen <= 0) {
            $codeLen = 4;
        }
        if ($codeLen % 2 !== 0) {
            $codeLen++;
        }
        if (strlen($hex) % $codeLen !== 0) {
            if ($codeLen !== 2 && (strlen($hex) % 2 === 0)) {
                $codeLen = 2;
            }
        }

        $decoded = 0;
        $missing = 0;
        $text = '';
        for ($offset = 0; $offset < strlen($hex); $offset += $codeLen) {
            $chunk = substr($hex, $offset, $codeLen);
            if ($chunk === '' || strlen($chunk) < $codeLen) {
                continue;
            }
            if (isset($map[$chunk])) {
                $text .= (string) $map[$chunk];
                $decoded++;
            } else {
                $missing++;
            }
        }

        return ['text' => $text, 'decoded' => $decoded, 'missing' => $missing];
    }

    private function scoreDecodedText(string $text, int $decoded, int $missing): int
    {
        if ($text === '' || $decoded <= 0) {
            return -10000;
        }
        $letters = preg_match_all('/[A-Za-z]/', $text);
        $digits = preg_match_all('/[0-9]/', $text);
        $readable = preg_match_all('/[A-Za-z0-9 \.\,\:\-\(\)\/%]/', $text);

        return ($decoded * 5) + ($letters * 3) + ($digits * 2) + ($readable) - ($missing * 2);
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

        $result = '';
        $length = strlen($literal);
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

        return $this->stripControlChars($result);
    }

    private function flushLine(array &$lines, string &$current): void
    {
        $line = trim($this->normalizeSpacing($current));
        if ($line !== '') {
            $lines[] = $line;
        }
        $current = '';
    }

    private function normalizeSpacing(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private function fallbackExtractLiteralStrings(string $pdfBinary): string
    {
        $result = [];
        $matches = [];
        if (preg_match_all('/\((?:\\\\.|[^\\\\)]){3,}\)/s', $pdfBinary, $matches) <= 0) {
            return '';
        }
        foreach ($matches[0] as $match) {
            $decoded = $this->decodeLiteralPdfString((string) $match);
            if ($decoded === '') {
                continue;
            }
            if (preg_match('/[A-Za-z0-9]/', $decoded) !== 1) {
                continue;
            }
            $result[] = $decoded;
        }

        return $this->normalizeExtractedText(implode("\n", $result));
    }

    private function normalizeExtractedText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = $this->stripControlChars($text);
        $text = preg_replace("/[ \t]+\n/u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function stripControlChars(string $value): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
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
        return $this->stripControlChars($converted);
    }

    private function unicodeHexToUtf8(string $hex): string
    {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', $hex);
        if ($hex === null || $hex === '') {
            return '';
        }
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        $binary = @hex2bin($hex);
        if (!is_string($binary) || $binary === '') {
            return '';
        }

        if (strlen($binary) === 1) {
            return chr(ord($binary));
        }

        $converted = $this->convertUtf16ToUtf8($binary, 'UTF-16BE');
        if ($converted !== '') {
            return $converted;
        }

        return $this->stripControlChars($binary);
    }

    private function unicodeCodepointToUtf8(int $codepoint): string
    {
        if ($codepoint < 0 || $codepoint > 0x10FFFF) {
            return '';
        }
        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }
        $hex = strtoupper(dechex($codepoint));
        if (strlen($hex) % 4 !== 0) {
            $hex = str_pad($hex, (int) (ceil(strlen($hex) / 4) * 4), '0', STR_PAD_LEFT);
        }
        return $this->unicodeHexToUtf8($hex);
    }

    private function splitNonEmptyLines(string $text): array
    {
        $parts = preg_split('/\n/', $text) ?: [];
        $lines = [];
        foreach ($parts as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $lines[] = $line;
        }
        return $lines;
    }

    private function extractDocumentNumber(string $text): string
    {
        if (preg_match('/\b(A-[A-Z0-9]+)\s+([0-9]{1,10})\b/', $text, $match) === 1) {
            return trim($match[1] . ' ' . $match[2]);
        }

        if (preg_match('/AVIZ\s+INSOTIRE\s+MARFA\s+([A-Z0-9\- ]+)/i', $text, $match) === 1) {
            return trim((string) $match[1]);
        }

        return 'A-DEON';
    }

    private function extractDateByLabel(string $text, string $label): string
    {
        $label = preg_quote($label, '/');
        if (preg_match('/' . $label . '\s*:\s*([0-9]{2}\.[0-9]{2}\.[0-9]{4})/i', $text, $match) === 1) {
            return trim((string) $match[1]);
        }
        return '';
    }

    private function extractClientLines(array $lines): array
    {
        $start = -1;
        $end = count($lines);
        foreach ($lines as $index => $line) {
            if (preg_match('/^Client$/i', $line) === 1) {
                $start = $index + 1;
                continue;
            }
            if ($start >= 0 && ($line === '#' || stripos($line, 'Produs / serviciu') !== false)) {
                $end = $index;
                break;
            }
        }

        if ($start < 0) {
            return [];
        }

        return array_slice($lines, $start, max(0, $end - $start));
    }

    private function parseClient(array $clientLines): array
    {
        $client = [
            'denumire' => '',
            'cui' => '',
            'reg_com' => '',
            'tara' => '',
            'judet' => '',
            'localitate' => '',
            'adresa' => '',
        ];

        if (empty($clientLines)) {
            return $client;
        }

        foreach ($clientLines as $line) {
            if ($client['denumire'] !== '') {
                break;
            }
            if (preg_match('/^(CUI|Reg\.?Com|Reg\.?\s*Com|Tara|Judet|Localitate|Adresa)\b/i', $line) === 1) {
                continue;
            }
            $client['denumire'] = trim($line);
        }

        $client['cui'] = $this->extractClientValue($clientLines, '/^CUI\b/i', '/(?:RO\s*)?[0-9]{4,}/');
        $client['reg_com'] = $this->extractClientValue($clientLines, '/^Reg\.?\s*Com\b/i', '/[A-Z]{0,2}\d{2,}[A-Z0-9\/]*/i');
        $client['tara'] = $this->extractClientValue($clientLines, '/^Tara\b/i', '/[A-Z][A-Z ]{2,}/');
        $client['judet'] = $this->extractClientValue($clientLines, '/^Judet\b/i', '/[A-Z][A-Z ]{2,}/');
        $client['localitate'] = $this->extractClientValue($clientLines, '/^Localitate\b/i', '/.+/');
        $client['adresa'] = $this->extractClientValue($clientLines, '/^Adresa\b/i', '/.+/');

        return $client;
    }

    private function extractClientValue(array $lines, string $labelRegex, string $valueRegex): string
    {
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = (string) $lines[$i];
            if (preg_match($labelRegex, $line) !== 1) {
                continue;
            }

            $inline = $this->extractInlineValueAfterLabel($line, $labelRegex, $valueRegex);
            if ($inline !== '') {
                return $inline;
            }

            for ($j = $i; $j < min($i + 4, $count); $j++) {
                $candidate = trim((string) $lines[$j]);
                if ($candidate === '' || preg_match($labelRegex, $candidate) === 1) {
                    continue;
                }
                if (preg_match('/^(CUI|Reg\.?\s*Com|Tara|Judet|Localitate|Adresa)\b/i', $candidate) === 1) {
                    continue;
                }
                if (preg_match($valueRegex, $candidate, $match) === 1) {
                    return trim((string) ($match[0] ?? ''));
                }
            }
        }

        return '';
    }

    private function extractInlineValueAfterLabel(string $line, string $labelRegex, string $valueRegex): string
    {
        $candidate = trim($line);
        if ($candidate === '') {
            return '';
        }

        for ($pass = 0; $pass < 3; $pass++) {
            if (preg_match($labelRegex, $candidate) !== 1) {
                break;
            }
            $updated = preg_replace('/^[^:]+:\s*/u', '', $candidate, 1);
            if (!is_string($updated) || $updated === $candidate) {
                break;
            }
            $candidate = trim($updated);
        }

        if ($candidate === '') {
            return '';
        }

        if ($valueRegex === '/.+/') {
            return $candidate;
        }

        if (preg_match($valueRegex, $candidate, $match) === 1) {
            return trim((string) ($match[0] ?? ''));
        }

        return '';
    }

    private function parseItems(array $lines): array
    {
        $headerIndex = $this->findItemsHeaderIndex($lines);
        if ($headerIndex < 0) {
            return [];
        }

        $numericRows = $this->parseNumericRows($lines, $headerIndex);
        $productRows = $this->parseProductRows($lines, $headerIndex);
        if (empty($productRows) || count($productRows) < count($numericRows)) {
            $globalRows = $this->parseProductRowsFromAllLines($lines);
            foreach ($globalRows as $positionNo => $row) {
                if (!isset($productRows[$positionNo])) {
                    $productRows[$positionNo] = $row;
                }
            }
            if (!empty($productRows)) {
                ksort($productRows);
            }
        }
        $items = [];

        if (!empty($numericRows)) {
            $productRowsByOrder = array_values($productRows);
            foreach ($numericRows as $index => $numericRow) {
                $positionNo = $index + 1;
                $product = $productRows[$positionNo] ?? ($productRowsByOrder[$index] ?? null);
                $name = trim((string) ($product['name'] ?? ''));
                $unit = strtoupper(trim((string) ($product['unit'] ?? '')));

                if ($name === '') {
                    $name = 'Pozitie ' . $positionNo;
                }
                if ($unit === '' || !$this->isUnitToken($unit)) {
                    $unit = 'BUC';
                }

                $item = [
                    'name' => $name,
                    'unit' => $unit,
                    'quantity' => (float) ($numericRow['quantity'] ?? 0),
                    'unit_price' => (float) ($numericRow['unit_price'] ?? 0),
                    'total_without_vat' => (float) ($numericRow['total_without_vat'] ?? 0),
                    'vat_percent' => (float) ($numericRow['vat_percent'] ?? 0),
                    'vat_value' => (float) ($numericRow['vat_value'] ?? 0),
                    'total_with_vat' => (float) ($numericRow['total_with_vat'] ?? 0),
                ];

                if ($item['quantity'] <= 0) {
                    $item['quantity'] = 1.0;
                }
                if ($item['unit_price'] <= 0 && $item['quantity'] > 0 && $item['total_without_vat'] > 0) {
                    $item['unit_price'] = round($item['total_without_vat'] / $item['quantity'], 2);
                }
                if ($item['vat_value'] <= 0 && $item['total_with_vat'] > 0 && $item['total_without_vat'] > 0) {
                    $item['vat_value'] = round($item['total_with_vat'] - $item['total_without_vat'], 2);
                }
                if ($item['vat_percent'] <= 0) {
                    if ($item['total_without_vat'] > 0 && $item['vat_value'] > 0) {
                        $item['vat_percent'] = round(($item['vat_value'] * 100) / $item['total_without_vat'], 2);
                    } else {
                        $item['vat_percent'] = 21.0;
                    }
                }

                $items[] = $item;
            }

            return $items;
        }

        if (!empty($productRows)) {
            foreach ($productRows as $positionNo => $product) {
                $name = trim((string) ($product['name'] ?? ''));
                $unit = strtoupper(trim((string) ($product['unit'] ?? '')));
                if ($name === '') {
                    $name = 'Pozitie ' . (int) $positionNo;
                }
                if ($unit === '' || !$this->isUnitToken($unit)) {
                    $unit = 'BUC';
                }
                $items[] = [
                    'name' => $name,
                    'unit' => $unit,
                    'quantity' => 1.0,
                    'unit_price' => 0.0,
                    'total_without_vat' => 0.0,
                    'vat_percent' => 21.0,
                    'vat_value' => 0.0,
                    'total_with_vat' => 0.0,
                ];
            }
        }

        return $items;
    }

    private function findItemsHeaderIndex(array $lines): int
    {
        foreach ($lines as $index => $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (stripos($line, 'Produs / serviciu') !== false) {
                return (int) $index;
            }
        }

        return -1;
    }

    private function parseNumericRows(array $lines, int $headerIndex): array
    {
        $tokenStream = [];
        $rows = [];
        $limit = $headerIndex > 0 ? min($headerIndex, count($lines)) : count($lines);
        for ($i = 0; $i < $limit; $i++) {
            $line = trim((string) $lines[$i]);
            if ($line === '') {
                continue;
            }

            $combined = $this->parseCombinedNumericRow($line);
            if ($combined !== null) {
                $rows[] = $combined;
                continue;
            }

            if (preg_match('/^\(?([0-9]+(?:[\.,][0-9]+)?)%\)?$/', $line, $vatMatch) === 1) {
                $tokenStream[] = [
                    'type' => 'vat',
                    'value' => $this->toFloat((string) $vatMatch[1]),
                ];
                continue;
            }

            $scalar = $this->parseScalarNumber($line);
            if ($scalar !== null) {
                $tokenStream[] = [
                    'type' => 'num',
                    'value' => $scalar,
                ];
            }
        }

        $count = count($tokenStream);
        for ($i = 0; $i + 5 < $count; $i++) {
            $window = array_slice($tokenStream, $i, 6);
            if (
                ($window[0]['type'] ?? '') !== 'num'
                || ($window[1]['type'] ?? '') !== 'num'
                || ($window[2]['type'] ?? '') !== 'num'
                || ($window[3]['type'] ?? '') !== 'vat'
                || ($window[4]['type'] ?? '') !== 'num'
                || ($window[5]['type'] ?? '') !== 'num'
            ) {
                continue;
            }

            $rows[] = [
                'quantity' => (float) $window[0]['value'],
                'unit_price' => (float) $window[1]['value'],
                'total_without_vat' => (float) $window[2]['value'],
                'vat_percent' => (float) $window[3]['value'],
                'vat_value' => (float) $window[4]['value'],
                'total_with_vat' => (float) $window[5]['value'],
            ];
            $i += 5;
        }

        return $rows;
    }

    private function parseProductRows(array $lines, int $headerIndex): array
    {
        $rows = [];
        $count = count($lines);
        if ($headerIndex < 0 || $headerIndex >= $count) {
            return $rows;
        }

        for ($i = $headerIndex + 1; $i < $count; $i++) {
            $line = trim((string) $lines[$i]);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^Total\b/i', $line) === 1 || strtoupper($line) === 'TOTAL') {
                break;
            }

            if (preg_match('/^(\d+)\s*(.*)$/', $line, $singleLineMatch) === 1) {
                $positionNo = (int) $singleLineMatch[1];
                $rest = $this->normalizeSpacing(trim((string) $singleLineMatch[2]));
                if ($positionNo > 0 && $rest !== '' && !$this->isReservedProductLabel($rest)) {
                    $unit = '';
                    $name = $rest;
                    $parts = preg_split('/\s+/', $rest) ?: [];
                    if (!empty($parts)) {
                        $last = strtoupper((string) (preg_replace('/[^A-Za-z0-9]/', '', (string) end($parts)) ?? ''));
                        if ($last !== '' && $this->isUnitToken($last)) {
                            array_pop($parts);
                            $name = $this->normalizeSpacing(implode(' ', $parts));
                            $unit = $last;
                        }
                    }

                    if ($name !== '' && !$this->isReservedProductLabel($name)) {
                        if ($unit === '') {
                            $nextIndex = $this->nextNonEmptyLineIndex($lines, $i + 1, $count);
                            if ($nextIndex !== null) {
                                $nextRaw = trim((string) $lines[$nextIndex]);
                                $nextToken = strtoupper((string) (preg_replace('/[^A-Za-z0-9]/', '', $nextRaw) ?? ''));
                                if ($this->isUnitToken($nextToken)) {
                                    $unit = $nextToken;
                                    $i = $nextIndex;
                                }
                            }
                        }
                        if ($unit === '') {
                            $unit = 'BUC';
                        }
                        $rows[$positionNo] = ['name' => $name, 'unit' => $unit];
                        continue;
                    }
                }
            }

            if (ctype_digit($line) !== true) {
                continue;
            }
            $positionNo = (int) $line;
            if ($positionNo <= 0) {
                continue;
            }

            $nameIndex = $this->nextNonEmptyLineIndex($lines, $i + 1, $count);
            if ($nameIndex === null) {
                break;
            }
            $nameLine = trim((string) $lines[$nameIndex]);
            if ($this->isItemsStopLine($nameLine)) {
                break;
            }
            if ($nameLine === '' || $this->isReservedProductLabel($nameLine)) {
                $i = $nameIndex;
                continue;
            }

            $unitIndex = $this->nextNonEmptyLineIndex($lines, $nameIndex + 1, $count);
            $unit = 'BUC';
            if ($unitIndex !== null) {
                $unitRaw = trim((string) $lines[$unitIndex]);
                if ($this->isItemsStopLine($unitRaw)) {
                    $rows[$positionNo] = [
                        'name' => $this->normalizeSpacing($nameLine),
                        'unit' => $unit,
                    ];
                    break;
                }
                $unitCandidate = strtoupper((string) (preg_replace('/[^A-Za-z0-9]/', '', $unitRaw) ?? ''));
                if ($this->isUnitToken($unitCandidate)) {
                    $unit = $unitCandidate;
                    $i = $unitIndex;
                } else {
                    $i = $nameIndex;
                }
            }

            $rows[$positionNo] = [
                'name' => $this->normalizeSpacing($nameLine),
                'unit' => $unit,
            ];
        }

        ksort($rows);
        return $rows;
    }

    private function parseProductRowsFromAllLines(array $lines): array
    {
        $rows = [];
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = trim((string) $lines[$i]);
            if ($line === '') {
                continue;
            }
            if ($this->isItemsStopLine($line)) {
                continue;
            }

            if (preg_match('/^(\d+)\s+(.+)$/', $line, $lineMatch) === 1) {
                $positionNo = (int) $lineMatch[1];
                $rest = $this->normalizeSpacing(trim((string) $lineMatch[2]));
                if ($positionNo > 0 && $rest !== '' && !$this->isReservedProductLabel($rest) && preg_match('/[A-Za-z]/', $rest) === 1) {
                    $unit = '';
                    $name = $rest;
                    $parts = preg_split('/\s+/', $rest) ?: [];
                    if (count($parts) >= 2) {
                        $last = strtoupper((string) (preg_replace('/[^A-Za-z0-9]/', '', (string) end($parts)) ?? ''));
                        if ($this->isUnitToken($last)) {
                            array_pop($parts);
                            $name = $this->normalizeSpacing(implode(' ', $parts));
                            $unit = $last;
                        }
                    }

                    if ($name !== '' && !$this->isReservedProductLabel($name)) {
                        if ($unit === '') {
                            $nextIndex = $this->nextNonEmptyLineIndex($lines, $i + 1, $count);
                            if ($nextIndex !== null) {
                                $nextRaw = trim((string) $lines[$nextIndex]);
                                $nextToken = strtoupper((string) (preg_replace('/[^A-Za-z0-9]/', '', $nextRaw) ?? ''));
                                if ($this->isUnitToken($nextToken)) {
                                    $unit = $nextToken;
                                    $i = $nextIndex;
                                }
                            }
                        }
                        if ($unit === '') {
                            $unit = 'BUC';
                        }
                        $rows[$positionNo] = [
                            'name' => $name,
                            'unit' => $unit,
                        ];
                        continue;
                    }
                }
            }

            if (ctype_digit($line) !== true) {
                continue;
            }

            $positionNo = (int) $line;
            if ($positionNo <= 0) {
                continue;
            }

            $nameIndex = $this->nextNonEmptyLineIndex($lines, $i + 1, $count);
            if ($nameIndex === null) {
                continue;
            }
            $nameLine = trim((string) $lines[$nameIndex]);
            if ($nameLine === '' || $this->isItemsStopLine($nameLine) || $this->isReservedProductLabel($nameLine) || preg_match('/[A-Za-z]/', $nameLine) !== 1) {
                continue;
            }

            $unit = 'BUC';
            $unitIndex = $this->nextNonEmptyLineIndex($lines, $nameIndex + 1, $count);
            if ($unitIndex !== null) {
                $unitRaw = trim((string) $lines[$unitIndex]);
                $unitToken = strtoupper((string) (preg_replace('/[^A-Za-z0-9]/', '', $unitRaw) ?? ''));
                if ($this->isUnitToken($unitToken)) {
                    $unit = $unitToken;
                    $i = $unitIndex;
                } else {
                    $i = $nameIndex;
                }
            }

            $rows[$positionNo] = [
                'name' => $this->normalizeSpacing($nameLine),
                'unit' => $unit,
            ];
        }

        ksort($rows);
        return $rows;
    }

    private function parseCombinedNumericRow(string $line): ?array
    {
        if (preg_match('/\(?([0-9]+(?:[\.,][0-9]+)?)%\)?/', $line, $vatMatch) !== 1) {
            return null;
        }
        $withoutPercent = preg_replace('/\(?[0-9]+(?:[\.,][0-9]+)?%\)?/', ' ', $line) ?? $line;
        $tokens = $this->extractNumberTokens($withoutPercent);
        if (count($tokens) < 5) {
            return null;
        }

        return [
            'quantity' => (float) $tokens[0],
            'unit_price' => (float) $tokens[1],
            'total_without_vat' => (float) $tokens[2],
            'vat_percent' => $this->toFloat((string) $vatMatch[1]),
            'vat_value' => (float) $tokens[3],
            'total_with_vat' => (float) $tokens[4],
        ];
    }

    private function parseScalarNumber(string $line): ?float
    {
        if (preg_match('/^(\d+(?:\s\d{3})*(?:[\.,]\d+)?)(?:\s*Lei)?$/iu', $line, $match) !== 1) {
            return null;
        }

        return $this->toFloat((string) $match[1]);
    }

    private function nextNonEmptyLineIndex(array $lines, int $start, int $count): ?int
    {
        for ($index = $start; $index < $count; $index++) {
            if (trim((string) $lines[$index]) !== '') {
                return $index;
            }
        }

        return null;
    }

    private function isItemsStopLine(string $line): bool
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return false;
        }
        if (preg_match('/^Total\b/i', $trimmed) === 1 || strtoupper($trimmed) === 'TOTAL') {
            return true;
        }

        return false;
    }

    private function extractNumberTokens(string $text): array
    {
        $tokens = [];
        $matches = [];
        if (preg_match_all('/\d+(?:\s\d{3})*(?:[\.,]\d+)?/', $text, $matches) <= 0) {
            return $tokens;
        }

        foreach ($matches[0] as $raw) {
            $value = $this->toFloat((string) $raw);
            $tokens[] = $value;
        }

        return $tokens;
    }

    private function parseMainItem(string $text, array $lines): array
    {
        $item = [
            'name' => '',
            'unit' => '',
            'quantity' => 0.0,
            'unit_price' => 0.0,
            'total_without_vat' => 0.0,
            'vat_percent' => 0.0,
            'vat_value' => 0.0,
            'total_with_vat' => 0.0,
        ];

        if (
            preg_match(
                '/(\d+(?:[\.,]\d+)?)\s+(\d+(?:[\.,]\d+)?)\s+(\d+(?:[\.,]\d+)?)\s+\((\d+(?:[\.,]\d+)?)%\)\s+(\d+(?:[\.,]\d+)?)\s+(\d+(?:[\.,]\d+)?)/s',
                $text,
                $match
            ) === 1
        ) {
            $item['quantity'] = $this->toFloat($match[1]);
            $item['unit_price'] = $this->toFloat($match[2]);
            $item['total_without_vat'] = $this->toFloat($match[3]);
            $item['vat_percent'] = $this->toFloat($match[4]);
            $item['vat_value'] = $this->toFloat($match[5]);
            $item['total_with_vat'] = $this->toFloat($match[6]);
        }

        if (preg_match('/\(?([0-9]{1,2}(?:[\.,][0-9]{1,2})?)%\)?/', $text, $vatMatch) === 1) {
            $item['vat_percent'] = max($item['vat_percent'], $this->toFloat($vatMatch[1]));
        }

        $item['unit'] = $this->extractUnit($lines);
        $item['name'] = $this->extractProductName($lines);

        return $item;
    }

    private function extractUnit(array $lines): string
    {
        $allowed = ['BUC', 'KG', 'L', 'ML', 'M', 'MP', 'MC', 'SET', 'PACH', 'CUT', 'SAC', 'PAL'];
        foreach ($lines as $line) {
            $normalized = strtoupper(trim((string) $line));
            if (in_array($normalized, $allowed, true)) {
                return $normalized;
            }
        }
        return 'BUC';
    }

    private function extractProductName(array $lines): string
    {
        $count = count($lines);
        $tableStart = -1;
        for ($i = 0; $i < $count; $i++) {
            $line = trim((string) $lines[$i]);
            if ($line === '#' || stripos($line, 'Produs / serviciu') !== false) {
                $tableStart = $i;
                break;
            }
        }

        if ($tableStart >= 0) {
            for ($i = $tableStart; $i < $count; $i++) {
                $line = trim((string) $lines[$i]);
                if (preg_match('/^\d+$/', $line) !== 1) {
                    continue;
                }
                for ($j = $i + 1; $j < min($count, $i + 6); $j++) {
                    $candidate = trim((string) $lines[$j]);
                    if ($candidate === '') {
                        continue;
                    }
                    if ($this->isReservedProductLabel($candidate)) {
                        continue;
                    }
                    if ($this->isUnitToken($candidate)) {
                        continue;
                    }
                    if (preg_match('/^[0-9\.\,\(\)%]+$/', $candidate) === 1) {
                        continue;
                    }
                    if (preg_match('/[A-Za-z]/', $candidate) !== 1) {
                        continue;
                    }
                    if (strlen($candidate) >= 4) {
                        return $candidate;
                    }
                }
            }
        }

        return 'Produse conform avizului sursa';
    }

    private function isReservedProductLabel(string $value): bool
    {
        $normalized = strtoupper($value);
        $normalized = preg_replace('/[^A-Z0-9]+/', '', $normalized) ?? '';
        if ($normalized === '') {
            return true;
        }

        $reserved = [
            'FURNIZOR',
            'CLIENT',
            'TOTAL',
            'CUI',
            'REGCOM',
            'TARA',
            'JUDET',
            'LOCALITATE',
            'ADRESA',
            'PRODUSSERVICIU',
            'UM',
            'CANT',
            'PRETUNITAR',
            'VALOARE',
            'TVA',
        ];

        return in_array($normalized, $reserved, true);
    }

    private function isUnitToken(string $value): bool
    {
        $token = strtoupper(trim($value));
        $allowed = ['BUC', 'KG', 'L', 'ML', 'M', 'MP', 'MC', 'SET', 'PACH', 'CUT', 'SAC', 'PAL'];
        return in_array($token, $allowed, true);
    }

    private function parseTotals(array $lines, array $items, string $text): array
    {
        $totals = [
            'without_vat' => 0.0,
            'vat' => 0.0,
            'with_vat' => 0.0,
            'vat_percent' => 0.0,
        ];

        $lineCount = count($lines);
        for ($i = 0; $i < $lineCount; $i++) {
            $trimmed = trim((string) $lines[$i]);
            if ($trimmed === '' || preg_match('/^Total\b/i', $trimmed) !== 1) {
                continue;
            }

            $numberTokens = $this->extractNumberTokens($trimmed);
            if (count($numberTokens) < 3) {
                for ($j = $i + 1; $j < min($lineCount, $i + 6); $j++) {
                    $scalar = $this->parseScalarNumber(trim((string) $lines[$j]));
                    if ($scalar === null) {
                        if ($this->isItemsStopLine((string) $lines[$j])) {
                            break;
                        }
                        continue;
                    }
                    $numberTokens[] = $scalar;
                    if (count($numberTokens) >= 3) {
                        break;
                    }
                }
            }

            if (count($numberTokens) >= 3) {
                $totals['without_vat'] = (float) $numberTokens[0];
                $totals['vat'] = (float) $numberTokens[1];
                $totals['with_vat'] = (float) $numberTokens[2];
                break;
            }
        }

        if (preg_match('/\(?([0-9]{1,2}(?:[\.,][0-9]{1,2})?)%\)?/', $text, $vatMatch) === 1) {
            $totals['vat_percent'] = $this->toFloat((string) $vatMatch[1]);
        }

        if ($totals['without_vat'] <= 0 || $totals['with_vat'] <= 0) {
            $sumWithout = 0.0;
            $sumVat = 0.0;
            $sumWith = 0.0;
            foreach ($items as $item) {
                $sumWithout += (float) ($item['total_without_vat'] ?? 0);
                $sumVat += (float) ($item['vat_value'] ?? 0);
                $sumWith += (float) ($item['total_with_vat'] ?? 0);
            }
            if ($totals['without_vat'] <= 0) {
                $totals['without_vat'] = $sumWithout;
            }
            if ($totals['vat'] <= 0) {
                $totals['vat'] = $sumVat;
            }
            if ($totals['with_vat'] <= 0) {
                $totals['with_vat'] = $sumWith;
            }
        }

        if ($totals['vat'] <= 0 && $totals['with_vat'] > 0 && $totals['without_vat'] > 0) {
            $totals['vat'] = round($totals['with_vat'] - $totals['without_vat'], 2);
        }
        if ($totals['vat_percent'] <= 0 && $totals['without_vat'] > 0 && $totals['vat'] > 0) {
            $totals['vat_percent'] = round(($totals['vat'] * 100) / $totals['without_vat'], 2);
        }

        return $totals;
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

    private function toFloat(string|float|int $value): float
    {
        $string = trim((string) $value);
        if ($string === '') {
            return 0.0;
        }
        $string = str_replace(' ', '', $string);
        $string = str_replace(',', '.', $string);
        if (!is_numeric($string)) {
            return 0.0;
        }
        return round((float) $string, 4);
    }

    private function formatNumber(float $value, int $decimals): string
    {
        return number_format($value, $decimals, '.', ' ');
    }

    private function resolveBinaryPath(string $binary): string
    {
        $binary = trim($binary);
        if ($binary === '') {
            return '';
        }
        $path = @shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null');
        if (!is_string($path)) {
            return '';
        }
        $resolved = trim($path);
        if ($resolved === '' || !is_executable($resolved)) {
            return '';
        }

        return $resolved;
    }

    private function cleanupOcrArtifacts(string $workDir, array $images): void
    {
        foreach ($images as $imagePath) {
            if (is_string($imagePath) && $imagePath !== '') {
                @unlink($imagePath);
            }
        }
        @rmdir($workDir);
    }
}
