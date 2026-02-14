<?php

namespace App\Domain\Contracts\Services;

use App\Support\Database;

class ContractTemplateVariables
{
    public function listPlaceholders(): array
    {
        return [
            ['key' => 'partner.name', 'label' => 'Denumirea companiei'],
            ['key' => 'partner.cui', 'label' => 'CUI companie'],
            ['key' => 'partner.email', 'label' => 'Email companie'],
            ['key' => 'partner.phone', 'label' => 'Telefon companie'],
            ['key' => 'partner.address', 'label' => 'Adresa companiei'],
            ['key' => 'partner.city', 'label' => 'Localitate'],
            ['key' => 'partner.county', 'label' => 'Judet'],
            ['key' => 'partner.country', 'label' => 'Tara'],
            ['key' => 'partner.reg_no', 'label' => 'Nr. Reg. Comertului'],
            ['key' => 'partner.bank', 'label' => 'Banca'],
            ['key' => 'partner.iban', 'label' => 'IBAN'],
            ['key' => 'supplier.name', 'label' => 'Denumire furnizor'],
            ['key' => 'supplier.cui', 'label' => 'CUI furnizor'],
            ['key' => 'supplier.email', 'label' => 'Email furnizor'],
            ['key' => 'supplier.phone', 'label' => 'Telefon furnizor'],
            ['key' => 'client.name', 'label' => 'Denumire client'],
            ['key' => 'client.cui', 'label' => 'CUI client'],
            ['key' => 'client.email', 'label' => 'Email client'],
            ['key' => 'client.phone', 'label' => 'Telefon client'],
            ['key' => 'relation.supplier_cui', 'label' => 'Relatie - CUI furnizor'],
            ['key' => 'relation.client_cui', 'label' => 'Relatie - CUI client'],
            ['key' => 'relation.invoice_inbox_email', 'label' => 'Email inbox facturi (relatie)'],
            ['key' => 'contract.title', 'label' => 'Titlu contract'],
            ['key' => 'contract.created_at', 'label' => 'Data creare contract'],
            ['key' => 'date.today', 'label' => 'Data curenta'],
        ];
    }

    public function buildVariables(?string $partnerCui, ?string $supplierCui, ?string $clientCui, array $contractContext = []): array
    {
        $vars = [];

        $partner = $partnerCui ? $this->fetchPartner($partnerCui) : [];
        $supplier = $supplierCui ? $this->fetchPartner($supplierCui) : [];
        $client = $clientCui ? $this->fetchPartner($clientCui) : [];
        $partnerCompany = $partnerCui ? $this->fetchCompany($partnerCui) : [];
        $supplierCompany = $supplierCui ? $this->fetchCompany($supplierCui) : [];
        $clientCompany = $clientCui ? $this->fetchCompany($clientCui) : [];

        $vars['partner.name'] = $partner['denumire'] ?? '';
        $vars['partner.cui'] = $partner['cui'] ?? '';
        $vars['partner.email'] = $partnerCompany['email'] ?? '';
        $vars['partner.phone'] = $partnerCompany['telefon'] ?? '';
        $vars['partner.address'] = $partnerCompany['adresa'] ?? '';
        $vars['partner.city'] = $partnerCompany['localitate'] ?? '';
        $vars['partner.county'] = $partnerCompany['judet'] ?? '';
        $vars['partner.country'] = $partnerCompany['tara'] ?? '';
        $vars['partner.reg_no'] = $partnerCompany['nr_reg_comertului'] ?? '';
        $vars['partner.bank'] = $partnerCompany['banca'] ?? '';
        $vars['partner.iban'] = $partnerCompany['iban'] ?? '';

        $vars['supplier.name'] = $supplier['denumire'] ?? '';
        $vars['supplier.cui'] = $supplier['cui'] ?? '';
        $vars['supplier.email'] = $supplierCompany['email'] ?? '';
        $vars['supplier.phone'] = $supplierCompany['telefon'] ?? '';

        $vars['client.name'] = $client['denumire'] ?? '';
        $vars['client.cui'] = $client['cui'] ?? '';
        $vars['client.email'] = $clientCompany['email'] ?? '';
        $vars['client.phone'] = $clientCompany['telefon'] ?? '';

        $vars['relation.supplier_cui'] = $supplierCui ?? '';
        $vars['relation.client_cui'] = $clientCui ?? '';
        $vars['relation.invoice_inbox_email'] = $this->fetchRelationEmail($supplierCui, $clientCui);

        $vars['contract.title'] = (string) ($contractContext['title'] ?? '');
        $vars['contract.created_at'] = (string) ($contractContext['created_at'] ?? date('Y-m-d'));
        $vars['date.today'] = date('Y-m-d');

        return $vars;
    }

    private function fetchPartner(string $cui): array
    {
        $cui = preg_replace('/\D+/', '', $cui);
        if ($cui === '' || !Database::tableExists('partners')) {
            return [];
        }

        return Database::fetchOne('SELECT cui, denumire FROM partners WHERE cui = :cui LIMIT 1', ['cui' => $cui]) ?? [];
    }

    private function fetchCompany(string $cui): array
    {
        $cui = preg_replace('/\D+/', '', $cui);
        if ($cui === '' || !Database::tableExists('companies')) {
            return [];
        }

        return Database::fetchOne(
            'SELECT cui, nr_reg_comertului, adresa, localitate, judet, tara, email, telefon, banca, iban
             FROM companies WHERE cui = :cui LIMIT 1',
            ['cui' => $cui]
        ) ?? [];
    }

    private function fetchRelationEmail(?string $supplierCui, ?string $clientCui): string
    {
        $supplierCui = $supplierCui ? preg_replace('/\D+/', '', $supplierCui) : '';
        $clientCui = $clientCui ? preg_replace('/\D+/', '', $clientCui) : '';
        if ($supplierCui === '' || $clientCui === '' || !Database::tableExists('partner_relations')) {
            return '';
        }

        $row = Database::fetchOne(
            'SELECT invoice_inbox_email FROM partner_relations WHERE supplier_cui = :supplier AND client_cui = :client LIMIT 1',
            ['supplier' => $supplierCui, 'client' => $clientCui]
        );

        return $row ? (string) ($row['invoice_inbox_email'] ?? '') : '';
    }
}
