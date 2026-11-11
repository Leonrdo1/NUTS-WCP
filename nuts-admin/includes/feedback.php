<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
// A simple feedback form: a [nuts_form] shortcode, public REST submission, and an entry log in the panel.
add_action('init', function () {
    register_post_type('nuts_form_entry', [
        'labels' => ['name' => 'Заявки', 'singular_name' => 'Заявка'],
        'public' => false, 'show_ui' => false, 'show_in_rest' => false,
        'supports' => ['title', 'editor'],
        'capability_type' => 'post',
    ]);
});
function feedback_entries($unread_only = false) {
    $query = new \WP_Query([
        'post_type' => 'nuts_form_entry',
        'post_status' => 'private',
        'posts_per_page' => 100,
        'orderby' => 'date', 'order' => 'DESC',
        'meta_query' => $unread_only ? [['key' => '_nuts_read', 'value' => '1', 'compare' => '!=']] : [],
    ]);
    return array_map(function ($post) {
        return [
            'id' => $post->ID,
            'name' => $post->post_title,
            'message' => $post->post_content,
            'email' => (string) get_post_meta($post->ID, '_nuts_email', true),
            'time' => strtotime($post->post_date_gmt.' UTC'),
            'read' => (bool) get_post_meta($post->ID, '_nuts_read', true),
        ];
    }, $query->posts);
}
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1', '/feedback', [
        'methods' => 'GET',
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function () { return feedback_entries(); },
    ]);
    register_rest_route('nuts/v1', '/feedback/(?P<id>\d+)', [
        'methods' => 'POST',
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function ($request) {
            $id = (int) $request['id'];
            if (get_post_type($id) !== 'nuts_form_entry') return new \WP_Error('nuts_not_found', 'Заявка не найдена.', ['status' => 404]);
            $action = (string) $request->get_param('action');
            if ($action === 'delete') {
                wp_delete_post($id, true);
                return ['ok' => true];
            }
            if ($action === 'read') {
                update_post_meta($id, '_nuts_read', '1');
                return feedback_entries();
            }
            return new \WP_Error('nuts_invalid_action', 'Неизвестное действие.', ['status' => 400]);
        },
    ]);
    // Public submission: a logged-out visitor has no nonce, so we rate-limit by IP and use a honeypot.
    register_rest_route('nuts/v1', '/form', [
        'methods' => 'POST',
        'permission_callback' => '__return_true',
        'callback' => function ($request) {
            if (!empty($request->get_param('company'))) return ['ok' => true]; // Honeypot: silently accept bots.
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $key = 'nuts_form_'.md5($ip);
            $count = (int) get_transient($key);
            if ($count >= 5) return new \WP_Error('nuts_too_many', 'Слишком много отправок. Попробуйте позже.', ['status' => 429]);
            $name = sanitize_text_field((string) $request->get_param('name'));
            $email = sanitize_email((string) $request->get_param('email'));
            $message = sanitize_textarea_field((string) $request->get_param('message'));
            if ($name === '' || $message === '') return new \WP_Error('nuts_invalid_form', 'Заполните имя и сообщение.', ['status' => 400]);
            if ($email !== '' && !is_email($email)) return new \WP_Error('nuts_invalid_form', 'Адрес электронной почты указан некорректно.', ['status' => 400]);
            $id = wp_insert_post([
                'post_type' => 'nuts_form_entry',
                'post_status' => 'private',
                'post_title' => $name,
                'post_content' => $message,
            ], true);
            if (is_wp_error($id)) return new \WP_Error('nuts_form_failed', 'Не удалось сохранить заявку.', ['status' => 500]);
            update_post_meta($id, '_nuts_email', $email);
            set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
            $data = contacts_data();
            $to = !empty($data['email']) ? $data['email'] : get_option('admin_email');
            $body = "Имя: {$name}\nEmail: {$email}\n\n{$message}\n\n—\nСтраница: ".sanitize_text_field((string) $request->get_param('source'));
            wp_mail($to, 'Заявка с сайта «'.get_bloginfo('name').'»', $body, ['Reply-To: '.$email]);
            return ['ok' => true];
        },
    ]);
});
add_shortcode('nuts_form', function () {
    $id = wp_unique_id('nuts-form-');
    ob_start();
    echo '<form id="'.esc_attr($id).'" class="nuts-feedback-form" method="post" action="'.esc_url(rest_url('nuts/v1/form')).'">';
    echo '<p><label>Имя <input type="text" name="name" required maxlength="120"></label></p>';
    echo '<p><label>Электронная почта <input type="email" name="email" maxlength="120"></label></p>';
    echo '<p><label>Сообщение <textarea name="message" required rows="5" maxlength="5000"></textarea></label></p>';
    echo '<p style="position:absolute;left:-9999px" aria-hidden="true"><label>Company <input type="text" name="company" tabindex="-1" autocomplete="off"></label></p>';
    echo '<p><button type="submit">Отправить</button> <span role="status"></span></p></form>';
    echo '<script>(function(){var f=document.getElementById('.wp_json_encode($id).');if(!f)return;f.addEventListener("submit",function(e){e.preventDefault();var s=f.querySelector("[role=status]");s.textContent="Отправляем…";var fd=new FormData(f);fd.append("source",location.href);fetch(f.action,{method:"POST",body:fd}).then(function(r){if(!r.ok)throw 0;s.textContent="Спасибо! Сообщение отправлено.";f.reset();}).catch(function(){s.textContent="Не удалось отправить. Попробуйте ещё раз.";});});})();</script>';
    return ob_get_clean();
});
