<?php

namespace App\Domain\Invoices\Services;

class SagaAhkGenerator
{
    public function buildScript(array $packages, string $defaultDate): string
    {
        $lines = [];
        $lines[] = 'PgUp::';
        $lines[] = '{';

        $index = 0;
        foreach ($packages as $package) {
            if ($index > 0) {
                $lines[] = '';
            }
            $lines = array_merge($lines, $this->buildPackageLines($package, $defaultDate));
            $index++;
        }

        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    private function buildPackageLines(array $package, string $defaultDate): array
    {
        $packageNo = (string) ($package['package_no'] ?? '');
        $label = (string) ($package['label'] ?? '');
        $date = (string) ($package['date'] ?? $defaultDate);
        $total = $this->formatNumber((float) ($package['total'] ?? 0), 2);

        if ($label === '') {
            $label = 'Pachet de produse #' . $packageNo;
        }

        $lines = [];
        $lines[] = '    ; Creare de Pachet in Saga';
        $lines[] = '    Send("{Alt down}")';
        $lines[] = '    Sleep(100)';
        $lines[] = '    Send("o")';
        $lines[] = '    Sleep(200)';
        $lines[] = '    Send("p")';
        $lines[] = '    Sleep(100)';
        $lines[] = '    Send("{Alt up}")';
        $lines[] = '';
        $lines[] = '    Sleep(200)';
        $lines[] = '    Send("!a")';
        $lines[] = '';
        $lines[] = '    Sleep(300)';
        $lines[] = '    SendText("' . $this->formatSendText($date) . '")';
        $lines[] = '    Sleep(150)';
        $lines[] = '    Send("{Down}")';
        $lines[] = '    Sleep(150)';
        $lines[] = '    Send("{Tab}")';
        $lines[] = '    Sleep(300)';
        $lines[] = '';
        $lines[] = '    Sleep(300)';
        $lines[] = '    Send("{Tab}")';
        $lines[] = '';
        $lines[] = '    Sleep(300)';
        $lines[] = '    SendText("' . $this->formatSendText($label) . '")';
        $lines[] = '';
        $lines[] = '    ; === CE AI CERUT NOU ===';
        $lines[] = '';
        $lines[] = '    Sleep(700)';
        $lines[] = '    Send("{Tab}")';
        $lines[] = '';
        $lines[] = '    Sleep(200)';
        $lines[] = '    Send("!d")';
        $lines[] = '';
        $lines[] = '    Sleep(200)';
        $lines[] = '    Send("{Left}")';
        $lines[] = '';
        $lines[] = '    Sleep(300)';
        $lines[] = '    SendText("' . $this->formatSendText($packageNo) . '") ; Cod pachet';
        $lines[] = '';
        $lines[] = '    Sleep(300)';
        $lines[] = '    Send("{Tab}{Tab}") ; aici Tab Tab poate fi inlocuit dupa nevoie';
        $lines[] = '';
        $lines[] = '    Sleep(300)';
        $lines[] = '    SendText("' . $this->formatSendText('buc') . '")';
        $lines[] = '';
        $lines[] = '    Sleep(200)';
        $lines[] = '    Send("!s")';
        $lines[] = '';
        $lines[] = '    Sleep(200)';
        $lines[] = '    Send("!i")';
        $lines[] = '';
        $lines[] = '    ; === ADAUGARE FINALA CERUTA ===';
        $lines[] = '';
        $lines[] = '    Sleep(300)';
        $lines[] = '    Send("{Tab}")';
        $lines[] = '';
        $lines[] = '    Sleep(300)';
        $lines[] = '    SendText("' . $this->formatSendText('1') . '")';
        $lines[] = '';
        $lines[] = '    Sleep(300)';
        $lines[] = '    Send("{Tab}")';
        $lines[] = '';
        $lines[] = '    Sleep(300)';
        $lines[] = '    SendText("' . $this->formatSendText($total) . '") ; valoare produs finit';
        $lines[] = '';
        $lines[] = '    Sleep(200)';
        $lines[] = '    Send("!s")';
        $lines[] = '';

        $items = $package['lines'] ?? [];
        $items = is_array($items) ? array_values($items) : [];

        if (!empty($items)) {
            $lines = array_merge($lines, $this->buildFirstItemLines($items[0]));

            for ($i = 1; $i < count($items); $i++) {
                $lines = array_merge($lines, $this->buildNextItemLines($items[$i], $i + 1));
            }
        }

        $lines[] = '';
        $lines[] = '    ; ALT V - validare Productie Pachet';
        $lines[] = '    Sleep(200)';
        $lines[] = '    Send("!v")';

        return $lines;
    }

    private function buildFirstItemLines(array $item): array
    {
        $name = $this->formatSendText((string) ($item['name'] ?? 'Produs'));
        $qty = $this->formatSendText($this->formatNumber((float) ($item['quantity'] ?? 0), 3));
        $total = $this->formatSendText($this->formatNumber((float) ($item['total'] ?? 0), 2));

        return [
            '    ; === Adaugare primul produs ===',
            '',
            '    Sleep(300)',
            '    Send("!d")              ; Alt + D',
            '    Sleep(200)',
            '    Send("{Down}")',
            '    Sleep(150)',
            '    Send("{Down}")',
            '    Sleep(150)',
            '    Send("{Tab}")',
            '    Sleep(150)',
            '    Send("{Down}")',
            '    Sleep(150)',
            '    Send("{Tab}{Tab}")',
            '    Sleep(300)',
            '    SendText("' . $name . '") ; text',
            '',
            '    Sleep(300)',
            '    Send("{Tab}")',
            '    Sleep(300)',
            '    SendText("' . $qty . '")            ; cantitate',
            '',
            '    Sleep(300)',
            '    Send("{Tab}")',
            '    Sleep(300)',
            '    SendText("' . $total . '")       ; valoare',
            '',
            '    Sleep(200)',
            '    Send("!s")               ; Alt + S',
            '',
        ];
    }

    private function buildNextItemLines(array $item, int $index): array
    {
        $name = $this->formatSendText((string) ($item['name'] ?? 'Produs'));
        $qty = $this->formatSendText($this->formatNumber((float) ($item['quantity'] ?? 0), 3));
        $total = $this->formatSendText($this->formatNumber((float) ($item['total'] ?? 0), 2));

        return [
            '; === Adaugare Produs ' . $index . ' ===',
            '',
            '    Sleep(300)',
            '    Send("!d")              ; Alt + D',
            '    Sleep(200)',
            '    Send("{Tab}")',
            '    Sleep(150)',
            '    Send("{Tab}")',
            '    Sleep(150)',
            '    Send("{Tab}")',
            '    Sleep(150)',
            '    Sleep(300)',
            '    SendText("' . $name . '") ; text',
            '',
            '    Sleep(300)',
            '    Send("{Tab}")',
            '    Sleep(300)',
            '    SendText("' . $qty . '")            ; cantitate',
            '',
            '    Sleep(300)',
            '    Send("{Tab}")',
            '    Sleep(300)',
            '    SendText("' . $total . '")       ; valoare',
            '',
            '    Sleep(200)',
            '    Send("!s")               ; Alt + S',
            '',
        ];
    }

    private function escapeText(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        $value = str_replace('"', "'", $value);
        $value = str_replace(['{', '}'], ['(', ')'], $value);
        $value = trim($value);

        return $value === '' ? '-' : $value;
    }

    private function formatSendText(string $value): string
    {
        $value = $this->escapeText($value);

        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value, 'UTF-8');
        }

        return strtoupper($value);
    }

    private function formatNumber(float $value, int $decimals): string
    {
        $formatted = number_format($value, $decimals, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
