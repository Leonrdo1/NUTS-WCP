<?php
namespace NutsCMS;
defined('ABSPATH') || exit;
// Store only a small, redacted incident summary; never expose stack traces or log contents.
register_shutdown_function(function () {
    $error=error_get_last();
    if(!$error || !in_array($error['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true)) return;
    $items=get_option('nuts_critical_incidents',[]);
    if(!is_array($items)) $items=[];
    array_unshift($items,['time'=>time(),'type'=>(int)$error['type'],'file'=>sanitize_file_name(basename($error['file'])),'line'=>(int)$error['line']]);
    update_option('nuts_critical_incidents',array_slice($items,0,20),false);
});
add_action('rest_api_init',function () {
    register_rest_route('nuts/v1','/status',[
        'methods'=>'GET','permission_callback'=>function(){return current_user_can('manage_options');},
        'callback'=>function () {
            global $wpdb;
            $start=microtime(true);
            require_once ABSPATH.'wp-admin/includes/class-wp-site-health.php';
            require_once ABSPATH.'wp-admin/includes/plugin.php';
            $health=\WP_Site_Health::get_instance();$checks=[];
            foreach(['php_default_timezone','php_sessions','is_in_debug_mode','insecure_registration','file_uploads'] as $test) {
                if(!is_callable([$health,'get_test_'.$test])) continue;
                $result=$health->{'get_test_'.$test}();
                $checks[]=['id'=>$test,'label'=>wp_strip_all_tags($result['label']),'status'=>$result['status'],'description'=>wp_strip_all_tags($result['description'])];
            }
            $cached=get_transient('health-check-site-status-result');
            $summary=is_string($cached)?json_decode($cached,true):null;
            $db_start=microtime(true);$db_ok=(string)$wpdb->get_var('SELECT 1')==='1';$db_ms=round((microtime(true)-$db_start)*1000,2);
            $playground=is_dir('/internal') || stripos(php_uname(),'emscripten')!==false;
            $load=(!$playground && function_exists('sys_getloadavg'))?@sys_getloadavg():false;
            $free=(!$playground && function_exists('disk_free_space'))?@disk_free_space(ABSPATH):false;
            $total=(!$playground && function_exists('disk_total_space'))?@disk_total_space(ABSPATH):false;
            $core=get_site_transient('update_core');$next=[];
            if(is_object($core)&&!empty($core->updates)) foreach($core->updates as $item) if(($item->response??'')==='upgrade')$next[]=$item->current;
            $plugin_updates=get_site_transient('update_plugins');$theme_updates=get_site_transient('update_themes');
            $plugins=get_plugins();$active=(array)get_option('active_plugins',[]);
            $counts=[];foreach(['page','post',post_type_exists('product')?'product':'nuts_product'] as $type){$c=wp_count_posts($type);$counts[$type]=['published'=>(int)($c->publish??0),'drafts'=>(int)($c->draft??0)];}
            $response=[
                'time'=>gmdate('c'),'cms_version'=>VERSION,'wordpress'=>get_bloginfo('version'),
                'server'=>['php'=>PHP_VERSION,'sapi'=>PHP_SAPI,'os'=>PHP_OS_FAMILY,'environment'=>wp_get_environment_type(),'playground'=>$playground,'software'=>sanitize_text_field($_SERVER['SERVER_SOFTWARE']??'Не предоставлено'),'https'=>is_ssl(),'database_ok'=>$db_ok,'database_ms'=>$db_ms],
                'metrics'=>['load_average'=>is_array($load)?array_map(function($n){return round($n,2);},$load):null,'disk_free'=>is_numeric($free)?$free:null,'disk_total'=>is_numeric($total)?$total:null,'memory_peak'=>memory_get_peak_usage(true),'memory_limit'=>ini_get('memory_limit'),'upload_limit'=>wp_max_upload_size(),'post_limit'=>ini_get('post_max_size'),'execution_limit'=>ini_get('max_execution_time')],
                'health'=>['checks'=>$checks,'cached_summary'=>is_array($summary)?array_intersect_key($summary,array_flip(['good','recommended','critical'])):null],
                'updates'=>['wordpress_versions'=>array_values(array_unique($next)),'plugins'=>is_object($plugin_updates)&&isset($plugin_updates->response)?count((array)$plugin_updates->response):null,'themes'=>is_object($theme_updates)&&isset($theme_updates->response)?count((array)$theme_updates->response):null,'last_checked'=>is_object($core)?($core->last_checked??null):null,'core_auto'=>defined('WP_AUTO_UPDATE_CORE')?WP_AUTO_UPDATE_CORE:null,'can_core'=>current_user_can('update_core'),'can_upload'=>current_user_can('upload_plugins')&&current_user_can('install_plugins')&&current_user_can('update_plugins')&&wp_is_file_mod_allowed('nuts-cms-update')],
                'errors'=>['debug'=>defined('WP_DEBUG')&&WP_DEBUG,'log_enabled'=>defined('WP_DEBUG_LOG')&&(bool)WP_DEBUG_LOG,'incidents'=>get_option('nuts_critical_incidents',[])],
                'content'=>$counts,'plugins'=>['installed'=>count($plugins),'active'=>count($active)],
            ];
            $response['metrics']['snapshot_ms']=round((microtime(true)-$start)*1000,2);
            return rest_ensure_response($response);
        }
    ]);
});
