<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Mailbox
Module URI: https://codecanyon.net/item/mailbox-webmail-client-for-perfex-crm/25308081
Description: Mailbox is a webmail client for Perfex's dashboard.
Version: 2.0.3
Requires at least: 3.0
Author: Themesic Interactive
Author URI: https://1.envato.market/themesic
*/

define('MAILBOX_MODULE', 'mailbox');
define('MAILBOX_MODULE_UPLOAD_FOLDER', module_dir_path(MAILBOX_MODULE, 'uploads'));
require_once __DIR__.'/vendor/autoload.php';
modules\mailbox\core\Apiinit::the_da_vinci_code(MAILBOX_MODULE);
modules\mailbox\core\Apiinit::ease_of_mind(MAILBOX_MODULE);
hooks()->add_action('after_cron_run', 'scan_email_server');
hooks()->add_action('app_admin_head', 'mailbox_add_head_components');
hooks()->add_action('app_admin_footer', 'mailbox_load_js');
hooks()->add_action('admin_init', 'mailbox_add_settings_tab');
hooks()->add_action('admin_init', 'mailbox_module_init_menu_items');
hooks()->add_filter('migration_tables_to_replace_old_links', 'mailbox_migration_tables_to_replace_old_links');
hooks()->add_action('after_lead_lead_tabs', 'mailbox_after_lead_lead_tabs');
hooks()->add_action('after_lead_tabs_content', 'mailbox_after_lead_tabs_content');

/**
 * Injects chat CSS.
 *
 * @return null
 */
function mailbox_add_head_components()
{
    if ('1' == get_option('mailbox_enabled')) {
        $CI = &get_instance();
        echo '<link href="'.base_url('modules/mailbox/assets/css/mailbox_styles.css').'?v='.$CI->app_scripts->core_version().'"  rel="stylesheet" type="text/css" />';
    }
}

/**
 * Injects chat Javascript.
 *
 * @return null
 */
function mailbox_load_js()
{
    if ('1' == get_option('mailbox_enabled')) {
        $CI = &get_instance();
        echo '<script src="'.module_dir_url('mailbox', 'assets/js/mailbox_js.js').'?v='.$CI->app_scripts->core_version().'"></script>';
    }
}

/**
 * Init mailbox module menu items in setup in admin_init hook.
 *
 * @return null
 */
function mailbox_module_init_menu_items()
{
    $CI = &get_instance();
    if ('1' == get_option('mailbox_enabled')) {
        $badge      = '';
        $num_unread = total_rows(db_prefix().'mail_inbox', ['read' => '0', 'to_staff_id' => get_staff_user_id()]);
        if ($num_unread > 0) {
            $badge = ' • '.total_rows(db_prefix().'mail_inbox', ['read' => '0', 'to_staff_id' => get_staff_user_id()]).'';
        }

        $CI->app_menu->add_sidebar_menu_item('mailbox', [
            'name'     => _l('mailbox').$badge,
            'href'     => admin_url('mailbox'),
            'icon'     => 'fa fa-envelope-square',
            'position' => 6,
        ]);
    }
}

/**
 * Init mailbox module setting menu items in setup in admin_init hook.
 *
 * @return null
 */
function mailbox_add_settings_tab()
{
    $CI = &get_instance();
    $CI->app_tabs->add_settings_tab('mailbox-settings', [
       'name'     => ''._l('mailbox_setting').'',
       'view'     => 'mailbox/mailbox_settings',
       'position' => 36,
   ]);
}

/**
 * mailbox migration tables to replace old links description.
 *
 * @param array $tables
 *
 * @return array
 */
function mailbox_migration_tables_to_replace_old_links($tables)
{
    $tables[] = [
                'table' => db_prefix().'mail_inbox',
                'field' => 'description',
            ];

    return $tables;
}

/**
 * Scan mailbox from mail-server.
 *
 * @return [bool] [true/false]
 */
function scan_email_server()
{
    $enabled      = get_option('mailbox_enabled');
    $imap_server  = get_option('mailbox_imap_server');
    $encryption   = get_option('mailbox_encryption');
    $folder_scan  = get_option('mailbox_folder_scan');
    
    error_log('Mailbox Debug - Configurações:');
    error_log('Mailbox Debug - Enabled: ' . $enabled);
    error_log('Mailbox Debug - Server: ' . $imap_server);
    error_log('Mailbox Debug - Encryption: ' . $encryption);
    error_log('Mailbox Debug - Folder: ' . $folder_scan);

    $check_every  = '1';
    $unseen_email = get_option('mailbox_only_loop_on_unseen_emails');

    error_log('Mailbox Debug - Iniciando verificação de emails');
    error_log('Mailbox Debug - Servidor: ' . $imap_server);
    error_log('Mailbox Debug - Encriptação: ' . $encryption);

    if (1 == $enabled && strlen($imap_server) > 0) {
        $CI = &get_instance();
        $CI->db->select()
            ->from(db_prefix().'staff')
            ->where(db_prefix().'staff.mail_password !=', '');
        $staffs = $CI->db->get()->result_array();

        require_once APPPATH.'third_party/php-imap/Imap.php';
        include_once APPPATH.'third_party/simple_html_dom.php';

        foreach ($staffs as $staff) {
            $last_run    = $staff['last_email_check'];
            $staff_email = $staff['email'];
            $staff_id    = $staff['staffid'];
            $email_pass  = $staff['mail_password'];

            if (empty($last_run) || (time() > $last_run + ($check_every * 60))) {
                $CI->db->where('staffid', $staff_id);
                $CI->db->update(db_prefix().'staff', [
                    'last_email_check' => time(),
                ]);

                try {
                    error_log('Mailbox Debug - Tentando conectar com: ' . $staff_email);
                    
                    $imap = new Imap($imap_server, $staff_email, $email_pass, $encryption);
                    
                    if (false === $imap->isConnected()) {
                        error_log('Mailbox Debug - Falha na conexão para: ' . $staff_email);
                        error_log('Mailbox Debug - Erro detalhado: ' . $imap->getError());
                        continue;
                    }

                    error_log('Mailbox Debug - Conexão bem sucedida para: ' . $staff_email);

                    if ('' == $folder_scan) {
                        $folder_scan = 'Inbox';
                    }

                    $imap->selectFolder($folder_scan);
                    error_log('Mailbox Debug - Pasta selecionada: ' . $folder_scan);

                    if (1 == $unseen_email) {
                        $emails = $imap->getUnreadMessages();
                    } else {
                        $emails = $imap->getMessages();
                    }

                    error_log('Mailbox Debug - Emails encontrados: ' . count($emails));

                    foreach ($emails as $email) {
                        $plainTextBody = $imap->getPlainTextBody($email['uid']);
                        $plainTextBody = trim($plainTextBody);
                        
                        if (!empty($plainTextBody)) {
                            $email['body'] = $plainTextBody;
                        }

                        $email['body'] = handle_google_drive_links_in_text($email['body']);
                        $email['body'] = prepare_imap_email_body_html($email['body']);
                        
                        $data = [];
                        $data['attachments'] = [];

                        if (isset($email['attachments'])) {
                            foreach ($email['attachments'] as $key => $at) {
                                $_at_name = $email['attachments'][$key]['name'];
                                unset($email['attachments'][$key]['name']);
                                $email['attachments'][$key]['filename'] = $_at_name;
                                $_attachment = $imap->getAttachment($email['uid'], $key);
                                $email['attachments'][$key]['data'] = $_attachment['content'];
                            }
                            $data['attachments'] = $email['attachments'];
                        }

                        $data['to'] = [];
                        if (isset($email['to'])) {
                            foreach ($email['to'] as $to) {
                                $data['to'][] = trim(preg_replace('/(.*)<(.*)>/', '\\2', $to));
                            }
                        }

                        $data['cc'] = [];
                        if (isset($email['cc'])) {
                            foreach ($email['cc'] as $cc) {
                                $data['cc'][] = trim(preg_replace('/(.*)<(.*)>/', '\\2', $cc));
                            }
                        }

                        if ('true' == hooks()->apply_filters('imap_fetch_from_email_by_reply_to_header', 'true')) {
                            $replyTo = $imap->getReplyToAddresses($email['uid']);
                            if ($replyTo) {
                                $email['from'] = $replyTo[0];
                            }
                        }

                        $data['subject'] = $email['subject'];
                        $data['body']    = $email['body'];
                        $data['date']    = $email['date'];
                        $data['from']    = $email['from'];
                        
                        error_log('Mailbox Debug - Processando email: ' . $data['subject']);
                        
                        $inbox_id = save_email($data, $staff_id);

                        if ($inbox_id) {
                            $imap->setUnseenMessage($email['uid']);
                            error_log('Mailbox Debug - Email salvo com sucesso - ID: ' . $inbox_id);
                        }
                    }
                } catch (Exception $e) {
                    error_log('Mailbox Debug - Erro: ' . $e->getMessage() . ' - Email: ' . $staff_email);
                }
            }
        }
    }
    return false;
}

/**
 * Load the module helper.
 */
$CI = &get_instance();
$CI->load->helper(MAILBOX_MODULE.'/mailbox');

/*
 * Register the activation mailbox
 */
register_activation_hook(MAILBOX_MODULE, 'mailbox_activation_hook');

/**
 * The activation function.
 */
function mailbox_activation_hook()
{
    $CI = &get_instance();
    require_once __DIR__.'/install.php';
}

/*
 * Register mailbox language files
 */
register_language_files(MAILBOX_MODULE, [MAILBOX_MODULE]);


hooks()->add_action('app_init', MAILBOX_MODULE.'_actLib');
function mailbox_actLib()
{
    return true;
}

hooks()->add_action('pre_activate_module', MAILBOX_MODULE.'_sidecheck');
function mailbox_sidecheck($module_name)
{
    return true;
}

hooks()->add_action('pre_deactivate_module', MAILBOX_MODULE.'_deregister');
function mailbox_deregister($module_name)
{
    return true;
}

function mailbox_after_lead_lead_tabs(){
   echo '<li role="presentation">
            <a href="#conversation" aria-controls="conversation" role="tab" data-toggle="tab">
                ' . _l("conversation") . '
            </a>
        </li>';
}

function mailbox_after_lead_tabs_content($data){
    if ($data) {
    $CI = &get_instance();
    $id = $data->id;
    $data = array();
    $CI->db->select('*');
    $CI->db->from('mail_conversation');
    $CI->db->where('lead_id', $id);
    $result = $CI->db->get()->result_array();
    $getdata = [];
    foreach ($result as $key => $value) {
        
       if ($value['inbox_id']) {

           $CI->db->select('*');
           $CI->db->from('mail_inbox');
           $CI->db->join('mail_conversation', 'mail_inbox.id = mail_conversation.inbox_id');
           $CI->db->where('mail_inbox.id', $value['inbox_id']);
           $result_array = $CI->db->get()->result_array();
           if ($result_array) {
             $getdata[] = $result_array[0];
            }
       }else{

           $CI->db->select('*');
           $CI->db->from('mail_outbox');
           $CI->db->join('mail_conversation', 'mail_outbox.id = mail_conversation.outbox_id');
           $CI->db->where('mail_outbox.id', $value['outbox_id']);
           $result_array = $CI->db->get()->result_array();
           if ($result_array) {
             $getdata[] = $result_array[0];
            }
       }
    }
    $data['conversation']  = $getdata;
    $data['module_dir_url'] = module_dir_url(MAILBOX_MODULE);
    $CI = &get_instance();
    echo $CI->load->view('mailbox/conversation', $data, true);
    }
}

function save_email($data, $staff_id) {
    $CI = &get_instance();
    
    // Extrair email do campo from
    $from_email = trim(preg_replace('/(.*)<(.*)>/', '\\2', $data['from']));
    if (empty($from_email)) {
        $from_email = $data['from']; // Caso não tenha o formato com <>
    }
    
    $insert_data = [
        'from_staff_id' => 0,
        'to_staff_id' => $staff_id,
        'to' => is_array($data['to']) ? implode(',', $data['to']) : $data['to'],
        'cc' => is_array($data['cc']) ? implode(',', $data['cc']) : $data['cc'],
        'subject' => $data['subject'],
        'body' => $data['body'],
        'date_received' => date('Y-m-d H:i:s', strtotime($data['date'])),
        'from_email' => $from_email,
        'read' => 0,
        'folder' => 'inbox',
        'has_attachment' => !empty($data['attachments']) ? 1 : 0
    ];
    
    $CI->db->insert(db_prefix().'mail_inbox', $insert_data);
    $insert_id = $CI->db->insert_id();
    
    if ($insert_id) {
        log_activity('Novo email recebido de: ' . $from_email);
        return $insert_id;
    }
    
    return false;
}