<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
function site_settings_data() {
    return [
        'title' => get_bloginfo('name'),
        'description' => get_bloginfo('description'),
        'admin_email' => get_option('admin_email'),
        'timezone' => wp_timezone_string(),
        'language' => get_locale(),
        'languages' => array_values(array_unique(array_merge([get_locale()], get_available_languages()))),
        'timezones' => timezone_identifiers_list(),
        'discussions' => [
            'default_comment_status' => (bool) get_option('default_comment_status'),
            'comment_moderation' => (bool) get_option('comment_moderation'),
            'comment_registration' => (bool) get_option('comment_registration'),
            'close_comments_for_old_posts' => (bool) get_option('close_comments_for_old_posts'),
        ],
        'front' => [
            'show_on_front' => get_option('show_on_front'),
            'page_on_front' => (int) get_option('page_on_front'),
            'page_for_posts' => (int) get_option('page_for_posts'),
        ],
    ];
}
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1', '/site-settings', [
        'methods' => ['GET', 'POST'],
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function ($request) {
            if ($request->get_method() === 'GET') return site_settings_data();
            $title = $request->get_param('title');
            $description = $request->get_param('description');
            $email = $request->get_param('admin_email');
            $timezone = $request->get_param('timezone');
            $language = $request->get_param('language');
            if (!is_string($title) || trim($title) === '') return new \WP_Error('nuts_invalid_title', 'Название сайта не может быть пустым.', ['status' => 400]);
            if (!is_string($email) || !is_email($email)) return new \WP_Error('nuts_invalid_email', 'Укажите корректный адрес электронной почты.', ['status' => 400]);
            if (!is_string($timezone) || !valid_timezone($timezone)) return new \WP_Error('nuts_invalid_timezone', 'Выберите часовой пояс из списка.', ['status' => 400]);
            $languages = array_values(array_unique(array_merge([get_locale()], get_available_languages())));
            if (!is_string($language) || !in_array($language, $languages, true)) return new \WP_Error('nuts_invalid_language', 'Выберите язык из списка установленных.', ['status' => 400]);
            update_option('blogname', sanitize_text_field($title));
            update_option('blogdescription', sanitize_text_field((string) $description));
            update_option('admin_email', sanitize_email($email));
            update_option('timezone_string', $timezone);
            if (!is_multisite()) update_option('locale', $language);
            $discussions = $request->get_param('discussions');
            if (is_array($discussions)) {
                foreach (['default_comment_status', 'comment_moderation', 'comment_registration', 'close_comments_for_old_posts'] as $key) {
                    if (array_key_exists($key, $discussions)) update_option($key, !empty($discussions[$key]) ? '1' : '');
                }
            }
            $front = $request->get_param('front');
            if (is_array($front)) {
                $show = $front['show_on_front'] ?? null;
                if (in_array($show, ['posts', 'page'], true)) update_option('show_on_front', $show);
                foreach (['page_on_front', 'page_for_posts'] as $key) {
                    if (!array_key_exists($key, $front)) continue;
                    $id = (int) $front[$key];
                    if ($id > 0 && get_post_type($id) !== 'page') return new \WP_Error('nuts_invalid_page', 'Выбранная страница не существует.', ['status' => 400]);
                    update_option($key, $id);
                }
            }
            return site_settings_data();
        },
    ]);
});
function valid_timezone($timezone) {
    if (in_array($timezone, timezone_identifiers_list(), true)) return true;
    return (bool) preg_match('/^UTC([+-])(0?[0-9]|1[0-4])(:[0-5]?[0-9])?$/D', $timezone);
}
