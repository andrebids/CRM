<?php

hooks()->add_action('after_clients_area_init', 'my_disable_customers_area'); 
hooks()->add_action('clients_authentication_constructor', 'my_disable_customers_area'); 
// Uncomment to disable customers area knowledge base as well
//  hooks()->add_action('customers_area_knowledge_base_construct', 'my_disable_customers_area'); 

function my_disable_customers_area(){ 
    header('HTTP/1.0 401 Unauthorized'); 
    die('Access not allowed'); 
}

hooks()->add_action('app_init','my_change_default_url_to_admin');

function my_change_default_url_to_admin(){
    $CI = &get_instance();

    if(!is_client_logged_in() && !$CI->uri->segment(1)){
        redirect(site_url('admin/authentication'));
    }
}