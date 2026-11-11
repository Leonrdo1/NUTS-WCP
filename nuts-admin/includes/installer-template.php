<?php
// Шаблон однофайлового установщика NUTS ПУС. Генератор подставляет значения вместо маркеров __NUTS_*__.
// Маркер __NUTS_TEMPLATE_GUARD__ в файле остаётся только в шаблоне — по нему узнаём неподготовленную копию.
if (strpos(file_get_contents(__FILE__), '__NUTS_TEMPLATE_GUARD__') !== false) { http_response_code(403); exit('Это шаблон установщика NUTS ПУС. Скачайте готовый файл в разделе «Установщик сборки» панели.'); }

$NUTS_PAYLOAD = '__NUTS_PAYLOAD__';
$NUTS_VERSION = '__NUTS_VERSION__';
$NUTS_BUILD = '__NUTS_BUILD__';
$NUTS_ACCENT = '__NUTS_ACCENT__';

$step = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'run' : 'form';
$accent = preg_match('/^#[0-9a-fA-F]{6}$/D', $NUTS_ACCENT) ? $NUTS_ACCENT : '#096fd2';
$root = __DIR__;
$error = '';

function nuts_installer_head($accent, $title) {
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex, nofollow"><title>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</title><style>';
    echo 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(150deg,#f1f2f4 55%,#e4e9f2);font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#202020}';
    echo '.card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(20,30,50,.12);padding:40px 36px;width:460px;max-width:calc(100vw - 40px);box-sizing:border-box}';
    echo '.brand{font-size:30px;font-weight:900;letter-spacing:-2px;line-height:1;margin:0 0 4px}.brand em{font-style:normal;color:'.$accent.'}';
    echo '.sub{font-size:11px;letter-spacing:1.6px;color:#7a7a7a;margin:0 0 24px;text-transform:uppercase}';
    echo 'fieldset{border:1px solid #e3e3e3;border-radius:10px;margin:0 0 18px;padding:16px}legend{font-size:12px;font-weight:700;color:#555;padding:0 6px}';
    echo 'label{display:block;font-size:12px;font-weight:600;color:#555;margin:0 0 5px}';
    echo 'input[type=text],input[type=password],input[type=email]{width:100%;box-sizing:border-box;font:inherit;padding:10px 12px;border:1px solid #d5d5d5;border-radius:8px;margin-bottom:13px}input:focus{outline:2px solid '.$accent.';outline-offset:1px;border-color:'.$accent.'}';
    echo 'button{width:100%;font:inherit;font-weight:700;padding:13px;border:0;border-radius:8px;background:'.$accent.';color:#fff;cursor:pointer}button:disabled{opacity:.6}';
    echo '.row{display:flex;gap:12px}.row label{flex:1}';
    echo '.chk{display:flex!important;align-items:center;gap:8px;font-weight:400!important;font-size:13px;margin:0 0 13px}';
    echo '.log{background:#0f172a;color:#d7e3f4;border-radius:10px;padding:16px;font:12px/1.7 ui-monospace,Menlo,monospace;max-height:260px;overflow:auto;white-space:pre-wrap;margin:0 0 16px}';
    echo '.ok{background:#e8f3ec;color:#205c33;border-radius:8px;padding:12px 14px;font-size:13px;margin:0 0 14px}.err{background:#fdecea;color:#8b2c22;border-radius:8px;padding:12px 14px;font-size:13px;margin:0 0 14px}';
    echo '.btn{display:inline-block;background:'.$accent.';color:#fff!important;text-decoration:none;padding:11px 18px;border-radius:8px;font-weight:700;margin-right:10px}';
    echo '.ghost{display:inline-block;background:#efefef;color:#333!important;text-decoration:none;padding:11px 18px;border-radius:8px}';
    echo '.foot{margin-top:22px;font-size:11px;color:#9a9a9a}.muted{font-size:12px;color:#777;margin-top:-8px;margin-bottom:13px}';
    echo '</style></head><body><main class="card"><h1 class="brand">NUTS<em> ПУС</em></h1><p class="sub">'.$title.'</p>';
}
function nuts_installer_foot($version, $build) {
    echo '<p class="foot">Установщик NUTS ПУС · сборка N '.$version.' · '.$build.'.<br>После установки удалите этот файл с сервера.</p></main></body></html>';
}
function nuts_fail($message) { echo '<p class="err" role="alert">'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</p><p><a class="ghost" href="javascript:history.back()">← Назад</a></p>'; }
function nuts_log($text) { echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8')."\n"; flush(); }
function nuts_salts() {
    $map = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_[]{}<>~`+=,.;:/?|';
    $out = [];
    foreach (['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $name) {
        $s = '';
        for ($i = 0; $i < 64; $i++) $s .= $map[random_int(0, strlen($map) - 1)];
        $out[$name] = $s;
    }
    return $out;
}

if ($step === 'form') {
    nuts_installer_head($accent, 'Установка стартовой сборки');
    $checks = [];
    $checks[] = [version_compare(PHP_VERSION, '8.0', '>='), 'PHP 8.0+ (у вас '.PHP_VERSION.')'];
    $checks[] = [extension_loaded('mysqli'), 'расширение mysqli'];
    $checks[] = [extension_loaded('zip'), 'расширение ZipArchive'];
    $checks[] = [is_writable($root), 'запись в текущую папку'];
    $bad = array_filter($checks, fn($c) => !$c[0]);
    if ($bad) {
        echo '<p class="err">Окружение не готово:</p><ul>';
        foreach ($bad as $c) echo '<li>'.htmlspecialchars($c[1], ENT_QUOTES, 'UTF-8').'</li>';
        echo '</ul>';
        nuts_installer_foot($NUTS_VERSION, $NUTS_BUILD);
        exit;
    }
    $suggest = 'Nut5'.substr(str_replace(['+','/','='], '', base64_encode(random_bytes(8))), 0, 10);
    echo '<form method="post"><fieldset><legend>База данных</legend>';
    echo '<p class="muted">Создайте базу в панели хостинга (раздел «Базы данных») — установщик подключится к ней и попробует создать таблицы. Если у учётной записи есть право CREATE DATABASE, базу можно не создавать заранее.</p>';
    echo '<label>Хост</label><input type="text" name="dbhost" value="localhost" required>';
    echo '<div class="row"><div style="flex:1"><label>Имя базы</label><input type="text" name="dbname" required></div><div style="flex:1"><label>Префикс таблиц</label><input type="text" name="dbprefix" value="wp_"></div></div>';
    echo '<label>Пользователь</label><input type="text" name="dbuser" autocomplete="off" required>';
    echo '<label>Пароль</label><input type="password" name="dbpass" autocomplete="off" required></fieldset>';
    echo '<fieldset><legend>Сайт и администратор</legend>';
    echo '<label>Название сайта</label><input type="text" name="title" value="Мой сайт" required>';
    echo '<div class="row"><div style="flex:1"><label>Логин администратора</label><input type="text" name="admin" autocomplete="off" required></div><div style="flex:1"><label>Пароль</label><input type="password" name="pass" value="'.htmlspecialchars($suggest, ENT_QUOTES, 'UTF-8').'" required></div></div>';
    echo '<label>Электронная почта</label><input type="email" name="email" required>';
    echo '<label class="chk"><input type="checkbox" name="noindex" value="1" checked> Скрыть сайт от поисковых систем (рекомендуется для стенда)</label>';
    echo '</fieldset><button type="submit">Установить</button></form>';
    nuts_installer_foot($NUTS_VERSION, $NUTS_BUILD);
    exit;
}

// ==== Установка ====
@set_time_limit(600);
@ini_set('memory_limit', '256M');
header('Content-Type: text/html; charset=utf-8');
nuts_installer_head($accent, 'Установка стартовой сборки');
echo '<div class="log">';
$fail = function ($message) { echo '</div>'; nuts_fail($message); nuts_installer_foot($GLOBALS['NUTS_VERSION'], $GLOBALS['NUTS_BUILD']); exit; };

$dbhost = trim((string) ($_POST['dbhost'] ?? 'localhost'));
$dbname = trim((string) ($_POST['dbname'] ?? ''));
$dbuser = trim((string) ($_POST['dbuser'] ?? ''));
$dbpass = (string) ($_POST['dbpass'] ?? '');
$dbprefix = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_POST['dbprefix'] ?? 'wp_'))) ?: 'wp_';
$title = trim((string) ($_POST['title'] ?? '')) ?: 'Мой сайт';
$admin = trim((string) ($_POST['admin'] ?? ''));
$pass = (string) ($_POST['pass'] ?? '');
$email = trim((string) ($_POST['email'] ?? ''));
$noindex = !empty($_POST['noindex']);
if ($dbname === '' || $admin === '' || $pass === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $fail('Заполните все поля: база данных, логин, пароль и корректный адрес почты.');

$host = $dbhost; $port = 3306;
if (preg_match('/^([^:]+):(\d+)$/', $dbhost, $m)) { $host = $m[1]; $port = (int) $m[2]; }
nuts_log('→ Подключаемся к базе данных…');
mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli($host, $dbuser, $dbpass, '', $port);
if ($db->connect_error) $fail('Не удалось подключиться к MySQL: '.$db->connect_error);
if (!$db->select_db($dbname)) {
    if (!$db->query('CREATE DATABASE `'.$db->real_escape_string($dbname).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
        $fail('Базы «'.$dbname.'» нет, а создать её не удалось. Создайте базу в панели хостинга и повторите шаг.');
    }
    $db->select_db($dbname);
    nuts_log('  База создана.');
}
nuts_log('  Подключение установлено.');

$wpzip = $root.'/wordpress-install.zip';
if (!file_exists($wpzip)) {
    nuts_log('→ Скачиваем WordPress (ru.wordpress.org)…');
    $urls = ['https://ru.wordpress.org/latest-ru_RU.zip', 'https://wordpress.org/latest.zip'];
    $ok = false;
    foreach ($urls as $url) {
        $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'NUTS-Installer']]));
        if ($data && strlen($data) > 1000000) { file_put_contents($wpzip, $data); $ok = true; nuts_log('  Скачано '.round(strlen($data) / 1048576, 1).' МБ.'); break; }
    }
    if (!$ok) $fail('Не удалось скачать WordPress (нет доступа к ru.wordpress.org из этого хостинга). Загрузите архив wordpress-ru.zip через панель хостинга и повторите — установщик найдёт файл рядом с собой.');
} else {
    nuts_log('→ Найден готовый архив WordPress рядом с установщиком.');
}

nuts_log('→ Распаковываем WordPress…');
$zip = new ZipArchive();
if ($zip->open($wpzip) !== true) $fail('Архив WordPress повреждён. Удалите wordpress-install.zip и повторите.');
$zip->extractTo($root);
$zip->close();
$wproot = is_dir($root.'/wordpress') ? $root.'/wordpress' : $root;
nuts_log('  Готово.');

nuts_log('→ Создаём wp-config.php…');
$sample = file_get_contents($wproot.'/wp-config-sample.php');
if ($sample === false) $fail('wp-config-sample.php не найден в архиве.');
$set_define = function ($code, $name, $value) {
    return preg_replace_callback("/define\(\s*'".$name."',\s*'[^']*'\s*\);/", function () use ($name, $value) {
        return "define('".$name."', '".addslashes($value)."');";
    }, $code);
};
$config = $set_define($sample, 'DB_NAME', $dbname);
$config = $set_define($config, 'DB_USER', $dbuser);
$config = $set_define($config, 'DB_PASSWORD', $dbpass);
$config = $set_define($config, 'DB_HOST', $dbhost);
$config = preg_replace_callback('/\$table_prefix\s*=\s*\'[^\']*\';/', function () use ($dbprefix) { return "\$table_prefix = '".$dbprefix."';"; }, $config);
foreach (nuts_salts() as $name => $value) {
    $config = $set_define($config, $name, $value);
}
if (strpos($config, 'придумайте') !== false || strpos($config, 'database_name_here') !== false) $fail('Не удалось подготовить wp-config.php.');
file_put_contents($wproot.'/wp-config.php', $config);
nuts_log('  Ключи безопасности сгенерированы.');

nuts_log('→ Устанавливаем WordPress…');
if ($wproot !== $root) {
    foreach (glob($wproot.'/{.*,*}', GLOB_BRACE) as $item) {
        if (in_array(basename($item), ['.', '..'], true)) continue;
        rename($item, $root.'/'.basename($item));
    }
    rmdir($wproot);
    $wproot = $root;
}
define('WP_INSTALLING', true);
require $wproot.'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/upgrade.php';
wp_install($title, $admin, $email, !$noindex ? 1 : 0, '', $pass, 'ru_RU');
if (!is_blog_installed()) $fail('wp_install не смог создать таблицы.');
update_option('blogdescription', 'Сайт на NUTS ПУС.');
update_option('timezone_string', 'Europe/Moscow');
global $wp_rewrite;
$wp_rewrite->set_permalink_structure('/%postname%/');
$wp_rewrite->flush_rules(true);
nuts_log('  WordPress установлен.'.($noindex ? ' Сайт скрыт от поисковиков.' : ''));

nuts_log('→ Устанавливаем NUTS ПУС N '.$NUTS_VERSION.'…');
$plugzip = tempnam(sys_get_temp_dir(), 'nuts');
file_put_contents($plugzip, base64_decode($NUTS_PAYLOAD));
$dir = $wproot.'/wp-content/plugins';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$zip = new ZipArchive();
if ($zip->open($plugzip) === true) { $zip->extractTo($dir); $zip->close(); }
unlink($plugzip);
if (!file_exists($dir.'/nuts-admin/nuts-admin.php')) $fail('Не удалось распаковать плагин NUTS ПУС.');
require_once ABSPATH.'wp-admin/includes/plugin.php';
activate_plugins(['nuts-admin/nuts-admin.php']);
if (!is_plugin_active('nuts-admin/nuts-admin.php')) $fail('Плагин распакован, но не активировался. Активируйте «NUTS ПУС» в разделе «Плагины».');
nuts_log('  NUTS ПУС активирован.');

@unlink($wpzip);
$selfdeleted = @unlink(__FILE__);
echo '</div>';
$home = home_url('/');
$cab = home_url('/cabinet/');
echo '<p class="ok"><strong>Готово!</strong> Сборка установлена.</p>';
echo '<p><a class="btn" href="'.htmlspecialchars($cab, ENT_QUOTES, 'UTF-8').'">Войти в панель</a><a class="ghost" href="'.htmlspecialchars($home, ENT_QUOTES, 'UTF-8').'">Открыть сайт</a></p>';
echo '<fieldset><legend>Данные администратора</legend><p style="font-size:13px;margin:0">Логин: <strong>'.htmlspecialchars($admin, ENT_QUOTES, 'UTF-8').'</strong><br>Пароль: <strong>'.htmlspecialchars($pass, ENT_QUOTES, 'UTF-8').'</strong><br>Вход: <strong>/cabinet/</strong></p></fieldset>';
if (!$selfdeleted) echo '<p class="err">Удалите файл '.htmlspecialchars(basename(__FILE__), ENT_QUOTES, 'UTF-8').' с сервера — установка завершена.</p>';
nuts_installer_foot($NUTS_VERSION, $NUTS_BUILD);
