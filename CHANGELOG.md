# Changelog

## v1.0.42

- Culori dark pentru randurile highlight.

## v1.0.41

- Ajustari dark mode pentru tabele si input-uri.

## v1.0.40

- Toggle pentru dark mode in bara de sus.

## v1.0.39

- pret_vanz exportat cu 4 zecimale fixe in JSON SAGA.

## v1.0.38

- pret_vanz in JSON SAGA la 4 zecimale.

## v1.0.37

- API SAGA foloseste comisionul facturii pentru pret_vanz.

## v1.0.36

- pret_vanz in JSON SAGA rotunjit la 2 zecimale.

## v1.0.35

- pret_vanz bazat pe total cu TVA + comision, fara TVA.
- Debug optional in API SAGA (?debug=1).

## v1.0.34

- pret_vanz = cost_total + comision (fara TVA).

## v1.0.33

- pret_vanz din JSON SAGA calculat din total client fara TVA.

## v1.0.32

- pret_vanz calculat cu comision (fara TVA) in JSON SAGA.

## v1.0.31

- JSON SAGA foloseste valori nete (fara TVA) din produse.

## v1.0.30

- Accepta GET pentru API importat SAGA.

## v1.0.29

- API pentru marcarea importului SAGA (processing -> imported).

## v1.0.28

- Citire fallback din .env pentru token SAGA.

## v1.0.27

- Auto-creare coloana saga_status la generare SAGA.

## v1.0.26

- Status JSON SAGA setat la processing dupa generare.

## v1.0.25

- Genereaza SAGA seteaza pending fara download.
- Buton Json SAGA foloseste API cu token.

## v1.0.24

- API SAGA afiseaza doar pachetele cu status pending.
- Buton Genereaza devine Json dupa setare pending.

## v1.0.23

- Eliminare generare AHK Saga din interfata.
- Fix eroare 500 la pachete confirmate.

## v1.0.22

- Buton Genereaza SAGA pentru pachete cu produse asociate.
- API SAGA JSON + status pending/executed.

## v1.0.21

- Evidentiere pachete cu toate produsele asociate SAGA.

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
