<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
// GitHub Releases based self-update: the plugin injects itself into WP's native
// plugin update flow; one click (or fully automatic) upgrades from a release zip.
function update_repo() {
    $repo = get_option('nuts_update_repo', '');
    return is_string($repo) && preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/D', $repo) ? $repo : '';
}
function update_auto() {
    return (bool) get_option('nuts_update_auto', false);
}
function fetch_release($force = false) {
    $repo = update_repo();
    if ($repo === '') return null;
    $cached = get_site_transient('nuts_release_cache');
    if (!$force && is_array($cached)) return $cached;
    $response = wp_remote_get('https://api.github.com/repos/'.$repo.'/releases', [
        'timeout' => 10,
        'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'NUTS-PUS-updater/'.VERSION],
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        $release = ['error' => true];
        set_site_transient('nuts_release_cache', $release, HOUR_IN_SECONDS);
        return $release;
    }
    $list = json_decode((string) wp_remote_retrieve_body($response), true);
    $data = null;
    if (is_array($list)) {
        foreach ($list as $entry) {
            if (!empty($entry['draft'])) continue; // published releases only; pre-releases count too
            $data = $entry;
            break;
        }
    }
    if (!is_array($data)) {
        $release = ['error' => true];
        set_site_transient('nuts_release_cache', $release, HOUR_IN_SECONDS);
        return $release;
    }
    $version = ltrim((string) ($data['tag_name'] ?? ''), 'vV');
    $package = '';
    if (is_array($data['assets'] ?? null)) {
        foreach ($data['assets'] as $asset) {
            $name = (string) ($asset['name'] ?? '');
            if (strtolower(substr($name, -4)) !== '.zip') continue;
            $package = (string) ($asset['browser_download_url'] ?? '');
            if (stripos($name, 'nuts') !== false) break;
        }
    }
    if ($version === '' || $package === '') {
        $release = ['error' => true];
    } else {
        $release = [
            'version' => $version,
            'package' => $package,
            'url' => (string) ($data['html_url'] ?? 'https://github.com/'.$repo),
            'name' => (string) ($data['name'] ?? $version),
        ];
    }
    $release = apply_filters('nuts_release_data', $release);
    set_site_transient('nuts_release_cache', $release, 6 * HOUR_IN_SECONDS);
    return $release;
}
add_filter('site_transient_update_plugins', function ($transient) {
    if (!is_object($transient)) $transient = new \stdClass();
    $file = 'nuts-admin/nuts-admin.php';
    $release = fetch_release();
    if ($release && empty($release['error'])) {
        if (version_compare($release['version'], VERSION, '>')) {
            $transient->response[$file] = (object) [
                'id' => 'github.com/'.update_repo(),
                'slug' => 'nuts-admin',
                'plugin' => $file,
                'new_version' => $release['version'],
                'version' => $release['version'],
                'url' => $release['url'],
                'package' => $release['package'],
                'icons' => [],
                'banners' => [],
                'requires' => false,
                'tested' => false,
                'requires_php' => false,
            ];
            unset($transient->no_update[$file]);
        } else {
            unset($transient->response[$file]);
            $transient->no_update[$file] = (object) [
                'id' => 'github.com/'.update_repo(),
                'slug' => 'nuts-admin',
                'plugin' => $file,
                'new_version' => VERSION,
                'url' => $release['url'],
                'package' => $release['package'],
                'icons' => [], 'banners' => [], 'requires' => false, 'tested' => false, 'requires_php' => false,
            ];
        }
        $transient->checked[$file] = VERSION;
    } else {
        unset($transient->response[$file]);
    }
    return $transient;
});
add_filter('auto_update_plugin', function ($update, $item) {
    if (($item->plugin ?? '') === 'nuts-admin/nuts-admin.php' && update_auto()) return true;
    return $update;
}, 10, 2);
add_action('upgrader_process_complete', function ($upgrader, $hook_extra) {
    if (($hook_extra['type'] ?? '') === 'plugin' && in_array('nuts-admin/nuts-admin.php', (array) ($hook_extra['plugins'] ?? $hook_extra['plugin'] ?? []), true)) {
        delete_site_transient('nuts_release_cache');
    }
}, 10, 2);
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1', '/update', [
        'methods' => ['GET', 'POST'],
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function ($request) {
            if ($request->get_method() === 'POST') {
                $repo = trim((string) $request->get_param('repo'));
                if ($repo !== '' && !preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/D', $repo)) {
                    return new \WP_Error('nuts_invalid_repo', 'Укажите репозиторий в формате владелец/имя, например nuts-studio/nuts-pus.', ['status' => 400]);
                }
                update_option('nuts_update_repo', $repo, false);
                update_option('nuts_update_auto', !empty($request->get_param('auto')) ? '1' : '', false);
                delete_site_transient('nuts_release_cache');
            }
            $release = fetch_release($request->get_method() === 'POST');
            $update_available = is_array($release) && empty($release['error']) && version_compare($release['version'], VERSION, '>');
            return [
                'repo' => update_repo(),
                'auto' => update_auto(),
                'current' => VERSION,
                'latest' => is_array($release) && empty($release['error']) ? $release['version'] : null,
                'release_url' => is_array($release) && empty($release['error']) ? $release['url'] : '',
                'error' => is_array($release) && !empty($release['error']),
                'update_available' => $update_available,
                'update_url' => $update_available ? wp_nonce_url(admin_url('update.php?action=upgrade-plugin&plugin='.urlencode('nuts-admin/nuts-admin.php')), 'upgrade-plugin_nuts-admin/nuts-admin.php') : '',
            ];
        },
    ]);
});
