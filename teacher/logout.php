<?php
/**
 * Teacher Logout with SweetAlert confirmation
 */

require_once '../includes/jwt_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If confirm flag is present, perform logout then show success message
if (isset($_GET['confirm']) && $_GET['confirm'] == '1') {
    // Clear all session variables
    $_SESSION = array();

    // Clear JWT cookies for teacher role
    JWTHelper::clearTokenCookies('teacher');

    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();

    // Show a small page that displays a SweetAlert success then redirects to login
    $loginUrl = 'login.php';
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Logged out</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Logged out',
            text: 'You have been successfully logged out.',
            timer: 1400,
            showConfirmButton: false,
            willClose: () => {
                window.location.href = '<?php echo $loginUrl; ?>';
            }
        });
        // Fallback redirect
        setTimeout(function(){ window.location.href = '<?php echo $loginUrl; ?>'; }, 2000);
    </script>
    </body>
    </html>
    <?php
    exit;
}

// Otherwise show confirmation dialog
$currentUrl = basename($_SERVER['PHP_SELF']);
$confirmUrl = $currentUrl . '?confirm=1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Logout</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
    document.addEventListener('DOMContentLoaded', function(){
        Swal.fire({
            title: 'Are you sure you want to logout?',
            text: "You'll need to login again to continue.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, logout',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // proceed to logout
                window.location.href = '<?php echo $confirmUrl; ?>';
            } else {
                // go back
                if (document.referrer) window.location.href = document.referrer;
                else window.location.href = 'dashboard.php';
            }
        });
    });
</script>
</body>
</html>
<?php
exit;
?>
