<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
// Service tools: export/import of NUTS settings, media housekeeping (approximate unused list and file replace).
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1', '/export', [
        'methods' => 'GET',
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function () {
            return [
                'format' => 'nuts-settings', 'version' => VERSION, 'exported' => gmdate('c'),
                'appearance' => admin_appearance(),
                'contacts' => contacts_data(),
                'maintenance' => maintenance_data(),
                'redirects' => redirects_data(),
                'seo_home' => seo_home_data(),
            ];
        },
    ]);
    register_rest_route('nuts/v1', '/import', [
        'methods' => 'POST',
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function ($request) {
            $data = $request->get_param('data');
            if (is_string($data)) $data = json_decode($data, true);
            if (!is_array($data) || ($data['format'] ?? '') !== 'nuts-settings') {
                return new \WP_Error('nuts_invalid_import', 'Файл не является экспортом настроек NUTS ПУС.', ['status' => 400]);
            }
            $applied = [];
            $appearance = $data['appearance'] ?? null;
            if (is_array($appearance)) {
                $presets = appearance_presets();
                $preset = $appearance['preset'] ?? '';
                if (isset($presets[$preset])) {
                    update_option('nuts_admin_appearance', array_merge(['preset' => $preset], $presets[$preset]), false);
                    $applied[] = 'гамма админки';
                } elseif ($preset === 'custom') {
                    $clean = ['preset' => 'custom'];
                    foreach (['accent', 'sidebar', 'background'] as $key) {
                        $color = sanitize_hex_color($appearance[$key] ?? '');
                        if (!$color) return new \WP_Error('nuts_invalid_import', 'Некорректный цвет в файле: '.$key.'.', ['status' => 400]);
                        $clean[$key] = $color;
                    }
                    update_option('nuts_admin_appearance', $clean, false);
                    $applied[] = 'гамма админки';
                }
            }
            $contacts = $data['contacts'] ?? null;
            if (is_array($contacts)) {
                $clean = contacts_data();
                foreach (array_keys($clean) as $key) if (isset($contacts[$key]) && is_string($contacts[$key])) $clean[$key] = sanitize_text_field($contacts[$key]);
                update_option('nuts_contacts', $clean, false);
                $applied[] = 'контакты';
            }
            $redirects = $data['redirects'] ?? null;
            if (is_array($redirects)) {
                $clean = [];
                foreach (array_slice($redirects, 0, REDIRECTS_LIMIT) as $item) {
                    if (!is_array($item)) continue;
                    $from = normalize_redirect_path($item['from'] ?? '');
                    $to = trim((string) ($item['to'] ?? ''));
                    if ($from === null || $to === '') continue;
                    $clean[$from] = ['from' => $from, 'to' => $to, 'code' => in_array((int) ($item['code'] ?? 301), [301, 302, 307, 308], true) ? (int) $item['code'] : 301];
                }
                update_option('nuts_redirects', array_values($clean), false);
                $applied[] = 'перенаправления';
            }
            $seo = $data['seo_home'] ?? null;
            if (is_array($seo)) {
                update_option('nuts_seo_home', [
                    'title' => sanitize_text_field((string) ($seo['title'] ?? '')),
                    'description' => sanitize_textarea_field((string) ($seo['description'] ?? '')),
                ], false);
                $applied[] = 'SEO главной';
            }
            return ['ok' => true, 'applied' => $applied];
        },
    ]);
    register_rest_route('nuts/v1', '/media-unused', [
        'methods' => 'GET',
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function () {
            global $wpdb;
            // Approximation, clearly labelled: unattached images not used as icons, logos or thumbnails
            // and whose URL does not appear in any content. Confirm before deleting anything.
            $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_parent=0 AND post_mime_type LIKE 'image/%' ORDER BY ID DESC LIMIT 500");
            $used_ids = array_filter(array_unique(array_map('intval', array_merge(
                (array) get_option('site_icon', 0) ? [get_option('site_icon')] : [],
                (array) get_theme_mod('custom_logo', 0) ? [get_theme_mod('custom_logo')] : [],
                $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_thumbnail_id' LIMIT 1000"),
                $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_wp_attachment_is_custom_header' LIMIT 100"),
            ))));
            $used = array_fill_keys($used_ids, true);
            $content_haystack = strtolower((string) $wpdb->get_var("SELECT GROUP_CONCAT(post_content SEPARATOR ' ') FROM {$wpdb->posts} WHERE post_status IN ('publish','private','draft') LIMIT 2000"));
            $items = [];
            foreach ($ids as $id) {
                if (isset($used[$id])) continue;
                $url = wp_get_attachment_url((int) $id);
                if ($url && strpos($content_haystack, strtolower($url)) !== false) continue;
                $items[] = [
                    'id' => (int) $id,
                    'title' => get_the_title((int) $id),
                    'url' => (string) $url,
                    'date' => get_post_timestamp((int) $id),
                    'thumb' => (string) wp_get_attachment_image_url((int) $id, 'thumbnail'),
                ];
                if (count($items) >= 100) break;
            }
            return ['items' => $items, 'note' => 'Приблизительная проверка: изображения без родителя, не как значок/логотип/миниатюра и без упоминания URL в содержимом. Перед удалением проверьте вручную.'];
        },
    ]);
    register_rest_route('nuts/v1', '/media/(?P<id>\d+)/replace', [
        'methods' => 'POST',
        'permission_callback' => function ($request) { return current_user_can('upload_files') && current_user_can('edit_post', (int) $request['id']); },
        'callback' => function ($request) {
            $id = (int) $request['id'];
            if (get_post_type($id) !== 'attachment') return new \WP_Error('nuts_not_found', 'Файл не найден.', ['status' => 404]);
            $file = $request->get_file_params()['file'] ?? null;
            if (!$file || !empty($file['error'])) return new \WP_Error('nuts_invalid_file', 'Выберите файл для замены.', ['status' => 400]);
            $old_path = get_attached_file($id);
            if (!$old_path || !file_exists($old_path)) return new \WP_Error('nuts_invalid_file', 'Исходный файл недоступен.', ['status' => 500]);
            $old_mime = get_post_mime_type($id);
            $new_mime = wp_check_filetype($file['name'])['type'];
            if (!$new_mime || strtok($new_mime, '/') !== strtok((string) $old_mime, '/')) {
                return new \WP_Error('nuts_invalid_file', 'Тип нового файла должен совпадать с исходным (например, изображение).', ['status' => 400]);
            }
            $upload = wp_upload_bits(basename($old_path), null, (string) file_get_contents($file['tmp_name']));
            if (!empty($upload['error'])) return new \WP_Error('nuts_replace_failed', $upload['error'], ['status' => 500]);
            wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
            return ['ok' => true, 'url' => wp_get_attachment_url($id)];
        },
    ]);
});
