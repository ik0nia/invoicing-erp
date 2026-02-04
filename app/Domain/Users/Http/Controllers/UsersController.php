<?php

namespace App\Domain\Users\Http\Controllers;

use App\Domain\Users\Models\Role;
use App\Domain\Users\Models\User;
use App\Domain\Users\Models\UserPermission;
use App\Domain\Users\Models\UserSupplierAccess;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class UsersController
{
    public function index(): void
    {
        Auth::requireAdmin();

        if (!$this->ensureUserTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        Role::ensureDefaults();
        UserSupplierAccess::ensureTable();

        $rows = Database::fetchAll(
            'SELECT u.*, GROUP_CONCAT(r.key ORDER BY r.key SEPARATOR ",") AS roles,
                    GROUP_CONCAT(r.label ORDER BY r.key SEPARATOR ",") AS role_labels
             FROM users u
             LEFT JOIN role_user ru ON ru.user_id = u.id
             LEFT JOIN roles r ON r.id = ru.role_id
             GROUP BY u.id
             ORDER BY u.id DESC'
        );

        $userIds = array_map(static fn (array $row) => (int) $row['id'], $rows);
        $supplierMap = UserSupplierAccess::supplierMapForUsers($userIds);
        $supplierNames = $this->supplierNameMap($supplierMap);

        $currentUser = Auth::user();
        $assignableRoles = $this->assignableRoles($currentUser);
        $users = [];
        foreach ($rows as $row) {
            $roles = array_filter(array_map('trim', explode(',', (string) $row['roles'])));
            $labels = array_filter(array_map('trim', explode(',', (string) $row['role_labels'])));
            $id = (int) $row['id'];
            $supplierCuis = $supplierMap[$id] ?? [];
            $supplierList = [];
            foreach ($supplierCuis as $cui) {
                $supplierList[] = $supplierNames[$cui] ?? $cui;
            }

            $primaryRole = $this->resolvePrimaryRoleFromKeys($roles);
            $users[] = [
                'id' => $id,
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
                'roles' => $roles,
                'role_labels' => $labels,
                'primary_role' => $primaryRole,
                'can_edit' => $this->canEditTargetUser($currentUser, $id, $primaryRole, $assignableRoles),
                'supplier_cuis' => $supplierCuis,
                'supplier_names' => $supplierList,
            ];
        }

        Response::view('admin/users/index', [
            'users' => $users,
            'currentUserId' => Auth::user()?->id ?? 0,
            'canEditUsers' => $this->canManageUsers($currentUser),
            'canDeleteUsers' => $currentUser?->isSuperAdmin() ?? false,
        ]);
    }

    public function create(): void
    {
        Auth::requireAdmin();

        $currentUser = Auth::user();
        if (!$this->canManageUsers($currentUser)) {
            Response::abort(403, 'Acces interzis.');
        }

        if (!$this->ensureUserTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        Role::ensureDefaults();
        UserSupplierAccess::ensureTable();
        User::ensureShowPaymentDetailsColumn();

        Response::view('admin/users/create', [
            'roles' => $this->roleOptions($currentUser),
            'suppliers' => $this->supplierOptions(),
            'canManagePackagePermission' => $this->canManagePackagePermission(Auth::user()),
            'canEditUsers' => $this->canManageUsers($currentUser),
            'canDeleteUsers' => $currentUser?->isSuperAdmin() ?? false,
        ]);
    }

    public function store(): void
    {
        Auth::requireAdmin();

        $currentUser = Auth::user();
        if (!$this->canManageUsers($currentUser)) {
            Response::abort(403, 'Acces interzis.');
        }

        if (!$this->ensureUserTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru utilizatori.');
            Response::redirect('/admin/utilizatori');
        }

        Role::ensureDefaults();
        UserSupplierAccess::ensureTable();
        User::ensureShowPaymentDetailsColumn();

        $payload = $_POST;
        $name = trim((string) ($payload['name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $passwordConfirm = (string) ($payload['password_confirmation'] ?? '');
        $role = trim((string) ($payload['role'] ?? ''));
        $supplierCuis = array_map('strval', (array) ($payload['supplier_cuis'] ?? []));
        $canRenamePackages = !empty($payload['can_rename_packages']);
        $showPaymentDetails = !empty($payload['show_payment_details']);

        $errors = [];

        if ($name === '' || $email === '' || $password === '') {
            $errors[] = 'Completeaza nume, email si parola.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalid.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Parolele nu coincid.';
        }

        $allowedRoles = array_column($this->roleOptions($currentUser), 'key');
        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'Rol invalid.';
        }

        if ($role === 'supplier_user' && empty($supplierCuis)) {
            $errors[] = 'Selecteaza cel putin un furnizor pentru utilizator.';
        }

        if (User::findByEmail($email)) {
            $errors[] = 'Exista deja un utilizator cu acest email.';
        }

        if (!empty($errors)) {
            Session::flash('error', $errors[0]);
            Session::flash('user_form', $payload);
            Response::redirect('/admin/utilizatori/adauga');
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        Role::clearForUser($user->id);
        Role::assignToUser($user->id, $role);

        if ($role === 'supplier_user') {
            UserSupplierAccess::replaceForUser($user->id, $supplierCuis);
        } else {
            UserSupplierAccess::replaceForUser($user->id, []);
        }

        if ($this->canManagePackagePermission(Auth::user())) {
            UserPermission::setForUser($user->id, UserPermission::RENAME_PACKAGES, $canRenamePackages);
        }
        User::setShowPaymentDetails($user->id, $showPaymentDetails);

        Session::flash('status', 'Utilizatorul a fost creat.');
        Response::redirect('/admin/utilizatori/edit?id=' . $user->id);
    }

    public function edit(): void
    {
        Auth::requireAdmin();

        if (!$this->ensureUserTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        Role::ensureDefaults();
        UserSupplierAccess::ensureTable();
        UserPermission::ensureTable();
        User::ensureShowPaymentDetailsColumn();

        $userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $user = $userId ? User::find($userId) : null;
        if (!$user) {
            Response::redirect('/admin/utilizatori');
        }

        $currentUser = Auth::user();
        if (!$this->canManageUsers($currentUser)) {
            Response::abort(403, 'Acces interzis.');
        }

        $roles = Role::forUser($user->id);
        $selectedRole = $this->resolvePrimaryRole($roles);
        if (!$this->canEditTargetUser($currentUser, $user->id, $selectedRole, $this->assignableRoles($currentUser))) {
            Response::abort(403, 'Acces interzis.');
        }
        $supplierCuis = UserSupplierAccess::suppliersForUser($user->id);
        $form = Session::pull('user_form', [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $selectedRole,
            'supplier_cuis' => $supplierCuis,
        ]);
        $form['can_rename_packages'] = isset($form['can_rename_packages'])
            ? (bool) $form['can_rename_packages']
            : UserPermission::userHas($user->id, UserPermission::RENAME_PACKAGES);
        $form['show_payment_details'] = isset($form['show_payment_details'])
            ? (bool) $form['show_payment_details']
            : $user->canViewPaymentDetails();

        Response::view('admin/users/edit', [
            'user' => $user,
            'form' => $form,
            'roles' => $this->roleOptions($currentUser, $selectedRole),
            'suppliers' => $this->supplierOptions(),
            'selectedRole' => $selectedRole,
            'selectedSuppliers' => $supplierCuis,
            'currentUserId' => Auth::user()?->id ?? 0,
            'canManagePackagePermission' => $this->canManagePackagePermission(Auth::user()),
            'canEditUsers' => $this->canManageUsers($currentUser),
            'canDeleteUsers' => $currentUser?->isSuperAdmin() ?? false,
        ]);
    }

    public function update(): void
    {
        Auth::requireAdmin();

        if (!$this->ensureUserTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru utilizatori.');
            Response::redirect('/admin/utilizatori');
        }

        Role::ensureDefaults();
        UserSupplierAccess::ensureTable();
        UserPermission::ensureTable();
        User::ensureShowPaymentDetailsColumn();

        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $user = $userId ? User::find($userId) : null;
        if (!$user) {
            Response::redirect('/admin/utilizatori');
        }

        $currentUser = Auth::user();
        if (!$this->canManageUsers($currentUser)) {
            Response::abort(403, 'Acces interzis.');
        }
        $assignableRoles = $this->assignableRoles($currentUser);
        $currentRole = $this->resolvePrimaryRole(Role::forUser($user->id));
        if (!$this->canEditTargetUser($currentUser, $user->id, $currentRole, $assignableRoles)) {
            Response::abort(403, 'Acces interzis.');
        }
        $allowedRoles = $assignableRoles;
        if ($currentUser && $currentUser->id === $user->id && !in_array($currentRole, $allowedRoles, true)) {
            $allowedRoles[] = $currentRole;
        }
        $canRenamePackages = !empty($_POST['can_rename_packages']);
        $showPaymentDetails = !empty($_POST['show_payment_details']);

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirmation'] ?? '');
        $role = trim((string) ($_POST['role'] ?? ''));
        $supplierCuis = array_map('strval', (array) ($_POST['supplier_cuis'] ?? []));
        $canRenamePackages = !empty($_POST['can_rename_packages']);

        $errors = [];

        if ($name === '' || $email === '') {
            $errors[] = 'Completeaza nume si email.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalid.';
        }
        if ($password !== '' && $password !== $passwordConfirm) {
            $errors[] = 'Parolele nu coincid.';
        }

        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'Rol invalid.';
        }
        if ($role === 'supplier_user' && empty($supplierCuis)) {
            $errors[] = 'Selecteaza cel putin un furnizor pentru utilizator.';
        }

        $existing = User::findByEmail($email);
        if ($existing && $existing->id !== $user->id) {
            $errors[] = 'Exista deja un utilizator cu acest email.';
        }

        if (!empty($errors)) {
            Session::flash('error', $errors[0]);
            Session::flash('user_form', $_POST);
            Response::redirect('/admin/utilizatori/edit?id=' . $user->id);
        }

        $params = [
            'name' => $name,
            'email' => $email,
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $user->id,
        ];
        $set = 'name = :name, email = :email, updated_at = :updated_at';

        if ($password !== '') {
            $set .= ', password = :password';
            $params['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        Database::execute('UPDATE users SET ' . $set . ' WHERE id = :id', $params);

        Role::clearForUser($user->id);
        Role::assignToUser($user->id, $role);

        if ($role === 'supplier_user') {
            UserSupplierAccess::replaceForUser($user->id, $supplierCuis);
        } else {
            UserSupplierAccess::replaceForUser($user->id, []);
        }

        if ($this->canManagePackagePermission(Auth::user())) {
            UserPermission::setForUser($user->id, UserPermission::RENAME_PACKAGES, $canRenamePackages);
        }

        if ($this->canManagePackagePermission(Auth::user())) {
            UserPermission::setForUser($user->id, UserPermission::RENAME_PACKAGES, $canRenamePackages);
        }
        User::setShowPaymentDetails($user->id, $showPaymentDetails);

        Session::flash('status', 'Utilizatorul a fost actualizat.');
        Response::redirect('/admin/utilizatori/edit?id=' . $user->id);
    }

    public function delete(): void
    {
        Auth::requireSuperAdmin();

        if (!$this->ensureUserTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru utilizatori.');
            Response::redirect('/admin/utilizatori');
        }

        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        if (!$userId) {
            Response::redirect('/admin/utilizatori');
        }

        $currentUserId = Auth::user()?->id ?? 0;
        if ($currentUserId && $currentUserId === $userId) {
            Session::flash('error', 'Nu poti sterge utilizatorul curent.');
            Response::redirect('/admin/utilizatori');
        }

        Database::execute('DELETE FROM users WHERE id = :id', ['id' => $userId]);

        Session::flash('status', 'Utilizatorul a fost sters.');
        Response::redirect('/admin/utilizatori');
    }


    private function ensureUserTables(): bool
    {
        if (!Database::tableExists('users') || !Database::tableExists('roles') || !Database::tableExists('role_user')) {
            return false;
        }

        return true;
    }

    private function roleOptions(?User $currentUser = null, ?string $includeRole = null): array
    {
        $allowed = $this->assignableRoles($currentUser);
        if ($currentUser && $currentUser->isSuperAdmin()) {
            $allowed = ['super_admin', 'admin', 'contabil', 'operator', 'supplier_user'];
        }
        if ($includeRole && !in_array($includeRole, $allowed, true)) {
            $allowed[] = $includeRole;
        }
        $map = [];
        foreach (Role::all() as $role) {
            if (in_array($role->key, $allowed, true)) {
                $map[$role->key] = $role->label;
            }
        }

        $options = [];
        foreach ($allowed as $key) {
            if (!isset($map[$key])) {
                continue;
            }
            $options[] = [
                'key' => $key,
                'label' => $map[$key],
            ];
        }

        return $options;
    }

    private function supplierOptions(): array
    {
        $items = [];

        if (Database::tableExists('commissions')) {
            $rows = Database::fetchAll(
                'SELECT DISTINCT c.supplier_cui, p.denumire AS supplier_name
                 FROM commissions c
                 LEFT JOIN partners p ON p.cui = c.supplier_cui
                 ORDER BY supplier_name ASC'
            );
            foreach ($rows as $row) {
                $cui = (string) $row['supplier_cui'];
                if ($cui === '') {
                    continue;
                }
                $items[$cui] = (string) ($row['supplier_name'] ?? $cui);
            }
        }

        if (Database::tableExists('invoices_in')) {
            $rows = Database::fetchAll(
                'SELECT DISTINCT supplier_cui, supplier_name
                 FROM invoices_in
                 WHERE supplier_cui IS NOT NULL AND supplier_cui <> ""
                 ORDER BY supplier_name ASC'
            );
            foreach ($rows as $row) {
                $cui = (string) $row['supplier_cui'];
                if ($cui === '') {
                    continue;
                }
                if (!isset($items[$cui]) || $items[$cui] === '') {
                    $items[$cui] = (string) ($row['supplier_name'] ?? $cui);
                }
            }
        }

        $options = [];
        foreach ($items as $cui => $name) {
            $options[] = [
                'cui' => $cui,
                'name' => $name !== '' ? $name : $cui,
            ];
        }

        usort($options, static function (array $a, array $b): int {
            return strcasecmp($a['name'], $b['name']);
        });

        return $options;
    }

    private function supplierNameMap(array $supplierMap): array
    {
        $cuis = [];
        foreach ($supplierMap as $list) {
            foreach ($list as $cui) {
                $cuis[$cui] = true;
            }
        }

        if (empty($cuis)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        $index = 0;
        foreach (array_keys($cuis) as $cui) {
            $key = 'c' . $index++;
            $placeholders[] = ':' . $key;
            $params[$key] = $cui;
        }

        $map = [];

        if (Database::tableExists('partners')) {
            $rows = Database::fetchAll(
                'SELECT cui, denumire FROM partners WHERE cui IN (' . implode(',', $placeholders) . ')',
                $params
            );
            foreach ($rows as $row) {
                $map[(string) $row['cui']] = (string) $row['denumire'];
            }
        }

        if (Database::tableExists('invoices_in')) {
            $rows = Database::fetchAll(
                'SELECT DISTINCT supplier_cui, supplier_name FROM invoices_in
                 WHERE supplier_cui IN (' . implode(',', $placeholders) . ')',
                $params
            );
            foreach ($rows as $row) {
                $cui = (string) $row['supplier_cui'];
                if (!isset($map[$cui]) || $map[$cui] === '') {
                    $map[$cui] = (string) ($row['supplier_name'] ?? $cui);
                }
            }
        }

        return $map;
    }

    private function resolvePrimaryRole(array $roles): string
    {
        $priority = ['super_admin', 'admin', 'contabil', 'operator', 'supplier_user'];

        foreach ($priority as $key) {
            foreach ($roles as $role) {
                if ($role->key === $key) {
                    return $key;
                }
            }
        }

        return $priority[1] ?? 'admin';
    }

    private function resolvePrimaryRoleFromKeys(array $roles): string
    {
        $priority = ['super_admin', 'admin', 'contabil', 'operator', 'supplier_user'];

        foreach ($priority as $key) {
            if (in_array($key, $roles, true)) {
                return $key;
            }
        }

        return $priority[1] ?? 'admin';
    }

    private function canManagePackagePermission(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'contabil', 'operator']);
    }

    private function canManageUsers(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'contabil', 'operator']);
    }

    private function assignableRoles(?User $user): array
    {
        if (!$user) {
            return [];
        }
        if ($user->isSuperAdmin()) {
            return ['super_admin', 'admin', 'contabil', 'operator', 'supplier_user'];
        }
        if ($user->hasRole('admin')) {
            return ['contabil', 'operator', 'supplier_user'];
        }
        if ($user->hasRole('contabil')) {
            return ['operator', 'supplier_user'];
        }
        if ($user->hasRole('operator')) {
            return ['supplier_user'];
        }

        return [];
    }

    private function canEditTargetUser(?User $currentUser, int $targetUserId, string $targetRole, array $assignableRoles): bool
    {
        if (!$currentUser) {
            return false;
        }
        if ($currentUser->isSuperAdmin()) {
            return true;
        }
        if ($currentUser->id === $targetUserId) {
            return true;
        }
        return in_array($targetRole, $assignableRoles, true);
    }
}
