<?php

namespace modules\mailbox\core;

require_once __DIR__.'/../third_party/node.php';
require_once __DIR__.'/../vendor/autoload.php';
use Firebase\JWT\JWT as Mailbox_JWT;
use Firebase\JWT\Key as Mailbox_Key;
use WpOrg\Requests\Requests as Mailbox_Requests;

class Apiinit
{
    public static function the_da_vinci_code($module_name)
    {
        return true;
    }

    public static function ease_of_mind($module_name)
    {
        return true;
    }

    public static function activate($module)
    {
        return true;
    }

    public static function getUserIP()
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }

    public static function pre_validate($module_name, $code = '')
    {
        get_instance()->load->library('mailbox_aeiou');
        $module = get_instance()->app_modules->get($module_name);
        if (empty($code)) {
            return ['status' => false, 'message' => 'Purchase key is required'];
        }
        $all_activated = get_instance()->app_modules->get_activated();
        foreach ($all_activated as $active_module => $value) {
            $verification_id =  get_option($active_module.'_verification_id');
            if (!empty($verification_id)) {
                $verification_id = base64_decode($verification_id);
                $id_data         = explode('|', $verification_id);
                if ($id_data[3] == $code) {
                    return ['status' => false, 'message' => 'This Purchase code is Already being used in other module'];
                }
            }
        }

        $envato_res = get_instance()->mailbox_aeiou->getPurchaseData($code);

        if (empty($envato_res) || !\is_object($envato_res) || isset($envato_res->error) || !isset($envato_res->sold_at)) {
            return ['status' => false, 'message' => 'Something went wrong'];
        }
        if (basename($module['headers']['uri']) != $envato_res->item->id) {
            return ['status' => false, 'message' => 'Purchase key is not valid'];
        }
        get_instance()->load->library('user_agent');
        $data['user_agent']       = get_instance()->agent->browser().' '.get_instance()->agent->version();
        $data['activated_domain'] = base_url();
        $data['requested_at']     = date('Y-m-d H:i:s');
        $data['ip']               = self::getUserIP();
        $data['os']               = get_instance()->agent->platform();
        $data['purchase_code']    = $code;
        $data['envato_res']       = $envato_res;
        $data                     = json_encode($data);

        try {
            
            $response = Mailbox_Requests::post(REG_PROD_POINT, ['Accept' => 'application/json'], $data);

            
            if ($response->status_code >= 500 || 404 == $response->status_code) {
                update_option($module_name.'_verification_id', '');
                update_option($module_name.'_last_verification', time());
                update_option($module_name.'_heartbeat', base64_encode(json_encode(['status' => $response->status_code, 'id' => $code, 'end_point' => REG_PROD_POINT])));

                return ['status' => true];
            }
            $response = json_decode($response->body);
            if (200 != $response->status) {
                return ['status' => false, 'message' => $response->message];
            }
            $return = $response->data ?? [];
            if (!empty($return)) {
                update_option($module_name.'_verification_id', base64_encode($return->verification_id));
                update_option($module_name.'_last_verification', time());
                update_option($module_name.'_product_token', $return->token);
                delete_option($module_name.'_heartbeat');

                return ['status' => true];
            }
        } catch (Exception $e) {
            update_option($module_name.'_verification_id', '');
            update_option($module_name.'_last_verification', time());
            update_option($module_name.'_heartbeat', base64_encode(json_encode(['status' => $request->status_code, 'id' => $code, 'end_point' => REG_PROD_POINT])));

            return ['status' => true];
        }

        return ['status' => false, 'message' => 'Something went wrong'];
    }
}
