<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
function appearance_presets() {
    return [
        'nuts'=>['name'=>'NUTS / синий','accent'=>'#096fd2','sidebar'=>'#333333','background'=>'#f1f2f4'],
        'ocean'=>['name'=>'Океан','accent'=>'#9bd7f5','sidebar'=>'#192b3a','background'=>'#f1f2f4'],
        'violet'=>['name'=>'Лаванда','accent'=>'#d4b9f7','sidebar'=>'#2b2438','background'=>'#f7f4fb'],
        'sand'=>['name'=>'Песок','accent'=>'#efd099','sidebar'=>'#30291f','background'=>'#faf7f0'],
    ];
}
function admin_appearance() {
    $presets=appearance_presets();
    $saved=get_option('nuts_admin_appearance', []);
    $preset=is_array($saved) ? ($saved['preset'] ?? 'nuts') : 'nuts';
    if(isset($presets[$preset])) return array_merge(['preset'=>$preset], $presets[$preset]);
    if($preset==='custom' && !empty($saved['accent']) && !empty($saved['sidebar']) && !empty($saved['background']) && sanitize_hex_color($saved['accent']) && sanitize_hex_color($saved['sidebar']) && sanitize_hex_color($saved['background'])) return array_intersect_key($saved,array_flip(['preset','accent','sidebar','background']));
    return array_merge(['preset'=>'nuts'], $presets['nuts']);
}
function appearance_css($settings) {
    $css=''; foreach(['accent','sidebar','background'] as $key) $css.='--nuts-'.$key.':'.sanitize_hex_color($settings[$key]).';';
    foreach(['accent','sidebar','background'] as $key) {
        $hex=ltrim($settings[$key],'#');$v=[];
        foreach([0,2,4] as $offset) { $c=hexdec(substr($hex,$offset,2))/255; $v[]=$c<=0.04045?$c/12.92:pow(($c+0.055)/1.055,2.4); }
        $l=$v[0]*0.2126+$v[1]*0.7152+$v[2]*0.0722;
        $css.='--nuts-on-'.$key.':'.(($l+0.05)/0.05>=1.05/($l+0.05)?'#202020':'#ffffff').';';
    }
    return $css;
}
add_action('rest_api_init', function () {
    register_rest_route('nuts/v1','/admin-appearance', [
        'methods'=>['GET','POST'],
        'permission_callback'=>function () { return current_user_can('manage_options'); },
        'callback'=>function ($request) {
            if($request->get_method()==='GET') return admin_appearance();
            $preset=$request->get_param('preset');$presets=appearance_presets();
            if(!is_string($preset)) return new \WP_Error('nuts_invalid_preset','Неизвестная цветовая схема.',['status'=>400]);
            if(isset($presets[$preset])) $settings=array_merge(['preset'=>$preset],$presets[$preset]);
            elseif($preset==='custom') {
                $settings=['preset'=>'custom'];
                foreach(['accent','sidebar','background'] as $key) {
                    $color=$request->get_param($key);
                    if(!is_string($color) || !preg_match('/^#[0-9a-fA-F]{6}$/D',$color)) return new \WP_Error('nuts_invalid_color','Цвет должен быть в формате #RRGGBB.',['status'=>400]);
                    $settings[$key]=strtolower($color);
                }
            } else return new \WP_Error('nuts_invalid_preset','Неизвестная цветовая схема.',['status'=>400]);
            update_option('nuts_admin_appearance',$settings,false);
            return admin_appearance();
        }
    ]);
});
