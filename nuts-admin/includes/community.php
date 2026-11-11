<?php
namespace NutsCMS;
defined('ABSPATH')||exit;
add_action('admin_bar_menu',function($bar){
 if(!current_user_can('edit_pages'))return;
 $base=admin_url('admin.php?page=nuts-cms');$nodes=$bar->get_nodes();
 foreach($nodes as $id=>$node){$parent=$node->parent;$depth=0;while($parent&&isset($nodes[$parent])&&$depth++<10){if($parent==='wp-logo'){$bar->remove_node($id);break;}$parent=$nodes[$parent]->parent;}}
 $logo=$bar->get_node('wp-logo');if($logo){$logo->href=$base.'#nuts=news';$bar->add_node((array)$logo);}
 foreach(['news'=>'Новости панели','developer'=>'Страница разработчика','help'=>'Справка'] as $id=>$title)$bar->add_node(['id'=>'nuts-'.$id,'parent'=>'wp-logo','title'=>$title,'href'=>$base.'#nuts='.$id]);
 $bar->add_node(['id'=>'nuts-system','parent'=>'wp-logo','title'=>'Системный вид','href'=>admin_url()]);
 if(current_user_can('edit_pages')){
 $bar->add_node(['id'=>'nuts-notifications','parent'=>'top-secondary','title'=>'<span class="nuts-bell" aria-hidden="true"></span><span class="nuts-notification-count" hidden>0</span><span class="screen-reader-text">Уведомления</span>','href'=>$base.'#nuts=notifications','meta'=>['title'=>'Уведомления']]);
 $bar->add_node(['id'=>'nuts-notification-preview','parent'=>'nuts-notifications','title'=>'<span id="nuts-notification-preview">Загружаем уведомления…</span>','href'=>$base.'#nuts=notifications']);
 }
},10003);
add_action('wp_after_insert_post',function($id,$post,$update,$before){
 if(wp_is_post_revision($id)||wp_is_post_autosave($id)||!in_array($post->post_type,['page','post','nuts_product','product'],true)||in_array($post->post_status,['auto-draft','trash','inherit'],true))return;
 if($update&&$before&&$before->post_status===$post->post_status)return;
 $items=get_option('nuts_panel_events',[]);if(!is_array($items))$items=[];
 array_unshift($items,['id'=>$id,'time'=>time(),'status'=>$post->post_status,'kind'=>$update?'status':'created']);update_option('nuts_panel_events',array_slice($items,0,80),false);
},10,4);
function panel_notifications(){
 $items=[];$base=admin_url('admin.php?page=nuts-cms');
 if(current_user_can('manage_options')){
 foreach((array)get_option('nuts_critical_incidents',[]) as $e)$items[]=['id'=>'error-'.md5(wp_json_encode($e)),'title'=>'Критическая ошибка PHP: '.$e['file'].':'.$e['line'],'severity'=>'critical','time'=>$e['time'],'url'=>$base.'#nuts=status'];
 foreach(['update_core'=>'ядра','update_plugins'=>'плагинов','update_themes'=>'тем'] as $key=>$label){$v=get_site_transient($key);$n=0;if($key==='update_core'&&is_object($v)){foreach(($v->updates??[]) as $u)if(($u->response??'')==='upgrade')$n++;}elseif(is_object($v))$n=count((array)($v->response??[]));if($n)$items[]=['id'=>$key.'-'.md5(wp_json_encode($key==='update_core'?$v->updates:$v->response)),'title'=>'Доступны обновления '.$label.': '.$n,'severity'=>'update','time'=>null,'url'=>$base.'#nuts=status'];}
 }
 foreach((array)get_option('nuts_panel_events',[]) as $event){$post=get_post($event['id']);if(!$post||$post->post_status==='trash'||!current_user_can('edit_post',$post->ID))continue;$items[]=['id'=>'post-'.$post->ID.'-'.$event['time'].'-'.$event['status'],'title'=>($event['kind']==='created'?'Создан материал: ':'Изменён статус: ').wp_strip_all_tags(get_the_title($post)).' · '.get_post_status_object($event['status'])->label,'severity'=>'info','time'=>$event['time'],'url'=>$base.'#wp='.rawurlencode(get_edit_post_link($post->ID,'raw'))];}
 $seen=(array)get_user_meta(get_current_user_id(),'nuts_read_notifications',true);
 foreach($items as &$item)$item['unread']=!in_array($item['id'],$seen,true);unset($item);
 return array_slice($items,0,100);
}
add_action('rest_api_init',function(){
 register_rest_route('nuts/v1','/notifications',['methods'=>['GET','POST'],'permission_callback'=>function(){return current_user_can('edit_pages');},'callback'=>function($r){$items=panel_notifications();if($r->get_method()==='POST'){update_user_meta(get_current_user_id(),'nuts_read_notifications',array_column($items,'id'));foreach($items as &$i)$i['unread']=false;}return ['items'=>$items,'unread'=>count(array_filter($items,function($i){return $i['unread'];}))];}]);
});
