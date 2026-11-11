<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
// Central site contact details: a single place for the phone in the header, the email in the footer, etc.
function contacts_fields() {
    return [
        'phone' => 'Телефон',
        'email' => 'Электронная почта',
        'address' => 'Адрес',
        'working_hours' => 'Часы работы',
        'vk' => 'ВКонтакте',
        'telegram' => 'Telegram',
        'whatsapp' => 'WhatsApp',
        'youtube' => 'YouTube',
    ];
}
function contacts_data() {
    $saved = get_option('nuts_contacts', []);
    if (!is_array($saved)) $saved = [];
    $data = [];
    foreach (contacts_fields() as $key => $label) $data[$key] = is_string($saved[$key] ?? null) ? $saved[$key] : '';
    return $data;
}
function contacts_filled() {
    foreach (contacts_data() as $value) if (trim($value) !== '') return true;
    return false;
}
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1', '/contacts', [
        'methods' => ['GET', 'POST'],
        'permission_callback' => function () { return current_user_can('manage_options'); },
        'callback' => function ($request) {
            if ($request->get_method() === 'GET') return contacts_data();
            $data = contacts_data();
            foreach (contacts_fields() as $key => $label) {
                $value = $request->get_param($key);
                if (!is_string($value)) continue;
                $value = trim($value);
                if (in_array($key, ['vk', 'telegram', 'whatsapp', 'youtube'], true) && $value !== '' && !preg_match('/^https?:\/\//D', $value)) {
                    return new \WP_Error('nuts_invalid_contact', $label.': укажите полный адрес, начиная с https://.', ['status' => 400]);
                }
                if ($key === 'email' && $value !== '' && !is_email($value)) {
                    return new \WP_Error('nuts_invalid_contact', 'Электронная почта указана некорректно.', ['status' => 400]);
                }
                $data[$key] = sanitize_text_field($value);
            }
            update_option('nuts_contacts', $data, false);
            return contacts_data();
        },
    ]);
});
// [nuts_contacts field="phone"] prints a single sanitized value wherever the theme needs it.
add_shortcode('nuts_contacts', function ($atts) {
    $atts = shortcode_atts(['field' => ''], is_array($atts) ? $atts : []);
    $data = contacts_data();
    $key = (string) $atts['field'];
    if (!isset($data[$key]) || $data[$key] === '') return '';
    $value = $data[$key];
    if (in_array($key, ['vk', 'telegram', 'whatsapp', 'youtube'], true)) {
        return '<a href="'.esc_url($value).'" rel="noopener" target="_blank">'.esc_html(contacts_fields()[$key]).'</a>';
    }
    if ($key === 'email') return '<a href="mailto:'.antispambot(esc_attr($value)).'">'.antispambot(esc_html($value)).'</a>';
    if ($key === 'phone') return '<a href="tel:'.preg_replace('/[^+\d]/', '', $value).'">'.esc_html($value).'</a>';
    return esc_html($value);
});
