<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
// Custom entry point: /cabinet/ is the branded login page; wp-login.php keeps
// working for POST auth, logout, lost password and reset flows.
add_action('login_init', function () {
    if ('GET' !== strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET')) return;
    $action = $_GET['action'] ?? 'login';
    if (!in_array($action, ['login'], true)) return; // logout, lostpassword, rp stay native
    wp_safe_redirect(home_url('/cabinet/'));
    exit;
});
// Successful logins land in the NUTS panel.
add_filter('login_redirect', function ($redirect_to, $requested, $user) {
    if (is_wp_error($user) || !($user instanceof \WP_User)) return $redirect_to;
    if (!$user->has_cap('edit_pages')) return $redirect_to;
    return $requested && admin_url() !== $requested ? $redirect_to : admin_url('admin.php?page=nuts-cms');
}, 20, 3);
add_action('init', function () {
    if (is_admin()) return;
    $home_path = rtrim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
    $path = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
    if ($home_path.'/cabinet' !== $path) return;
    nocache_headers();
    if (($_GET['action'] ?? '') === 'logout') {
        if (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'log-out')) wp_logout();
        wp_safe_redirect(home_url('/cabinet/?loggedout=1'));
        exit;
    }
    if (is_user_logged_in()) {
        wp_safe_redirect(admin_url('admin.php?page=nuts-cms'));
        exit;
    }
    $error = '';
    $notice = ($_GET['loggedout'] ?? '') === '1' ? 'Вы вышли. До встречи!' : '';
    $redirect_to = admin_url('admin.php?page=nuts-cms');
    if ('POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
        $login = wp_unslash((string) ($_POST['log'] ?? ''));
        $password = (string) ($_POST['pwd'] ?? '');
        $remember = !empty($_POST['rememberme']);
        $requested = esc_url_raw(wp_unslash((string) ($_POST['redirect_to'] ?? '')));
        $result = wp_signon(['user_login' => $login, 'user_password' => $password, 'remember' => $remember], is_ssl());
        if (!is_wp_error($result)) {
            wp_safe_redirect(wp_validate_redirect($requested, $redirect_to));
            exit;
        }
        $error = $result->get_error_message() ?: 'Не удалось войти. Проверьте имя и пароль.';
    }
    $appearance = admin_appearance();
    $accent = sanitize_hex_color($appearance['accent'] ?? '') ?: '#096fd2';
    $title = get_bloginfo('name');
    $lost = wp_lostpassword_url(home_url('/cabinet/'));
    $logout = wp_logout_url(home_url('/cabinet/?loggedout=1'));
    header('Content-Type: text/html; charset=utf-8');
    status_header(200);
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex, nofollow"><title>Личный кабинет — ' . esc_html($title) . '</title><style>';
    echo 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(150deg,#f1f2f4 55%,#e4e9f2);font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#202020}';
    echo '.card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(20,30,50,.12);padding:44px 40px;width:340px;max-width:calc(100vw - 40px)}';
    echo '.brand{font-size:34px;font-weight:900;letter-spacing:-2px;line-height:1;margin:0 0 6px}.brand em{font-style:normal;color:' . $accent . '}';
    echo '.sub{font-size:11px;letter-spacing:1.6px;color:#7a7a7a;margin:0 0 30px;text-transform:uppercase}';
    echo 'label{display:block;font-size:12px;font-weight:600;color:#555;margin:0 0 6px}';
    echo 'input[type=text],input[type=password]{width:100%;box-sizing:border-box;font:inherit;padding:11px 13px;border:1px solid #d5d5d5;border-radius:8px;margin-bottom:16px}input:focus{outline:2px solid ' . $accent . ';outline-offset:1px;border-color:' . $accent . '}';
    echo 'button{width:100%;font:inherit;font-weight:700;padding:12px;border:0;border-radius:8px;background:' . $accent . ';color:#fff;cursor:pointer;margin-top:6px}';
    echo '.row{display:flex;justify-content:space-between;align-items:center;font-size:12px;margin:0 0 18px;color:#666}.row a{color:' . $accent . '}';
    echo '.err{background:#fdecea;color:#8b2c22;border-radius:8px;padding:11px 13px;font-size:13px;margin-bottom:16px}.ok{background:#e8f3ec;color:#205c33;border-radius:8px;padding:11px 13px;font-size:13px;margin-bottom:16px}';
    echo '.foot{margin-top:26px;font-size:11px;color:#9a9a9a;text-align:center}.foot a{color:#777}';
    echo '</style></head><body><main class="card"><h1 class="brand">NUTS<em> ПУС</em></h1><p class="sub">Панель управления сайтом</p>';
    if ($error) echo '<p class="err" role="alert">' . esc_html($error) . '</p>';
    if ($notice) echo '<p class="ok" role="status">' . esc_html($notice) . '</p>';
    echo '<form method="post" action="' . esc_url(home_url('/cabinet/')) . '"><input type="hidden" name="redirect_to" value="' . esc_attr($redirect_to) . '">';
    echo '<label for="nuts-login">Имя пользователя</label><input id="nuts-login" type="text" name="log" autocomplete="username" required autofocus>';
    echo '<label for="nuts-pass">Пароль</label><input id="nuts-pass" type="password" name="pwd" autocomplete="current-password" required>';
    echo '<div class="row"><label style="font-weight:400;display:flex;gap:7px;align-items:center;margin:0"><input type="checkbox" name="rememberme" value="forever">Запомнить меня</label><a href="' . esc_url($lost) . '">Забыли пароль?</a></div>';
    echo '<button type="submit">Войти в панель</button></form>';
    echo '<p class="foot">' . esc_html($title) . ' · <a href="' . esc_url(home_url('/')) . '">На сайт</a></p></main></body></html>';
    exit;
}, 1);
