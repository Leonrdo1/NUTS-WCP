<?php
/**
 * Plugin Name: NUTS WCP
 * Description: NUTS WCP — панель управления сайтом на штатном ядре.
 * Version: 1.8.0
 * Requires at least: 6.6
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */
namespace NutsCMS;
defined('ABSPATH') || exit;
const VERSION = '1.8.0';
require_once __DIR__.'/includes/appearance.php';
require_once __DIR__.'/includes/status.php';
require_once __DIR__.'/includes/community.php';
require_once __DIR__.'/includes/navigation.php';
require_once __DIR__.'/includes/site-identity.php';
require_once __DIR__.'/includes/site-settings.php';
require_once __DIR__.'/includes/contacts.php';
require_once __DIR__.'/includes/maintenance.php';
require_once __DIR__.'/includes/redirects.php';
require_once __DIR__.'/includes/seo.php';
require_once __DIR__.'/includes/feedback.php';
require_once __DIR__.'/includes/tools.php';
require_once __DIR__.'/includes/login.php';
require_once __DIR__.'/includes/avatars.php';
require_once __DIR__.'/includes/installer.php';
require_once __DIR__.'/includes/updater.php';
require_once __DIR__.'/includes/catalog.php';

add_action('admin_menu', function () {
    add_menu_page('NUTS ПУС', 'NUTS ПУС', 'edit_pages', 'nuts-cms', function () {
        echo '<div id="nuts-cms-root"></div>';
    }, 'dashicons-layout', 2);
});
add_action('admin_enqueue_scripts', function ($hook) {
    wp_enqueue_style('nuts-embed', plugins_url('assets/embed.css', __FILE__), [], filemtime(__DIR__.'/assets/embed.css'));
    wp_enqueue_style('nuts-toolbar', plugins_url('assets/toolbar.css', __FILE__), [], filemtime(__DIR__.'/assets/toolbar.css'));
    wp_enqueue_style('nuts-status', plugins_url('assets/status.css', __FILE__), [], filemtime(__DIR__.'/assets/status.css'));
    wp_enqueue_style('nuts-palette', plugins_url('assets/palette.css', __FILE__), ['nuts-embed'], filemtime(__DIR__.'/assets/palette.css'));
    wp_add_inline_style('nuts-palette', ':root{'.appearance_css(admin_appearance()).'}');
    wp_enqueue_script('nuts-community', plugins_url('assets/community.js', __FILE__), ['wp-element'], filemtime(__DIR__.'/assets/community.js'), true);
    wp_localize_script('nuts-community','nutsCommunityConfig',['root'=>rest_url('nuts/v1/'),'nonce'=>wp_create_nonce('wp_rest')]);
    if ($hook !== 'toplevel_page_nuts-cms') return;
    wp_enqueue_media();
    wp_enqueue_script('nuts-identity', plugins_url('assets/site-identity.js', __FILE__), ['wp-element','wp-api-fetch','nuts-workspace'], filemtime(__DIR__.'/assets/site-identity.js'), true);
    wp_enqueue_style('nuts-admin', plugins_url('assets/admin.css', __FILE__), [], filemtime(__DIR__.'/assets/admin.css'));
    wp_enqueue_script('nuts-workspace', plugins_url('assets/workspace.js', __FILE__), ['wp-element'], filemtime(__DIR__.'/assets/workspace.js'), true);
    wp_enqueue_script('nuts-design', plugins_url('assets/design-system.js', __FILE__), ['wp-element', 'wp-api-fetch', 'nuts-workspace', 'nuts-identity'], filemtime(__DIR__.'/assets/design-system.js'), true);
    wp_enqueue_script('nuts-status', plugins_url('assets/status.js', __FILE__), ['wp-element', 'wp-api-fetch', 'nuts-workspace'], filemtime(__DIR__.'/assets/status.js'), true);
    wp_enqueue_script('nuts-sections', plugins_url('assets/sections.js', __FILE__), ['wp-element', 'nuts-workspace', 'nuts-design', 'nuts-status', 'nuts-community'], filemtime(__DIR__.'/assets/sections.js'), true);
    wp_enqueue_script('nuts-site-settings', plugins_url('assets/site-settings.js', __FILE__), ['wp-element', 'wp-api-fetch', 'nuts-workspace'], filemtime(__DIR__.'/assets/site-settings.js'), true);
    wp_enqueue_script('nuts-tools', plugins_url('assets/tools.js', __FILE__), ['wp-element', 'wp-api-fetch', 'nuts-workspace'], filemtime(__DIR__.'/assets/tools.js'), true);
    wp_enqueue_script('nuts-catalog', plugins_url('assets/catalog.js', __FILE__), ['wp-element', 'wp-api-fetch', 'nuts-workspace'], filemtime(__DIR__.'/assets/catalog.js'), true);
    wp_enqueue_script('nuts-dashboard', plugins_url('assets/dashboard.js', __FILE__), ['wp-element', 'wp-api-fetch', 'nuts-workspace', 'nuts-community', 'nuts-identity'], filemtime(__DIR__.'/assets/dashboard.js'), true);
    wp_enqueue_script('nuts-admin', plugins_url('assets/admin.js', __FILE__), ['wp-element', 'wp-api-fetch', 'nuts-workspace', 'nuts-sections'], filemtime(__DIR__.'/assets/admin.js'), true);
    wp_localize_script('nuts-workspace', 'nutsConfig', [
        'appearance' => admin_appearance(),
        'appearancePresets' => appearance_presets(),
        'nonce' => wp_create_nonce('wp_rest'),
        'siteTitle' => get_bloginfo('name'),
        'siteDescription' => get_bloginfo('description'),
        'siteIcon' => site_icon_data(),
        'siteIconFallback' => default_site_icon_url(32),
        'home' => home_url('/'),
        'admin' => admin_url(),
        'nutsRest' => rest_url('nuts/v1/'),
        'cmsVersion' => VERSION,
        'versionLabel' => get_bloginfo('version').' / N '.VERSION,
        'version' => get_bloginfo('version'),
        'userId' => get_current_user_id(),
        'user' => wp_get_current_user()->display_name,
        'capabilities' => [
            'content' => current_user_can('edit_posts'), 'design' => current_user_can('edit_theme_options'),
            'users' => current_user_can('list_users'), 'plugins' => current_user_can('activate_plugins'),
            'settings' => current_user_can('manage_options'),
        ],
        'catalogType' => post_type_exists('product') ? 'product' : 'nuts_product',
        'customTypes' => catalog_custom_types(),
        'offerItemsType' => 'nuts_offer',
        'blockTheme' => wp_is_block_theme(),
        'designSettings' => current_user_can('edit_theme_options') ? wp_get_global_settings() : [],
        'canManage' => current_user_can('manage_options'),
        'coreAutoUpdate' => defined('WP_AUTO_UPDATE_CORE') ? WP_AUTO_UPDATE_CORE : null,
        'onboarding' => [
            'title' => trim(get_bloginfo('name')) !== '' && get_bloginfo('name') !== 'Ещё один сайт на WordPress',
            'description' => trim(get_bloginfo('description')) !== '',
            'icon' => (bool) get_option('site_icon'),
            'front' => get_option('show_on_front') === 'page' && (int) get_option('page_on_front') > 0,
            'contacts' => function_exists('NutsCMS\\contacts_filled') ? contacts_filled() : false,
            'appearance' => (admin_appearance()['preset'] ?? 'nuts') !== 'nuts',
        ],
    ]);
});

// Only the iframe owned by our shell gets the embedded presentation.
// Direct WordPress pages retain their normal navigation and behavior.
add_action('admin_head', function () {
    ?>
    <script>
    try {
        if (window.frameElement && window.frameElement.id === 'nuts-wp-frame' && window.parent.location.origin === window.location.origin) {
            document.documentElement.classList.add('nuts-embedded');
        }
    } catch (e) {}
    </script>
    <?php
}, 0);

// A small catalog is available without requiring a shop plugin.
// If a shop already registers products, its product type takes precedence.
add_action('init', function () {
    if (post_type_exists('product')) return;
    register_post_type('nuts_product', [
        'labels' => [ 'name' => 'Каталог товаров', 'singular_name' => 'Товар', 'add_new' => 'Добавить товар', 'add_new_item' => 'Добавить товар', 'edit_item' => 'Редактировать товар', 'all_items' => 'Все товары', 'not_found' => 'Товары не найдены' ],
        'public' => true, 'show_in_rest' => true, 'menu_icon' => 'dashicons-products',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
        'rewrite' => false,
    ]);
    register_taxonomy('nuts_product_category', 'nuts_product', [
        'labels' => ['name' => 'Категории товаров', 'singular_name' => 'Категория товара'],
        'hierarchical' => true, 'show_in_rest' => true, 'rewrite' => false,
    ]);
}, 30);

// Keep native toolbar destinations, keyboard behavior and submenu permissions.
add_action('admin_bar_menu', function ($bar) {
    if (!current_user_can('edit_pages')) return;
    $node=$bar->get_node('my-account');
    if (!$node) return;
    $node->title='<span class="display-name">'.esc_html(wp_get_current_user()->display_name).'</span><span class="nuts-toolbar-user-icon" aria-hidden="true"></span>';
    $bar->add_node((array)$node);
}, 10001);

// Brand display text only: engine files, URLs and update identifiers stay native.
// Latin brand is NUTS WCP; Russian screens render it as NUTS ПУС.
add_filter('gettext', function ($translated) {
    if (!is_admin()) return $translated;
    $translated = str_replace('WordPress', 'NUTS ПУС', $translated);
    if (strpos(get_locale(), 'ru') === 0) $translated = str_replace('NUTS WCP', 'NUTS ПУС', $translated);
    return $translated;
}, 20);
add_filter('admin_footer_text', function () { return 'NUTS ПУС — Панель управления сайтом'; });
add_filter('update_footer', function () { return get_bloginfo('version').' / N '.VERSION; }, 99);
add_filter('admin_title', function ($title) { return str_replace('WordPress','NUTS ПУС',$title); });
add_action('admin_bar_menu', function ($bar) {
    if (!current_user_can('edit_pages')) return;
    $node=$bar->get_node('wp-logo');
    if($node){$node->meta['title']='NUTS ПУС — Панель управления сайтом';$node->title='<span class="ab-icon" aria-hidden="true"></span><span class="screen-reader-text">NUTS ПУС — Панель управления сайтом</span>';$bar->add_node((array)$node);}
},10002);

register_activation_hook(__FILE__, function () {
    // Only initialize a new installation; never overwrite an existing palette.
    add_option('nuts_admin_appearance', ['preset'=>'custom','accent'=>'#096fd2','sidebar'=>'#333333','background'=>'#f1f2f4'], '', false);
});
