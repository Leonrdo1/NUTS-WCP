<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
// SEO basics: meta title/description for the front page and for each page, without a heavyweight SEO plugin.
function seo_post_types() {
    return array_values(array_filter(['page', 'post', post_type_exists('product') ? 'product' : 'nuts_product'], 'post_type_exists'));
}
function seo_home_data() {
    $saved = get_option('nuts_seo_home', []);
    if (!is_array($saved)) $saved = [];
    return [
        'title' => is_string($saved['title'] ?? null) ? $saved['title'] : '',
        'description' => is_string($saved['description'] ?? null) ? $saved['description'] : '',
    ];
}
add_action('init', function () {
    foreach (seo_post_types() as $type) {
        register_post_meta($type, '_nuts_meta_title', [
            'type' => 'string', 'single' => true, 'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => [
                'schema' => ['type' => 'string'],
                'prepare_callback' => function ($value) { return is_string($value) ? $value : ''; },
            ],
            'auth_callback' => function ($allowed, $meta_key, $post_id) { return current_user_can('edit_post', $post_id); },
        ]);
        register_post_meta($type, '_nuts_meta_description', [
            'type' => 'string', 'single' => true, 'default' => '',
            'sanitize_callback' => 'sanitize_textarea_field',
            'show_in_rest' => [
                'schema' => ['type' => 'string'],
                'prepare_callback' => function ($value) { return is_string($value) ? $value : ''; },
            ],
            'auth_callback' => function ($allowed, $meta_key, $post_id) { return current_user_can('edit_post', $post_id); },
        ]);
    }
}, 20);
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1', '/seo-home', [
        'methods' => ['GET', 'POST'],
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function ($request) {
            if ($request->get_method() === 'GET') return seo_home_data();
            update_option('nuts_seo_home', [
                'title' => sanitize_text_field((string) $request->get_param('title')),
                'description' => sanitize_textarea_field((string) $request->get_param('description')),
            ], false);
            return seo_home_data();
        },
    ]);
});
add_filter('pre_get_document_title', function ($title) {
    if (is_front_page() && !is_home()) {
        $home = seo_home_data();
        if ($home['title'] !== '') return $home['title'];
    }
    if (is_singular()) {
        $meta = get_post_meta(get_queried_object_id(), '_nuts_meta_title', true);
        if (is_string($meta) && $meta !== '') return $meta;
    }
    return $title;
}, 20);
add_action('wp_head', function () {
    $description = '';
    if (is_front_page() && !is_home()) $description = seo_home_data()['description'];
    elseif (is_singular()) $description = (string) get_post_meta(get_queried_object_id(), '_nuts_meta_description', true);
    $description = trim((string) $description);
    if ($description !== '') echo '<meta name="description" content="'.esc_attr(wp_strip_all_tags($description)).'">'."\n";
}, 1);
