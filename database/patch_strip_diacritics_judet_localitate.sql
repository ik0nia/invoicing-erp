-- Strip Romanian diacritics from companies.judet and companies.localitate.
-- Handles both comma-below variants (ș/ț — correct) and cedilla variants (ş/ţ — legacy).

UPDATE companies
SET
    judet = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
        -- Remove "Municipiul " prefix first (case-insensitive handled by application layer; SQL handles exact case)
        judet,
        'ă', 'a'), 'Ă', 'A'),
        'â', 'a'), 'Â', 'A'),
        'î', 'i'), 'Î', 'I'),
        'ș', 's'), 'Ș', 'S'),
        'ț', 't'), 'Ț', 'T'),
        'ş', 's'), 'Ş', 'S'),
        'ţ', 't'), 'Ţ', 'T'),

    localitate = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
        localitate,
        'ă', 'a'), 'Ă', 'A'),
        'â', 'a'), 'Â', 'A'),
        'î', 'i'), 'Î', 'I'),
        'ș', 's'), 'Ș', 'S'),
        'ț', 't'), 'Ț', 'T'),
        'ş', 's'), 'Ş', 'S'),
        'ţ', 't'), 'Ţ', 'T');
