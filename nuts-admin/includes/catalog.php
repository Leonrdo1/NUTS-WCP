<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
// Catalog core: services, offer builder, and user-defined sections (infoblocks).
function catalog_custom_types() {
    $types = get_option('nuts_catalog_types', []);
    return is_array($types) ? array_values(array_filter($types, 'is_array')) : [];
}
function catalog_reserved_slugs() {
    return ['post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'product', 'product_variation', 'shop_order', 'shop_coupon', 'nuts_product', 'nuts_service', 'nuts_offer', 'nuts_form_entry'];
}
add_action('init', function () {
    register_post_type('nuts_service', [
        'labels' => ['name' => 'Услуги', 'singular_name' => 'Услуга', 'add_new' => 'Добавить услугу', 'add_new_item' => 'Добавить услугу', 'edit_item' => 'Редактировать услугу', 'all_items' => 'Все услуги', 'not_found' => 'Услуги не найдены'],
        'public' => true, 'show_in_rest' => true, 'menu_icon' => 'dashicons-admin-tools',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'],
        'has_archive' => false,
    ]);
    register_taxonomy('nuts_service_category', 'nuts_service', [
        'labels' => ['name' => 'Категории услуг', 'singular_name' => 'Категория услуги'],
        'hierarchical' => true, 'show_in_rest' => true,
    ]);
    register_post_type('nuts_offer', [
        'labels' => ['name' => 'Предложения', 'singular_name' => 'Предложение', 'add_new' => 'Добавить предложение', 'add_new_item' => 'Добавить предложение', 'edit_item' => 'Редактировать предложение', 'all_items' => 'Все предложения', 'not_found' => 'Предложения не найдены'],
        'public' => true, 'show_in_rest' => true, 'menu_icon' => 'dashicons-money-alt',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        'has_archive' => false,
    ]);
    register_post_meta('nuts_offer', '_nuts_offer_items', [
        'type' => 'array', 'single' => true, 'default' => [],
        'show_in_rest' => [
            'schema' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['t' => ['type' => 'string'], 'id' => ['type' => 'integer']], 'required' => ['t', 'id']]],
        ],
        'sanitize_callback' => function ($value) {
            $clean = [];
            foreach ((array) $value as $item) {
                if (!is_array($item)) continue;
                $t = $item['t'] ?? '';
                $id = (int) ($item['id'] ?? 0);
                if (in_array($t, ['product', 'service'], true) && $id > 0) $clean[] = ['t' => $t, 'id' => $id];
            }
            return array_slice($clean, 0, 100);
        },
        'auth_callback' => function ($allowed, $meta_key, $post_id) { return current_user_can('edit_post', $post_id); },
    ]);
    foreach (catalog_custom_types() as $type) {
        $slug = $type['slug'] ?? '';
        $name = $type['name'] ?? $slug;
        $singular = $type['singular'] ?? $name;
        if ($slug === '' || post_type_exists($slug)) continue;
        register_post_type($slug, [
            'labels' => ['name' => $name, 'singular_name' => $singular, 'add_new' => 'Добавить', 'add_new_item' => 'Добавить', 'edit_item' => 'Редактировать', 'all_items' => 'Все записи', 'not_found' => 'Записи не найдены'],
            'public' => true, 'show_in_rest' => true, 'menu_icon' => 'dashicons-category',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'],
            'has_archive' => false,
            'rewrite' => ['slug' => $slug],
        ]);
        register_taxonomy($slug.'_category', $slug, [
            'labels' => ['name' => 'Категории', 'singular_name' => 'Категория'],
            'hierarchical' => true, 'show_in_rest' => true,
        ]);
    }
}, 20);
function catalog_product_type() {
    return post_type_exists('product') ? 'product' : 'nuts_product';
}
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1', '/catalog-types', [
        'methods' => ['GET', 'POST'],
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function ($request) {
            if ($request->get_method() === 'GET') {
                return ['types' => catalog_custom_types(), 'reserved' => catalog_reserved_slugs()];
            }
            $action = (string) $request->get_param('action');
            if ($action === 'delete') {
                $slug = sanitize_key((string) $request->get_param('slug'));
                $types = array_values(array_filter(catalog_custom_types(), fn($t) => ($t['slug'] ?? '') !== $slug));
                update_option('nuts_catalog_types', $types, false);
                return ['types' => $types, 'deleted' => $slug];
            }
            $name = trim(sanitize_text_field((string) $request->get_param('name')));
            $singular = trim(sanitize_text_field((string) ($request->get_param('singular') ?: $name)));
            $slug = strtolower(sanitize_key((string) ($request->get_param('slug') ?: '')));
            if ($name === '' || mb_strlen($name) > 60) return new \WP_Error('nuts_invalid_type', 'Укажите название раздела (до 60 символов).', ['status' => 400]);
            if ($slug === '' || strlen($slug) < 2 || strlen($slug) > 30) return new \WP_Error('nuts_invalid_type', 'Системное имя: латиница, цифры, дефис — от 2 до 30 символов.', ['status' => 400]);
            if (in_array($slug, catalog_reserved_slugs(), true) || post_type_exists($slug) || taxonomy_exists($slug)) {
                return new \WP_Error('nuts_invalid_type', 'Такое системное имя уже занято. Выберите другое.', ['status' => 400]);
            }
            $types = catalog_custom_types();
            $types[] = ['slug' => $slug, 'name' => $name, 'singular' => $singular !== '' ? $singular : $name];
            update_option('nuts_catalog_types', $types, false);
            flush_rewrite_rules(false);
            return ['types' => array_values($types)];
        },
    ]);
});
// Products and services in one short list for the offer builder.
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1', '/catalog-items', [
        'methods' => 'GET',
        'permission_callback' => function () { return current_user_can('edit_posts'); },
        'callback' => function () {
            $out = [];
            $product_type = catalog_product_type();
            foreach ([['product', $product_type, 'Товары'], ['service', 'nuts_service', 'Услуги']] as [$key, $type, $label]) {
                if (!post_type_exists($type)) continue;
                $query = new \WP_Query(['post_type' => $type, 'post_status' => ['publish', 'draft', 'private'], 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC']);
                $items = [];
                foreach ($query->posts as $post) {
                    $items[] = ['id' => $post->ID, 'title' => $post->post_title ?: 'Без названия', 'status' => $post->post_status];
                }
                $out[] = ['kind' => $key, 'label' => $label, 'items' => $items];
            }
            return $out;
        },
    ]);
});
