<?php


namespace App\MenuBuilder;


use App\Helpers\LanguageHelper;
use App\Menu;
use Illuminate\Support\Pluralizer;

class MenuBuilderFrontendRender
{
    protected $page_id;

    public function render_frrontend_panel_menu($id){
        $output = '';
        if (empty($id)){
            return $output;
        }
        $menu_details_from_db = Menu::find($id);
        $default_lang = $menu_details_from_db->lang ?? LanguageHelper::default_slug();
        $menu_data = json_decode($menu_details_from_db->content);
        $this->page_id = 1;
        foreach ($menu_data as $menu_item){
            $this->page_id++;
            $output .= $this->render_menu_item($menu_item,$this->page_id,$default_lang);
        }

        return $output;
    }


    private function get_attribute_string(array $li_attributes):string
    {
        if (empty($li_attributes)){return '';}
        $attr_val = '';
        foreach ($li_attributes as $attr => $value){
            if (!empty($value)){
                $attr_val .= $attr.'="'.$value.'"';
            }
        }
        return $attr_val;
    }

    private function render_li_start(string $title, array $attributes_string,$default_lang)
    {
        $output = "\n\t".'<li '.$this->get_attribute_string($attributes_string).'> '."\n";

        return $output;
    }


    private function render_menu_item($menu_item, int $page_id, $default_lang)
    {
        $attributes_string = property_exists($menu_item,'children') ? ['class' => 'menu-item-has-children']  : [];

        if (empty((array)$menu_item)){return;}
        
        $menu_item = (object) $menu_item ;
        $ptype =  property_exists($menu_item,'ptype') ? $menu_item->ptype : '';
        
        
        $output = '';

        if ($ptype === 'custom'){
            $output .=  $this->render_li_start($menu_item->pname,$attributes_string,$default_lang);
            $title = $menu_item->pname;
            $output .= $this->get_anchor_markup($title,[
                'href' => str_replace('@url',url('/'),$menu_item->purl),
                'target' => $menu_item->antarget ?? '',
            ],$menu_item->icon ?? '');

        }elseif ($ptype === 'static'){
            $title = get_static_option(str_replace('-','_',$menu_item->pslug).'_page_'.$default_lang.'_name') ?? '';
            $output .=  $this->render_li_start($menu_item->pname,$attributes_string,$default_lang);
            // get anchor data
            $output .= $this->get_anchor_markup($title,[
                'href' => url('/').'/'. get_static_option(str_replace('-','_',$menu_item->pslug).'_page_slug') ?? '' ,
                'target' => $menu_item->antarget ?? '',
            ],$menu_item->icon ?? '');

        }else{
            //check is mega menu
            preg_match('/MegaMenus/',$ptype,$matches);
            if (!empty($matches[0])){
                $li_attributes = ['class' => 'menu-item-has-mega-menu'];
                $class_name = '\\'.$ptype;
                $instance = new $class_name();
                if ($instance->enable()){
                    $static_name = str_replace('[lang]',$default_lang,$instance->name());
                    $title = htmlspecialchars(strip_tags(get_static_option($static_name)));
                    $output .=  $this->render_li_start($title,$li_attributes,$default_lang);
                    // get anchor data
                    $output .= $this->get_anchor_markup($title,[
                        'href' => url('/').'/'.get_static_option($instance->slug()) ?? '#' ,
                        'target' => $menu_item->antarget ?? '',
                    ],$menu_item->icon ?? '');
                    $output .= $instance->render($menu_item->items_id ?? '',$default_lang);
                }
            }else {

                $menu_setup_instance = new MenuBuilderSetup();
                $all_dynamic_menus =  $menu_setup_instance->register_dynamic_menus();
                $dynamic_menu_type = $all_dynamic_menus[$ptype] ?? null;

                if ($dynamic_menu_type){

                    //load dynamic page item
                    $model_name = '\\'.$dynamic_menu_type['model'];
                    $model = new $model_name();
                    if ($dynamic_menu_type['query'] === 'old_lang'){
                        $item_details =  $model->where('id',$menu_item->pid)->first();
                    }else{
                        $item_details =  $model->with(['lang_query' => function($query) use ($default_lang){
                            $query->where('lang',$default_lang);
                        }])->where('id',$menu_item->pid)->first();
                    }

                    $title_param = $dynamic_menu_type['title_param'];
                    $query_lang = 'lang_query';
                    $title = $dynamic_menu_type['query'] === 'old_lang' ? $item_details->$title_param : $item_details->$query_lang->$title_param;


                    $output .=  $this->render_li_start($title,$attributes_string,$default_lang);
                    // get anchor data
                    $route_params = [];
                    $route_params_list= $dynamic_menu_type['route_params'] ?? [];
                    foreach ($route_params_list as $param){
                        $dynamic_param = $dynamic_menu_type['query'] === 'old_lang' ? $item_details->$param : $item_details->$query_lang->$param;
                        if (preg_match('/id/',$param)){
                            $route_params['id'] = $dynamic_param;
                        }else{
                            $route_params[$param] = $dynamic_param;
                        }

                    }
                    $output .= $this->get_anchor_markup($title,[
                        'href' => route($dynamic_menu_type['route'],$route_params),
                        'target' => $menu_item->antarget ?? '',
                    ],$menu_item->icon ?? '');
                }
            }
        }
        //check it has children
        if (property_exists($menu_item,'children')){
            $output .= $this->render_children_item($menu_item->children,$default_lang);
        }

        $output .= '</li>';
        return $output;
    }

    protected function render_children_item($menu_item,$default_lang){
        if (empty((array)$menu_item)){return;}
        $output= '';
        $output .= '<ul class="sub-menu">'."\n";
        foreach ( $menu_item as $ch_item) {
            $this->page_id +=1;
            $output .=  $this->render_menu_item( $ch_item, $this->page_id, $default_lang);
        }
        $output .= '</ul>'."\n";
        return $output;
    }

    private function get_anchor_markup(string $title,array $args, $icon = null)
    {
        $icon_markup = $icon ? "<i class='".$icon."'></i>" : '';
        return "\t\t".'<a '.$this->get_attribute_string($args).'>'.$icon_markup.$title.'</a>'."\n";
    }


}