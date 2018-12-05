<?php

session_start();
$invalid = false;

if (!empty($_GET['username']) && !empty($_GET['password'])) {
    $invalid  = true;
    $username = $_GET['username'];
    $password = $_GET['password'];

    if (is_file('../config.json') && is_readable('../config.json')) {
        $config    = json_decode(file_get_contents('../config.json'));
        $adminUser = $config->sys_admin_username;
        $adminPass = $config->sys_admin_password;
    }

    if (empty($adminUser) || empty($adminPass) || ($adminUser == $username && $adminPass == $password)) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: " . str_replace('login', 'index', strtok($_SERVER['REQUEST_URI'], '?')));
    }
}

?>

<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta charset="utf-8">
        <title>UniFi API browser</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">

        <!-- load the default Bootstrap CSS file from CDN -->
        <link rel="stylesheet" href="../assets/admin/css/bootstrap.min.css">

        <!-- placeholder to dynamically load the appropriate Bootswatch CSS file from CDN -->
        <link rel="stylesheet" href="../assets/admin/css/bootstrap.slate.min.css">

        <!-- define favicon  -->
        <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
        <link rel="icon" sizes="16x16" href="favicon.ico" type="image/x-icon" >
    </head>
    <body>
        <div class="container">
            <?php if($invalid):?>
            <div class="alert alert-danger alert-dismissible fade in" role="alert" id="invalid-login">
                <button type="button" class="close" data-dismiss="alert" aria-label="Zatvori"><span aria-hidden="true">&times;</span></button>
                <h4>Korisničko ime ili lozinka nisu ispravni!</h4>
                <p>Proverite unete podatke.</p>
            </div>
            <?php endif; ?>

            <form id="login" class="form-signin" style="max-width: 350px; margin: auto; padding-top: 100px;" action="login.php" method="GET">
                <h2 class="form-signin-heading">Prijavi se</h2>
                <label for="username" class="sr-only">Korisničko ime</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Korisničko ime" required autofocus>
                <label for="password" class="sr-only">Lozinka</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Lozinka" required>
                <button class="btn btn-lg btn-primary btn-block" name="login" type="submit">Prijava</button>
            </form>

        </div>

        <script src="../assets/admin/js/jquery-2.2.4.min.js"></script>
        <script src="../assets/admin/js/bootstrap.min.js"></script>
    </body>
</html>