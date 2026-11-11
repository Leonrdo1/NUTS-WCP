<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
// Local avatars: users pick an image from the media library; no Gravatar requests.
function user_avatar_data($user_id) {
    $id = (int) get_user_meta($user_id, 'nuts_avatar', true);
    if ($id && wp_attachment_is_image($id)) return ['id' => $id, 'url' => (string) wp_get_attachment_image_url($id, [256, 256])];
    return ['id' => 0, 'url' => ''];
}
// Serve the local avatar, and fall back to a generated letter avatar instead of Gravatar.
add_filter('get_avatar_data', function ($args, $id_or_email) {
    $user_id = 0;
    $email = '';
    if (is_numeric($id_or_email)) $user_id = (int) $id_or_email;
    elseif ($id_or_email instanceof \WP_User) $user_id = $id_or_email->ID;
    elseif ($id_or_email instanceof \WP_Post) $user_id = (int) $id_or_email->post_author;
    elseif ($id_or_email instanceof \WP_Comment) {
        if (!empty($id_or_email->user_id)) $user_id = (int) $id_or_email->user_id;
        $email = (string) $id_or_email->comment_author_email;
    } elseif (is_string($id_or_email)) {
        if (is_email($id_or_email)) {
            $email = $id_or_email;
            $user = get_user_by('email', $id_or_email);
            if ($user) $user_id = $user->ID;
        }
    }
    if ($user_id) {
        $avatar = user_avatar_data($user_id);
        if ($avatar['url']) {
            $args['url'] = $avatar['url'];
            $args['found_avatar'] = true;
            return $args;
        }
        $user = get_userdata($user_id);
        $name = $user ? $user->display_name : '';
    } else {
        $name = $email;
    }
    $letter = mb_strtoupper(mb_substr(trim($name) !== '' ? trim($name) : '·', 0, 1, 'UTF-8'), 'UTF-8');
    $args['url'] = home_url('/nuts-avatar/?l='.rawurlencode($letter).($user_id ? '' : '&guest=1'));
    $args['found_avatar'] = true;
    return $args;
}, 10, 2);
// Local letter-avatar renderer (a real URL: esc_url strips data: URIs from avatar markup).
add_action('init', function () {
    if (is_admin()) return;
    $home_path = rtrim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
    $path = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
    if ($home_path.'/nuts-avatar' !== $path) return;
    $letter = mb_substr(sanitize_text_field((string) ($_GET['l'] ?? '·')), 0, 1) ?: '·';
    $bg = isset($_GET['guest']) ? '#8a8f98' : (sanitize_hex_color(admin_appearance()['accent'] ?? '') ?: '#096fd2');
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96"><rect width="96" height="96" fill="'.$bg.'"/><text x="48" y="63" font-family="-apple-system,Segoe UI,sans-serif" font-size="44" font-weight="600" fill="#ffffff" text-anchor="middle">'.esc_html($letter).'</text></svg>';
    exit;
}, 1);
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1', '/avatar', [
        'methods' => ['GET', 'POST'],
        'permission_callback' => function () {
            $uid = get_current_user_id();
            return $uid > 0 && current_user_can('edit_user', $uid);
        },
        'callback' => function ($request) {
            $uid = get_current_user_id();
            if ($request->get_method() === 'POST') {
                $id = $request->get_param('id');
                if (!is_int($id) || $id < 0) return new \WP_Error('nuts_invalid_avatar', 'Неверный идентификатор изображения.', ['status' => 400]);
                if ($id) {
                    if (!wp_attachment_is_image($id) || !current_user_can('read_post', $id)) {
                        return new \WP_Error('nuts_invalid_avatar', 'Выберите доступное изображение из медиатеки.', ['status' => 400]);
                    }
                    $meta = wp_get_attachment_metadata($id);
                    if (empty($meta['width']) || empty($meta['height']) || min($meta['width'], $meta['height']) < 64) {
                        return new \WP_Error('nuts_small_avatar', 'Изображение должно быть не меньше 64 × 64 пикселей.', ['status' => 400]);
                    }
                    update_user_meta($uid, 'nuts_avatar', $id);
                } else {
                    delete_user_meta($uid, 'nuts_avatar');
                }
            }
            return user_avatar_data($uid);
        },
    ]);
});
// Avatar field on the classic profile screen (the Gravatar block itself is hidden below).
add_action('show_user_profile', 'NutsCMS\profile_avatar_field');
add_action('edit_user_profile', 'NutsCMS\profile_avatar_field');
function profile_avatar_field($user) {
    if (!current_user_can('edit_user', $user->ID)) return;
    $avatar = user_avatar_data($user->ID);
    echo '<h2>Аватар NUTS</h2><table class="form-table" role="presentation"><tr><th>Фотография</th><td>';
    echo '<img id="nuts-avatar-preview" src="'.esc_url($avatar['url']).'" width="64" height="64" style="border-radius:50%;object-fit:cover;background:#f0f0f0" alt="Текущий аватар">';
    echo '<p><button type="button" class="button" id="nuts-avatar-pick">Выбрать из медиатеки</button> ';
    if ($avatar['id']) echo '<button type="button" class="button" id="nuts-avatar-clear">Удалить аватар</button>';
    echo '</p><input type="hidden" name="nuts_avatar" id="nuts-avatar-id" value="'.(int) $avatar['id'].'">';
    echo '<p class="description">Аватар показывается в панели, комментариях и верхней панели сайта. Без фото используется значок с первой буквой имени — Gravatar не используется.</p></td></tr></table>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){var pick=document.getElementById("nuts-avatar-pick");if(!pick||!window.wp||!wp.media)return;var input=document.getElementById("nuts-avatar-id"),img=document.getElementById("nuts-avatar-preview");pick.addEventListener("click",function(){var frame=wp.media({title:"Ваш аватар",button:{text:"Выбрать фото"},library:{type:"image"},multiple:false});frame.on("select",function(){var m=frame.state().get("selection").first().toJSON();input.value=m.id;img.src=m.sizes&&m.sizes.thumbnail?m.sizes.thumbnail.url:m.url;});frame.open();});var clear=document.getElementById("nuts-avatar-clear");if(clear)clear.addEventListener("click",function(){input.value="0";img.src="";img.style.display="none";clear.remove();});});</script>';
}
add_action('personal_options_update', 'NutsCMS\profile_avatar_save');
add_action('edit_user_profile_update', 'NutsCMS\profile_avatar_save');
function profile_avatar_save($user_id) {
    if (!current_user_can('edit_user', $user_id) || !isset($_POST['nuts_avatar'])) return;
    check_admin_referer('update-user_'.$user_id);
    $id = (int) $_POST['nuts_avatar'];
    if ($id > 0 && wp_attachment_is_image($id)) update_user_meta($user_id, 'nuts_avatar', $id);
    else delete_user_meta($user_id, 'nuts_avatar');
}
add_action('admin_enqueue_scripts', function ($hook) {
    if (in_array($hook, ['profile.php', 'user-edit.php'], true)) wp_enqueue_media();
});
// Hide Gravatar UI: the profile block and the "Avatar display" section in Discussion settings.
add_action('admin_head', function () {
    if (!function_exists('get_current_screen')) return;
    $screen = get_current_screen();
    if (!$screen) return;
    if (in_array($screen->id, ['profile', 'user-edit'], true)) {
        echo '<style>.user-profile-picture{display:none!important}</style>';
    }
    if ($screen->id === 'options-discussion') {
        echo '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("h2").forEach(function(h){if(/аватар/i.test(h.textContent)){h.style.display="none";var n=h.nextElementSibling;while(n&&n.tagName!=="H2"){var nx=n.nextElementSibling;n.style.display="none";n=nx;}}});});</script>';
    }
});
