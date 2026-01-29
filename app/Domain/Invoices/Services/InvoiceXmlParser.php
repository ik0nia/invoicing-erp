<?php

namespace App\Domain\Invoices\Services;

class InvoiceXmlParser
{
    private const NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public function parse(string $filePath): array
    {
        $xml = simplexml_load_file($filePath);

        if (!$xml) {
            throw new \RuntimeException('XML invalid.');
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

        return [
            'invoice_number' => $invoiceNumber,
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
