-- Patch: normalizeaza campul judet in tabelul companies
-- Elimina prefixul "Municipiul " (case-insensitive) din valorile existente.
-- Exemplu: "Municipiul București" -> "București"
-- Compatibil MySQL 5.7+ si MariaDB 10.3+

UPDATE companies
SET judet = TRIM(SUBSTRING(judet, LENGTH('Municipiul ') + 1))
WHERE LOWER(judet) LIKE 'municipiul %';
