<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
const REDIRECTS_LIMIT = 200;
function redirects_data() {
    $items = get_option('nuts_redirects', []);
    return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
}
function normalize_redirect_path($path) {
    $path = trim((string) $path);
    if ($path === '' || $path[0] !== '/') return null;
    if (strpos($path, '//') !== false || strpos($path, '..') !== false || strpos($path, "\0") !== false) return null;
    return strtolower(rtrim($path, '/')) ?: '/';
}
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1', '/redirects', [
        'methods' => ['GET', 'POST'],
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function ($request) {
            if ($request->get_method() === 'GET') return redirects_data();
            $items = $request->get_param('items');
            if (!is_array($items) || count($items) > REDIRECTS_LIMIT) {
                return new \WP_Error('nuts_invalid_redirects', 'Список перенаправлений должен быть массивом не более '.REDIRECTS_LIMIT.' записей.', ['status' => 400]);
            }
            $clean = [];
            foreach ($items as $item) {
                if (!is_array($item)) return new \WP_Error('nuts_invalid_redirects', 'Каждая запись перенаправления должна быть объектом.', ['status' => 400]);
                $from = normalize_redirect_path($item['from'] ?? '');
                $to = trim((string) ($item['to'] ?? ''));
                $code = (int) ($item['code'] ?? 301);
                if ($from === null) return new \WP_Error('nuts_invalid_redirect', 'Адрес «откуда» должен начинаться с / и не содержать .. или //.', ['status' => 400]);
                if ($to === '') return new \WP_Error('nuts_invalid_redirect', 'Адрес «куда» не может быть пустым.', ['status' => 400]);
                if (strpos($to, "\0") !== false || strpos($to, '..') !== false) return new \WP_Error('nuts_invalid_redirect', 'Адрес «куда» содержит недопустимые символы.', ['status' => 400]);
                if (!in_array($code, [301, 302, 307, 308], true)) $code = 301;
                if (isset($clean[$from])) return new \WP_Error('nuts_invalid_redirects', 'Адрес «'.$item['from'].'» повторяется.', ['status' => 400]);
                $clean[$from] = ['from' => $from, 'to' => $to, 'code' => $code];
            }
            update_option('nuts_redirects', array_values($clean), false);
            return redirects_data();
        },
    ]);
});
add_action('template_redirect', function () {
    if (is_admin() || is_trackback() || is_robots() || is_favicon()) return;
    $items = redirects_data();
    if (!$items) return;
    $request_path = strtolower(rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/')) ?: '/';
    foreach ($items as $item) {
        if ($item['from'] === $request_path && normalize_redirect_path($item['to']) !== $request_path) {
            $target = preg_match('/^https?:\/\//D', $item['to']) ? $item['to'] : home_url($item['to']);
            nocache_headers();
            wp_redirect(esc_url_raw($target), (int) $item['code']);
            exit;
        }
    }
}, 1);
