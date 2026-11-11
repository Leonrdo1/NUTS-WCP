<?php
namespace NutsCMS;
defined('ABSPATH')||exit;
add_filter('body_class',function($classes){if(is_admin_bar_showing()&&current_user_can('edit_pages'))$classes[]='nuts-toolbar';return $classes;});
add_filter('admin_body_class',function($classes){return $classes.' nuts-toolbar';});
add_action('wp_enqueue_scripts',function(){
 if(!is_admin_bar_showing()||!current_user_can('edit_pages'))return;
 wp_enqueue_style('nuts-toolbar',plugins_url('../assets/toolbar.css',__FILE__),[],filemtime(__DIR__.'/../assets/toolbar.css'));
 wp_enqueue_script('nuts-community',plugins_url('../assets/community.js',__FILE__),['wp-element'],filemtime(__DIR__.'/../assets/community.js'),true);
 wp_localize_script('nuts-community','nutsCommunityConfig',['root'=>rest_url('nuts/v1/'),'nonce'=>wp_create_nonce('wp_rest')]);
});
add_action('admin_bar_menu',function($bar){
 if(!current_user_can('edit_pages'))return;
 $base=admin_url('admin.php?page=nuts-cms');
 // The site's stock submenu contains direct dashboard/theme links; replace it.
 foreach(['dashboard','themes','widgets','menus','customize','edit-site'] as $id)$bar->remove_node($id);
 $site=$bar->get_node('site-name');if($site){$site->href=is_admin()?home_url('/'):$base;$bar->add_node((array)$site);}
 $bar->add_node(['id'=>'nuts-open-panel','parent'=>'site-name','title'=>'Открыть NUTS ПУС','href'=>$base]);
 $bar->add_node(['id'=>'nuts-view-site','parent'=>'site-name','title'=>'Открыть сайт','href'=>home_url('/')]);
 foreach($bar->get_nodes() as $node){
  if($node->id==='nuts-system'||!is_string($node->href)||strpos($node->href,admin_url())!==0||strpos($node->href,$base)===0)continue;
  $node->href=$node->href===admin_url()?$base:$base.'#wp='.rawurlencode($node->href);$bar->add_node((array)$node);
 }
 $logo=$bar->get_node('wp-logo');if($logo){$logo->href=$base;$bar->add_node((array)$logo);}
},10004);

// Two view links share the toolbar on the site and in the panel.
add_action('admin_bar_menu', function ($bar) {
 if(!current_user_can('edit_pages'))return;
 $base=admin_url('admin.php?page=nuts-cms');
 $asset=plugins_url('../assets/figma/',__FILE__);
 $icon=get_site_icon_url(32);
 $panel=$bar->get_node('wp-logo');
 if($panel){
  $panel->title='<span class="nuts-view-icon" aria-hidden="true"><img src="'.esc_url($asset.'panel.svg').'" alt="" width="20" height="20"></span><span class="nuts-view-label">Панель</span>';
  $panel->href=$base;$panel->meta['class']='nuts-view-tab'.(is_admin()?' nuts-view-active':'');$panel->meta['title']='Панель NUTS ПУС';$bar->add_node((array)$panel);
 }
 $site=$bar->get_node('site-name');
 if($site){
  $site->title='<span class="nuts-view-icon'.($icon?' nuts-view-favicon':' nuts-view-fallback').'" aria-hidden="true"><img src="'.esc_url($icon?:$asset.'site-fallback.svg').'" alt="" width="20" height="20"></span><span class="nuts-view-label">Сайт</span>';
  $site->href=home_url('/');$site->meta['class']='nuts-view-tab'.(!is_admin()?' nuts-view-active':'');$site->meta['title']='Сайт — '.get_bloginfo('name');$bar->add_node((array)$site);
 }
},10005);
