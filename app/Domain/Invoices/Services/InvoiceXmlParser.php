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
        $customerParty = $xml->xpath('//cac:AccountingCustomerParty/cac:Party')[0] ?? null;

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

        return [
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
        if (!str_contains($contents, '<Invoice')) {
            return null;
        }

        $invoiceNumber = $this->firstTagValue($contents, 'cbc:ID');
        $issueDate = $this->firstTagValue($contents, 'cbc:IssueDate');
        $dueDate = $this->firstTagValue($contents, 'cbc:DueDate');
        $currency = $this->firstTagValue($contents, 'cbc:DocumentCurrencyCode') ?: 'RON';

        $supplierBlock = $this->extractBlock($contents, 'cac:AccountingSupplierParty');
        $customerBlock = $this->extractBlock($contents, 'cac:AccountingCustomerParty');

        [$supplierCui, $supplierName] = $this->partyFromBlock($supplierBlock);
        [$customerCui, $customerName] = $this->partyFromBlock($customerBlock);

        $totalWithoutVat = (float) $this->firstTagValue($contents, 'cbc:TaxExclusiveAmount');
        $totalVat = (float) $this->firstTagValue($contents, 'cbc:TaxAmount');
        $totalWithVat = (float) $this->firstTagValue($contents, 'cbc:TaxInclusiveAmount');

        if ($totalWithVat <= 0) {
            $totalWithVat = (float) $this->firstTagValue($contents, 'cbc:PayableAmount');
        }

        $lines = [];
        if (preg_match_all('/<cac:InvoiceLine\b[^>]*>(.*?)<\/cac:InvoiceLine>/s', $contents, $matches)) {
            foreach ($matches[1] as $lineBlock) {
                $lineNo = $this->firstTagValue($lineBlock, 'cbc:ID') ?: '';
                $productName = $this->firstTagValue($lineBlock, 'cbc:Name') ?: '';
                $lineTotal = (float) $this->firstTagValue($lineBlock, 'cbc:LineExtensionAmount');
                $unitPrice = (float) $this->firstTagValue($lineBlock, 'cbc:PriceAmount');
                $taxPercent = (float) $this->firstTagValue($lineBlock, 'cbc:Percent');

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

        if ($invoiceNumber === null || $issueDate === null || empty($lines)) {
            return null;
        }

        [$invoiceSeries, $invoiceNo] = $this->splitInvoiceNumber($invoiceNumber);

        return [
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
    }

    private function extractBlock(string $contents, string $tag): ?string
    {
        $tag = preg_quote($tag, '/');

        if (preg_match('/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/s', $contents, $match)) {
            return $match[1];
        }

        return null;
    }

    private function firstTagValue(string $contents, string $tag): ?string
    {
        $tag = preg_quote($tag, '/');

        if (preg_match('/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/s', $contents, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    private function partyFromBlock(?string $block): array
    {
        if (!$block) {
            return ['', ''];
        }

        $companyId = $this->firstTagValue($block, 'cbc:CompanyID')
            ?: $this->firstTagValue($block, 'cbc:ID');
        $registration = $this->firstTagValue($block, 'cbc:RegistrationName')
            ?: $this->firstTagValue($block, 'cbc:Name');

        $cui = $this->normalizeCui($companyId);

        return [$cui, $registration ?: ''];
    }

    private function quantityFromBlock(string $block): array
    {
        if (preg_match('/<cbc:InvoicedQuantity[^>]*unitCode="([^"]+)"[^>]*>(.*?)<\/cbc:InvoicedQuantity>/s', $block, $match)) {
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
            return null;
        }

        return trim((string) $result[0]);
    }

    private function attribute(\SimpleXMLElement $context, string $path, string $attribute): ?string
    {
        $result = $context->xpath($path);

        if (!$result || !isset($result[0])) {
            return null;
        }

        $attrs = $result[0]->attributes();

        if (!$attrs || !isset($attrs[$attribute])) {
            return null;
        }

        return trim((string) $attrs[$attribute]);
    }
}
