<?php

namespace App\Domain\Invoices\Services;

class InvoiceXmlParser
{
    private const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public function parse(string $filePath): array
    {
        $contents = file_get_contents($filePath);

        if ($contents === false || trim($contents) === '') {
            throw new \RuntimeException('Fisierul XML este gol sau nu poate fi citit.');
        }

        $contents = $this->stripBom($contents);
        $contents = $this->sanitizeXml($contents);
        $contents = $this->ensureUtf8($contents);

        $xml = $this->loadXml($contents);
        if (!$xml) {
            $fallback = $this->parseByRegex($contents);
            if ($fallback !== null) {
                return $fallback;
            }

            $message = $this->libxmlErrorMessage();
            throw new \RuntimeException('XML invalid.' . ($message ? ' ' . $message : ''));
        }

        $xml->registerXPathNamespace('inv', self::NS_INVOICE);
        $xml->registerXPathNamespace('cac', self::NS_CAC);
        $xml->registerXPathNamespace('cbc', self::NS_CBC);

        $invoiceNumber = $this->value($xml, '/inv:Invoice/cbc:ID');
        $issueDate = $this->value($xml, '/inv:Invoice/cbc:IssueDate');
        $dueDate = $this->value($xml, '/inv:Invoice/cbc:DueDate');
        $currency = $this->value($xml, '/inv:Invoice/cbc:DocumentCurrencyCode') ?: 'RON';

        $supplierParty = $xml->xpath('//cac:AccountingSupplierParty/cac:Party')[0] ?? null;
        if (!$supplierParty) {
            $supplierParty = $xml->xpath('//*[local-name()="AccountingSupplierParty"]/*[local-name()="Party"]')[0] ?? null;
        }
        $customerParty = $xml->xpath('//cac:AccountingCustomerParty/cac:Party')[0] ?? null;
        if (!$customerParty) {
            $customerParty = $xml->xpath('//*[local-name()="AccountingCustomerParty"]/*[local-name()="Party"]')[0] ?? null;
        }

        [$supplierCui, $supplierName] = $this->partyData($supplierParty);
        [$customerCui, $customerName] = $this->partyData($customerParty);

        $totalWithoutVat = (float) $this->value($xml, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount');
        $totalVat = (float) $this->value($xml, '/inv:Invoice/cac:TaxTotal/cbc:TaxAmount');
        $totalWithVat = (float) $this->value($xml, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount');

        if ($totalWithVat <= 0) {
            $totalWithVat = (float) $this->value($xml, '/inv:Invoice/cac:LegalMonetaryTotal/cbc:PayableAmount');
        }

        $lines = [];
        $invoiceLines = $xml->xpath('//cac:InvoiceLine') ?: [];
        if (empty($invoiceLines)) {
            $invoiceLines = $xml->xpath('//*[local-name()="InvoiceLine"]') ?: [];
        }

        foreach ($invoiceLines as $line) {
            $lineNo = $this->value($line, 'cbc:ID') ?: '';
            $quantity = (float) $this->value($line, 'cbc:InvoicedQuantity');
            $unitCode = $this->attribute($line, 'cbc:InvoicedQuantity', 'unitCode') ?: 'BUC';
            $lineTotal = (float) $this->value($line, 'cbc:LineExtensionAmount');
            $productName = $this->value($line, 'cac:Item/cbc:Name') ?: '';
            $taxPercent = (float) $this->value($line, 'cac:Item/cac:ClassifiedTaxCategory/cbc:Percent');
            $unitPrice = (float) $this->value($line, 'cac:Price/cbc:PriceAmount');

            $lineTotalVat = round($lineTotal * (1 + $taxPercent / 100), 2);

            $lines[] = [
                'line_no' => $lineNo,
                'product_name' => $productName,
                'quantity' => $quantity,
                'unit_code' => $unitCode,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'tax_percent' => $taxPercent,
                'line_total_vat' => $lineTotalVat,
            ];
        }

        [$invoiceSeries, $invoiceNo] = $this->splitInvoiceNumber($invoiceNumber);

        $data = [
            'invoice_number' => $invoiceNumber,
            'invoice_series' => $invoiceSeries,
            'invoice_no' => $invoiceNo,
            'issue_date' => $issueDate,
            'due_date' => $dueDate ?: null,
            'currency' => $currency,
            'supplier_cui' => $supplierCui,
            'supplier_name' => $supplierName,
            'customer_cui' => $customerCui,
            'customer_name' => $customerName,
            'total_without_vat' => $totalWithoutVat,
            'total_vat' => $totalVat,
            'total_with_vat' => $totalWithVat,
            'lines' => $lines,
        ];

        $missingCore = $this->dataMissingCore($data);

        if ($missingCore) {
            $domData = $this->parseWithDom($contents);
            $data = $this->mergeParsedData($data, $domData);
            $missingCore = $this->dataMissingCore($data);
        }

        if ($missingCore) {
            $fallback = $this->parseByRegex($contents);
            $data = $this->mergeParsedData($data, $fallback);
            $missingCore = $this->dataMissingCore($data);
        }

        if ($missingCore) {
            $message = $this->libxmlErrorMessage();
            throw new \RuntimeException('XML nu contine suficiente date pentru prelucrare.' . ($message ? ' ' . $message : ''));
        }

        return $data;
    }

    private function stripBom(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return substr($contents, 3);
        }

        return $contents;
    }

    private function sanitizeXml(string $contents): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $contents) ?? $contents;
    }

    private function ensureUtf8(string $contents): string
    {
        if (preg_match('//u', $contents)) {
            return $contents;
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $contents);
            if ($converted !== false) {
                return $converted;
            }
        }

        if (function_exists('utf8_encode')) {
            return utf8_encode($contents);
        }

        return $contents;
    }

    private function loadXml(string $contents): ?\SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $options = LIBXML_NONET | LIBXML_NOCDATA | LIBXML_PARSEHUGE;

        $xml = simplexml_load_string($contents, 'SimpleXMLElement', $options);
        if ($xml instanceof \SimpleXMLElement) {
            libxml_clear_errors();
            return $xml;
        }

        if (class_exists(\DOMDocument::class)) {
            $dom = new \DOMDocument();
            if (@$dom->loadXML($contents, $options) && $dom->documentElement) {
                $simple = simplexml_import_dom($dom);
                if ($simple instanceof \SimpleXMLElement) {
                    libxml_clear_errors();
                    return $simple;
                }
            }
        }

        return null;
    }

    private function libxmlErrorMessage(): ?string
    {
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if (!$errors) {
            return null;
        }

        $first = $errors[0];
        $message = trim($first->message ?? '');

        if ($message === '') {
            return null;
        }

        $line = $first->line ?? 0;
        $column = $first->column ?? 0;

        return 'Detalii: ' . $message . ($line ? ' (linia ' . $line . ', coloana ' . $column . ')' : '');
    }

    private function parseByRegex(string $contents): ?array
    {
        if (!preg_match('/<\s*(?:[A-Za-z0-9_\-]+:)?Invoice\b/i', $contents)) {
            return null;
        }

        $invoiceBlock = $this->extractBlock($contents, 'Invoice') ?? $contents;

        $invoiceNumber = $this->firstTagValue($invoiceBlock, 'ID');
        $issueDate = $this->firstTagValue($invoiceBlock, 'IssueDate');
        $dueDate = $this->firstTagValue($invoiceBlock, 'DueDate');
        $currency = $this->firstTagValue($invoiceBlock, 'DocumentCurrencyCode') ?: 'RON';

        $supplierBlock = $this->extractBlock($contents, 'AccountingSupplierParty');
        $customerBlock = $this->extractBlock($contents, 'AccountingCustomerParty');

        [$supplierCui, $supplierName] = $this->partyFromBlock($supplierBlock);
        [$customerCui, $customerName] = $this->partyFromBlock($customerBlock);

        $totalWithoutVat = (float) $this->firstTagValue($contents, 'cbc:TaxExclusiveAmount');
        $totalVat = (float) $this->firstTagValue($contents, 'cbc:TaxAmount');
        $totalWithVat = (float) $this->firstTagValue($contents, 'cbc:TaxInclusiveAmount');

        if ($totalWithVat <= 0) {
            $totalWithVat = (float) $this->firstTagValue($contents, 'cbc:PayableAmount');
        }

        $lines = [];
        if (preg_match_all('/<\s*(?:[A-Za-z0-9_\-]+:)?InvoiceLine\b[^>]*>(.*?)<\/\s*(?:[A-Za-z0-9_\-]+:)?InvoiceLine\s*>/s', $contents, $matches)) {
            foreach ($matches[1] as $lineBlock) {
                $lineNo = $this->firstTagValue($lineBlock, 'ID') ?: '';
                $productName = $this->firstTagValue($lineBlock, 'Name') ?: '';
                $lineTotal = (float) $this->firstTagValue($lineBlock, 'LineExtensionAmount');
                $unitPrice = (float) $this->firstTagValue($lineBlock, 'PriceAmount');
                $taxPercent = (float) $this->firstTagValue($lineBlock, 'Percent');

                [$quantity, $unitCode] = $this->quantityFromBlock($lineBlock);
                $lineTotalVat = round($lineTotal * (1 + $taxPercent / 100), 2);

                $lines[] = [
                    'line_no' => $lineNo,
                    'product_name' => $productName,
                    'quantity' => $quantity,
                    'unit_code' => $unitCode,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'tax_percent' => $taxPercent,
                    'line_total_vat' => $lineTotalVat,
                ];
            }
        }

        if ($invoiceNumber === null && $issueDate === null && empty($lines)) {
            return null;
        }

        [$invoiceSeries, $invoiceNo] = $this->splitInvoiceNumber($invoiceNumber ?: '');

        return [
            'invoice_number' => $invoiceNumber ?: '',
            'invoice_series' => $invoiceSeries,
            'invoice_no' => $invoiceNo,
            'issue_date' => $issueDate ?: '',
            'due_date' => $dueDate ?: null,
            'currency' => $currency,
            'supplier_cui' => $supplierCui,
            'supplier_name' => $supplierName,
            'customer_cui' => $customerCui,
            'customer_name' => $customerName,
            'total_without_vat' => $totalWithoutVat,
            'total_vat' => $totalVat,
            'total_with_vat' => $totalWithVat,
            'lines' => $lines,
        ];
    }

    private function parseWithDom(string $contents): ?array
    {
        if (!class_exists(\DOMDocument::class)) {
            return null;
        }

        libxml_use_internal_errors(true);
        $options = LIBXML_NONET | LIBXML_NOCDATA | LIBXML_PARSEHUGE;
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($contents, $options)) {
            libxml_clear_errors();
            return null;
        }
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        $invoiceNumber = $this->domValue($xpath, '/*[local-name()="Invoice"]/*[local-name()="ID"][1]');
        $issueDate = $this->domValue($xpath, '/*[local-name()="Invoice"]/*[local-name()="IssueDate"][1]');
        $dueDate = $this->domValue($xpath, '/*[local-name()="Invoice"]/*[local-name()="DueDate"][1]');
        $currency = $this->domValue($xpath, '/*[local-name()="Invoice"]/*[local-name()="DocumentCurrencyCode"][1]') ?: 'RON';

        $supplierNode = $this->domNode($xpath, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="Party"][1]');
        $customerNode = $this->domNode($xpath, '//*[local-name()="AccountingCustomerParty"]//*[local-name()="Party"][1]');
        [$supplierCui, $supplierName] = $this->domPartyData($xpath, $supplierNode);
        [$customerCui, $customerName] = $this->domPartyData($xpath, $customerNode);

        $totalWithoutVat = (float) $this->domValue($xpath, '//*[local-name()="LegalMonetaryTotal"]/*[local-name()="TaxExclusiveAmount"][1]');
        $totalVat = (float) $this->domValue($xpath, '//*[local-name()="TaxTotal"]/*[local-name()="TaxAmount"][1]');
        $totalWithVat = (float) $this->domValue($xpath, '//*[local-name()="LegalMonetaryTotal"]/*[local-name()="TaxInclusiveAmount"][1]');

        if ($totalWithVat <= 0) {
            $totalWithVat = (float) $this->domValue($xpath, '//*[local-name()="LegalMonetaryTotal"]/*[local-name()="PayableAmount"][1]');
        }

        $lines = [];
        foreach ($xpath->query('//*[local-name()="InvoiceLine"]') as $lineNode) {
            $lineNo = $this->domValue($xpath, './*[local-name()="ID"][1]', $lineNode) ?: '';
            $quantityNode = $this->domNode($xpath, './*[local-name()="InvoicedQuantity"][1]', $lineNode);
            $quantity = $quantityNode ? (float) trim((string) $quantityNode->textContent) : 0.0;
            $unitCode = $quantityNode && $quantityNode->attributes
                ? trim((string) ($quantityNode->attributes->getNamedItem('unitCode')?->nodeValue ?? ''))
                : '';
            $unitCode = $unitCode !== '' ? $unitCode : 'BUC';

            $lineTotal = (float) $this->domValue($xpath, './*[local-name()="LineExtensionAmount"][1]', $lineNode);
            $productName = $this->domValue($xpath, './/*[local-name()="Item"]/*[local-name()="Name"][1]', $lineNode) ?: '';
            $taxPercent = (float) $this->domValue($xpath, './/*[local-name()="ClassifiedTaxCategory"]/*[local-name()="Percent"][1]', $lineNode);
            $unitPrice = (float) $this->domValue($xpath, './/*[local-name()="Price"]/*[local-name()="PriceAmount"][1]', $lineNode);

            $lineTotalVat = round($lineTotal * (1 + $taxPercent / 100), 2);

            $lines[] = [
                'line_no' => $lineNo,
                'product_name' => $productName,
                'quantity' => $quantity,
                'unit_code' => $unitCode,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'tax_percent' => $taxPercent,
                'line_total_vat' => $lineTotalVat,
            ];
        }

        if ($invoiceNumber === '' && $issueDate === '' && empty($lines) && $supplierName === '' && $customerName === '') {
            return null;
        }

        [$invoiceSeries, $invoiceNo] = $this->splitInvoiceNumber($invoiceNumber ?: '');

        return [
            'invoice_number' => $invoiceNumber ?: '',
            'invoice_series' => $invoiceSeries,
            'invoice_no' => $invoiceNo,
            'issue_date' => $issueDate ?: '',
            'due_date' => $dueDate ?: null,
            'currency' => $currency,
            'supplier_cui' => $supplierCui,
            'supplier_name' => $supplierName,
            'customer_cui' => $customerCui,
            'customer_name' => $customerName,
            'total_without_vat' => $totalWithoutVat,
            'total_vat' => $totalVat,
            'total_with_vat' => $totalWithVat,
            'lines' => $lines,
        ];
    }

    private function extractBlock(string $contents, string $tag): ?string
    {
        $tag = $this->tagPattern($tag);

        if (preg_match('/<\s*' . $tag . '\b[^>]*>(.*?)<\/\s*' . $tag . '\s*>/s', $contents, $match)) {
            return $match[1];
        }

        return null;
    }

    private function firstTagValue(string $contents, string $tag): ?string
    {
        $tag = $this->tagPattern($tag);

        if (preg_match('/<\s*' . $tag . '\b[^>]*>(.*?)<\/\s*' . $tag . '\s*>/s', $contents, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    private function partyFromBlock(?string $block): array
    {
        if (!$block) {
            return ['', ''];
        }

        $companyId = $this->firstTagValue($block, 'CompanyID')
            ?: $this->firstTagValue($block, 'ID');
        $registration = $this->firstTagValue($block, 'RegistrationName')
            ?: $this->firstTagValue($block, 'Name');

        $cui = $this->normalizeCui($companyId);

        return [$cui, $registration ?: ''];
    }

    private function quantityFromBlock(string $block): array
    {
        if (preg_match('/<\s*(?:[A-Za-z0-9_\-]+:)?InvoicedQuantity[^>]*unitCode="([^"]+)"[^>]*>(.*?)<\/\s*(?:[A-Za-z0-9_\-]+:)?InvoicedQuantity\s*>/s', $block, $match)) {
            return [(float) trim($match[2]), trim($match[1]) ?: 'BUC'];
        }

        return [0.0, 'BUC'];
    }

    private function splitInvoiceNumber(?string $invoiceNumber): array
    {
        if (!$invoiceNumber) {
            return ['', ''];
        }

        $parts = explode('.', $invoiceNumber);

        if (count($parts) >= 2) {
            $series = trim($parts[0]);
            $number = trim($parts[1]);

            return [$series, $number];
        }

        if (preg_match('/^([A-Za-z]+)\s*0*([0-9]+)$/', $invoiceNumber, $match)) {
            return [$match[1], $match[2]];
        }

        return ['', $invoiceNumber];
    }

    private function partyData(?\SimpleXMLElement $party): array
    {
        if (!$party) {
            return ['', ''];
        }

        $companyId = $this->value($party, 'cac:PartyTaxScheme/cbc:CompanyID')
            ?: $this->value($party, 'cac:PartyIdentification/cbc:ID');

        $registrationName = $this->value($party, 'cac:PartyLegalEntity/cbc:RegistrationName')
            ?: $this->value($party, 'cac:PartyName/cbc:Name');

        $cui = $this->normalizeCui($companyId);

        return [$cui, $registrationName ?: ''];
    }

    private function normalizeCui(?string $value): string
    {
        if (!$value) {
            return '';
        }

        return preg_replace('/\D+/', '', $value);
    }

    private function value(\SimpleXMLElement $context, string $path): ?string
    {
        $result = $context->xpath($path);

        if (!$result || !isset($result[0])) {
            $localPath = $this->localNamePath($path);
            if ($localPath !== $path) {
                $result = $context->xpath($localPath);
            }
        }

        if (!$result || !isset($result[0])) {
            $anyPath = $this->anywherePath($path);
            if ($anyPath !== '') {
                $result = $context->xpath($anyPath);
            }
        }

        if (!$result || !isset($result[0])) {
            return null;
        }

        return trim((string) $result[0]);
    }

    private function attribute(\SimpleXMLElement $context, string $path, string $attribute): ?string
    {
        $result = $context->xpath($path);

        if (!$result || !isset($result[0])) {
            $localPath = $this->localNamePath($path);
            if ($localPath !== $path) {
                $result = $context->xpath($localPath);
            }
        }

        if (!$result || !isset($result[0])) {
            $anyPath = $this->anywherePath($path);
            if ($anyPath !== '') {
                $result = $context->xpath($anyPath);
            }
        }

        if (!$result || !isset($result[0])) {
            return null;
        }

        $attrs = $result[0]->attributes();

        if (!$attrs || !isset($attrs[$attribute])) {
            return null;
        }

        return trim((string) $attrs[$attribute]);
    }

    private function localNamePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return $path;
        }

        $isAbsolute = str_starts_with($path, '/');
        $parts = array_filter(explode('/', trim($path, '/')), static function (string $part): bool {
            return $part !== '';
        });

        $converted = [];
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..') {
                $converted[] = $part;
                continue;
            }

            $predicate = '';
            $base = $part;
            $bracketPos = strpos($part, '[');
            if ($bracketPos !== false) {
                $base = substr($part, 0, $bracketPos);
                $predicate = substr($part, $bracketPos);
            }

            $name = $base;
            $colonPos = strpos($base, ':');
            if ($colonPos !== false) {
                $name = substr($base, $colonPos + 1);
            }

            if ($name === '') {
                $name = $base;
            }

            $converted[] = "*[local-name()='" . $name . "']" . $predicate;
        }

        $localPath = implode('/', $converted);
        return $isAbsolute ? '/' . $localPath : $localPath;
    }

    private function anywherePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $localPath = $this->localNamePath($path);
        if ($localPath === '') {
            return '';
        }

        $trimmed = ltrim($localPath, '/');
        return '//' . $trimmed;
    }

    private function tagPattern(string $tag): string
    {
        $tag = trim($tag);
        if ($tag === '') {
            return '';
        }

        $name = $tag;
        $colonPos = strpos($tag, ':');
        if ($colonPos !== false) {
            $name = substr($tag, $colonPos + 1);
        }

        $name = preg_quote($name, '/');
        return '(?:[A-Za-z0-9_\\-]+:)?' . $name;
    }

    private function dataMissingCore(array $data): bool
    {
        $lines = $data['lines'] ?? [];
        $hasLines = !empty($lines);
        $hasInvoice = !empty($data['invoice_number']);
        $hasSupplier = !empty($data['supplier_name']) || !empty($data['supplier_cui']);
        $hasCustomer = !empty($data['customer_name']) || !empty($data['customer_cui']);

        if (!$hasLines) {
            return true;
        }

        if (!$hasInvoice && !$hasSupplier && !$hasCustomer) {
            return true;
        }

        return false;
    }

    private function mergeParsedData(?array $base, ?array $extra): array
    {
        $base = $base ?? [];
        if (!$extra) {
            return $base;
        }

        foreach ($extra as $key => $value) {
            if ($key === 'lines') {
                if (empty($base['lines']) && !empty($value)) {
                    $base['lines'] = $value;
                }
                continue;
            }

            if (!isset($base[$key]) || $base[$key] === '' || $base[$key] === null) {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function domValue(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): ?string
    {
        $nodes = $xpath->query($query, $context);
        if (!$nodes || $nodes->length === 0) {
            return null;
        }

        $value = trim((string) $nodes->item(0)?->textContent);
        return $value !== '' ? $value : null;
    }

    private function domNode(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): ?\DOMNode
    {
        $nodes = $xpath->query($query, $context);
        if (!$nodes || $nodes->length === 0) {
            return null;
        }

        return $nodes->item(0);
    }

    private function domPartyData(\DOMXPath $xpath, ?\DOMNode $party): array
    {
        if (!$party) {
            return ['', ''];
        }

        $companyId = $this->domValue($xpath, './/*[local-name()="PartyTaxScheme"]/*[local-name()="CompanyID"][1]', $party)
            ?: $this->domValue($xpath, './/*[local-name()="PartyIdentification"]/*[local-name()="ID"][1]', $party);

        $registration = $this->domValue($xpath, './/*[local-name()="PartyLegalEntity"]/*[local-name()="RegistrationName"][1]', $party)
            ?: $this->domValue($xpath, './/*[local-name()="PartyName"]/*[local-name()="Name"][1]', $party);

        $cui = $this->normalizeCui($companyId);

        return [$cui, $registration ?: ''];
    }
}
