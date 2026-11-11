<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
function maintenance_data() {
    $saved = get_option('nuts_maintenance', []);
    if (!is_array($saved)) $saved = [];
    return [
        'enabled' => !empty($saved['enabled']),
        'title' => is_string($saved['title'] ?? null) && $saved['title'] !== '' ? $saved['title'] : 'Сайт на обслуживании',
        'message' => is_string($saved['message'] ?? null) && $saved['message'] !== '' ? $saved['message'] : 'Мы обновляем сайт и скоро вернёмся. Попробуйте зайти чуть позже.',
    ];
}
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1', '/maintenance', [
        'methods' => ['GET', 'POST'],
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function ($request) {
            if ($request->get_method() === 'GET') return maintenance_data();
            $enabled = (bool) $request->get_param('enabled');
            $title = sanitize_text_field((string) $request->get_param('title'));
            $message = sanitize_textarea_field((string) $request->get_param('message'));
            update_option('nuts_maintenance', [
                'enabled' => $enabled,
                'title' => $title !== '' ? $title : 'Сайт на обслуживании',
                'message' => $message !== '' ? $message : 'Мы обновляем сайт и скоро вернёмся. Попробуйте зайти чуть позже.',
            ], false);
            return maintenance_data();
        },
    ]);
});
// Guests and unauthorized users get the maintenance screen; REST, feeds for editors and logins keep working.
add_action('template_redirect', function () {
    $data = maintenance_data();
    if (!$data['enabled'] || current_user_can('edit_pages')) return;
    status_header(503);
    nocache_headers();
    header('Retry-After: 300');
    $title = esc_html($data['title']);
    $message = esc_html($data['message']);
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.$title.'</title>';
    echo '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font:16px/1.6 system-ui,sans-serif;background:#f1f2f4;color:#202020}main{max-width:420px;padding:32px;text-align:center}h1{font-size:22px;margin:0 0 12px}p{margin:0;color:#555}</style>';
    echo '</head><body><main><h1>'.$title.'</h1><p>'.$message.'</p></main></body></html>';
    exit;
}, 0);
