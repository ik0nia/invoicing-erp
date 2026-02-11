# Changelog

## v1.0.20

- Fix import CSV SAGA pentru pachete confirmate.
- Normalizare uppercase la comparare pachete in import.

## v1.0.19

- Bifa verde pe produse cu cod SAGA si stoc suficient.
- Verificare stoc SAGA pentru pachete in lista confirmata.
## v1.0.18

- Endpoint GET pentru import stoc (instructiuni).

## v1.0.17

- Endpoint import stoc CSV cu token (cod/denumire/stoc).

## v1.0.16

- Afisare debug pentru comparare SAGA la import.

## v1.0.15

- Debug comparare CSV SAGA in pachete confirmate.

## v1.0.14

- Matching SAGA pe denumire completa + #numar pachet (uppercase).

## v1.0.13

- Matching SAGA strict pe denumire (uppercase).

## v1.0.12

- Matching SAGA dupa numar pachet / denumire normalizata.

## v1.0.11

- Accepta CSV fara coloana TVA (foloseste TVA pachet).

## v1.0.10

- Fix afisare formular import CSV SAGA pentru contabil/super admin.

## v1.0.9

- Import CSV SAGA pentru pachete confirmate cu comparare valori.

## v1.0.8

- Comision default furnizor in editare companie + salvare partiala.
- Status Detalii complete/lipsa bazat pe campuri obligatorii.

## v1.0.7

- Acces Setari doar pentru super admin.
- Meniu Utilizatori vizibil pentru admin/contabil/operator.

## v1.0.6

- Management utilizatori cu roluri limitate si acces personalizat.
- Permisiune pentru detalii incasari/plati + filtrare istorice pe furnizor/client.

## v1.0.5

- Comision default pe furnizor + auto-completare la asocieri.

## v1.0.4

- Autoselect furnizor in factura manuala cand exista unul singur.

## v1.0.3

- Blocare redenumire pachete dupa confirmare.
- Acces Saga doar pentru super admin si contabil (download si pagina).

## v1.0.2

- Rol Operator (Deon) cu acces similar admin/contabil, fara stergere.
- Acces doar la istoric pentru incasari si plati.

## v1.0.1

- Hotfix plati furnizori: suma editabila si auto-alocare dupa incasari nete.
- Alocari pe facturi bazate pe incasari minus comision.
- Afisare incasat net si disponibil in lista de alocare.

## v1.0.0

- Lansare ERP completa pentru facturare furnizori/clienti.
- Import XML (UBL) + vizualizare factura furnizor formatata.
- Facturi manuale, pachete de produse, confirmari, drag & drop.
- Integrare FGO: generare, printare, stornare, link PDF.
- Plati furnizori si incasari clienti cu alocari partiale.
- Rapoarte cashflow + export CSV + print situatie.
- Administrare utilizatori si roluri (super_admin/admin/contabil/staff/supplier_user).
- Setari generale, branding, API keys, generare/curatare demo.

## v0.0.2

- Trecere la aplicatie custom PHP (fara composer) pentru shared hosting.

## v0.0.1

- Initializare proiect, auth, companii, roluri, setari.
