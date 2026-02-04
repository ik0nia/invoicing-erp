<?php
    $title = 'Adauga utilizator';
    $action = 'admin/utilizatori/adauga';
    $form = $form ?? App\Support\Session::pull('user_form', []);
    if (empty($form['role']) && !empty($roles[0]['key'])) {
        $form['role'] = (string) $roles[0]['key'];
    }
    if (!isset($form['show_payment_details'])) {
        $form['show_payment_details'] = true;
    }
?>

<?php include BASE_PATH . '/resources/views/admin/users/form.php'; ?>
