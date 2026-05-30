<?php

use Core\Session;

$flash = Session::getFlash();

if($flash):

$class = 'alert-info';

if($flash['type'] == 'success'){
    $class = 'alert-success';
}

if($flash['type'] == 'error'){
    $class = 'alert-danger';
}

if($flash['type'] == 'warning'){
    $class = 'alert-warning';
}
?>

<div class="alert <?= $class; ?> alert-dismissible fade show flashMessage">

    <?= $flash['message']; ?>

    <button
    type="button"
    class="close"
    data-dismiss="alert"
    >

        <span>&times;</span>

    </button>

</div>

<?php endif; ?>