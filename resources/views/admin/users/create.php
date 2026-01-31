<?php
    $title = 'Adauga utilizator';
    $action = 'admin/utilizatori/adauga';
    $form = $form ?? App\Support\Session::pull('user_form', []);
    if (empty($form['role'])) {
        $form['role'] = 'admin';
    }
?>

<?php include BASE_PATH . '/resources/views/admin/users/form.php'; ?>
