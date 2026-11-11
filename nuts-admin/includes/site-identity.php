<?php
namespace NutsCMS;
defined('ABSPATH')||exit;
function default_site_icon_url($size=32){
 $size=$size<=32?32:($size<=180?180:($size<=192?192:512));
 return plugins_url('../assets/icons/favicon-'.$size.'.png',__FILE__);
}
add_filter('get_site_icon_url',function($url,$size,$blog_id){
 $id=$blog_id && $blog_id!==get_current_blog_id()?get_blog_option($blog_id,'site_icon',0):get_option('site_icon',0);
 return $id && $url ? $url : default_site_icon_url($size);
},20,3);
function site_icon_data(){
 $id=(int)get_option('site_icon',0);
 return ['id'=>$id,'url'=>get_site_icon_url(32),'preview'=>get_site_icon_url(512)];
}
add_action('rest_api_init',function(){
 register_rest_route('nuts/v1','/site-icon',['methods'=>['GET','POST'],'permission_callback'=>function(){return current_user_can('manage_options');},'callback'=>function($r){
  if($r->get_method()==='POST'){
   $id=$r->get_param('id');
   if(!is_int($id)||$id<0)return new \WP_Error('nuts_invalid_icon','Неверный идентификатор изображения.',['status'=>400]);
   if($id && (!wp_attachment_is_image($id)||!current_user_can('read_post',$id)))return new \WP_Error('nuts_invalid_icon','Выберите доступное изображение из медиатеки.',['status'=>400]);
   if($id){$meta=wp_get_attachment_metadata($id);if(empty($meta['width'])||empty($meta['height'])||min($meta['width'],$meta['height'])<32)return new \WP_Error('nuts_small_icon','Изображение должно быть не меньше 32 × 32 пикселей.',['status'=>400]);}
   update_option('site_icon',$id);
  }
  return site_icon_data();
 }]);
});
