<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
// Builds the single-file installer: packs the current plugin into the template.
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1', '/installer', [
        'methods' => 'GET',
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function () {
            $template = file_get_contents(__DIR__.'/installer-template.php');
            if ($template === false) return new \WP_Error('nuts_no_template', 'Шаблон установщика не найден.', ['status' => 500]);
            // The anti-direct-access guard belongs to the raw template only — strip it from the build.
            $template = preg_replace('/^if \(strpos\(file_get_contents\(__FILE__\).*?\}\s*$/m', '', $template, 1);
            if ($template === null || strpos($template, 'file_get_contents(__FILE__)') !== false) {
                return new \WP_Error('nuts_template_guard', 'Не удалось подготовить шаблон установщика.', ['status' => 500]);
            }
            $tmp = tempnam(sys_get_temp_dir(), 'nuts');
            $zip = new \ZipArchive();
            if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) return new \WP_Error('nuts_zip_failed', 'Не удалось собрать архив плагина.', ['status' => 500]);
            $base = realpath(__DIR__.'/..');
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            $count = 0;
            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;
                $relative = 'nuts-admin/'.str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                $zip->addFile($file->getPathname(), $relative);
                $count++;
            }
            $zip->close();
            $payload = base64_encode((string) file_get_contents($tmp));
            unlink($tmp);
            $accent = sanitize_hex_color(admin_appearance()['accent'] ?? '') ?: '#096fd2';
            $code = strtr($template, [
                '__NUTS_PAYLOAD__' => $payload,
                '__NUTS_VERSION__' => VERSION,
                '__NUTS_BUILD__' => gmdate('d.m.Y H:i'),
                '__NUTS_ACCENT__' => $accent,
            ]);
            foreach (['__NUTS_PAYLOAD__', '__NUTS_VERSION__', '__NUTS_BUILD__', '__NUTS_ACCENT__'] as $marker) {
                if (strpos($code, $marker) !== false) return new \WP_Error('nuts_template_incomplete', 'Шаблон установщика содержит неподставленные маркеры.', ['status' => 500]);
            }
            header('Content-Type: application/octet-stream; charset=utf-8');
            header('Content-Disposition: attachment; filename="nuts-installer-N'.VERSION.'.php"');
            header('Content-Length: '.strlen($code));
            echo $code;
            exit;
        },
    ]);
});
