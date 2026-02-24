-- Reseteaza tranzactii bancare marcate "Procesat" dar fara incasare alocata
-- (create de vechiul cod care facea insert automat la click)

-- Pasul 1: Sterge incasarile create automat fara nicio alocare de factura
DELETE p
FROM payments_in p
LEFT JOIN payment_in_allocations a ON a.payment_in_id = p.id
WHERE a.id IS NULL;

-- Pasul 2: Reseteaza payment_in_id pe tranzactiile al caror payment a fost sters
UPDATE bank_transactions bt
LEFT JOIN payments_in p ON p.id = bt.payment_in_id
SET bt.payment_in_id = NULL
WHERE bt.payment_in_id IS NOT NULL
  AND p.id IS NULL;
