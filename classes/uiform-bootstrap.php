<?php

/**
 * Frontend
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   Rocket_form
 * @author    Softdiscover <info@softdiscover.com>
 * @copyright 2015 Softdiscover
 * @license   http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link      http://wordpress-cost-estimator.zigaform.com
 */
if (!defined('ABSPATH')) {
    exit('No direct script access allowed');
}
if (class_exists('Uiform_Bootstrap')) {
    return;
}

class Uiform_Bootstrap extends Uiform_Base_Module {

    protected $modules;
    protected $addons;
    protected $models;

    const VERSION = '1.2';
    const PREFIX = 'wprofmr_';

    /*
     * Magic methods
     */

    /**
     * Constructor
     *
     * @mvc Controller
     */
    protected function __construct() {
        $this->register_hook_callbacks();
    }

    /**
     * Register callbacks for actions and filters
     *
     * @mvc Controller
     */
    
    public function register_hook_callbacks() {
        global $wp_version;



        add_action('admin_menu', array(&$this, 'loadMenu'));

        //add lang dir
            add_filter('rockfm_languages_directory', array(&$this, 'rockfm_lang_dir_filter'));
            add_filter('rockfm_languages_domain', array(&$this, 'rockfm_lang_domain_filter'));
            add_filter('plugin_locale', array(&$this, 'rockfm_lang_locale_filter'));
        
       
       
        //load admin
        if (is_admin() && Uiform_Form_Helper::is_uiform_page()) {
        
            //add class to body
             add_filter( 'body_class', array(&$this, 'filter_body_class') ); 
            
            //deregister bootstrap in child themes
            add_action('admin_enqueue_scripts',array(&$this, 'remove_unwanted_css'), 1000 );
            //admin resources
            add_action('admin_enqueue_scripts', array(&$this, 'load_admin_resources'),20,1);
        
            $this->loadBackendControllers();
            //disabling wordpress update message
            add_action('admin_menu', array(&$this, 'wphidenag'));
            //format wordpress editor
            if (version_compare($wp_version, 4, '<')) {
                //for wordpress 3.x
                //event tinymce
                add_filter('tiny_mce_before_init', array(&$this, 'wpse24113_tiny_mce_before_init'));
                //add_filter('tiny_mce_before_init', array(&$this, 'myformatTinyMCE'));
            } else {
                add_filter('tiny_mce_before_init', array(&$this, 'wpver411_tiny_mce_before_init'));
                add_filter('mce_external_plugins', array(&$this, 'my_external_plugins'));
                
            }
            
            //end format wordpress editor    
            add_action('init', array($this, 'init'));
            
        } else{  
            
              //load third party tools
            $vendor_file = UIFORM_FORMS_DIR. '/helpers/vendor/autoload_52.php';
            if ( is_readable( $vendor_file ) ) {
                    require_once $vendor_file;
            }
            add_filter( 'themeisle_sdk_products', array(&$this, 'zigaform_register_sdk'), 10, 1 );
            
            //load frontend
            $this->loadFrontendControllers();
        }

        //  i18n
        add_action('init', array(&$this, 'i18n'));

        //call post processing
        if (isset($_POST['_rockfm_type_submit']) && absint($_POST['_rockfm_type_submit']) === 0) {
            add_action('plugins_loaded', array(&$this, 'uiform_process_form'));
        }
        
         // register API endpoints
        add_action('init', array(&$this, 'add_endpoint'), 0);
        // handle  endpoint requests
        add_action('parse_request', array(&$this, 'handle_api_requests'), 0);
        add_action('uifm_costestimator_api_paypal_ipn_handler', array(&$this, 'paypal_ipn_handler'));
        add_action('uifm_costestimator_api_lmode_iframe_handler', array(&$this, 'lmode_iframe_handler'));
        
        add_action('uifm_costestimator_api_pdf_show_record', array(&$this, 'action_pdf_show_record'));
        add_action('uifm_costestimator_api_pdf_show_invoice', array(&$this, 'action_pdf_show_invoice'));
        add_action('uifm_costestimator_api_csv_show_allrecords', array(&$this, 'action_csv_show_allrecords'));
        
        //add_action( 'init',                  array( $this, 'upgrade' ), 11 );
        
         //disable update notifications and more
        if(is_admin()){
            add_filter( 'site_transient_update_plugins', array(&$this, 'disable_plugin_updates'));
            
            
            //if(ZIGAFORM_C_LITE===1){
              add_filter((is_multisite() ? 'network_admin_' : '').'plugin_action_links', array($this, 'plugin_add_links'), 10, 2);
              
              // ZigaForm Upgrade
              add_action( 'admin_notices', array( $this, 'zigaform_upgrade' ) );
              
           // }
           
        }
        
    }
    
     /**
    * Registers with the SDK
    *
    * @since    1.0.0
    */
   function zigaform_register_sdk( $products ) {
           $products[] = UIFORM_ABSFILE;
           return $products;
   }
    
    public function zigaform_upgrade() {
        
        //Return, If not super admin, or don't have the admin privilleges
        if ( ! current_user_can( 'edit_others_posts' ) || ! is_super_admin() ) {
                return;
        }
        
        //No need to show it on zigaform panel
        if ( isset( $_GET['page'] ) && 'zgfm_cost_estimate' == $_GET['page'] ) {
                return;
        }
        
        //Return if notice is already dismissed
        if ( get_option( 'zgfm-c-hide_upgrade_notice' ) || get_site_option( 'zgfm-c-hide_upgrade_notice' ) ) {
                return;
        }
        
        $install_type = get_site_option( 'zgfm-c-install-type', false );
        
        $zgfm_forms_nro= self::$_models['formbuilder']['form']->CountForms();
            
        if ( ! $install_type ) {
            if ( intval($zgfm_forms_nro) > 0 ) {
                    $install_type = 'existing';
            } else {
                    $install_type = 'new';
            }
            update_site_option( 'zgfm-c-install-type', $install_type );
	}
        
        //Whether New/Existing Installation
        
        if( !$install_type ) {
                $install_type = $zgfm_forms_nro > 0 ? 'existing' : 'new';
                update_site_option( 'zgfm-c-install-type', $install_type );
        }

        if ( 'new' == $install_type  ) {
            
             if(ZIGAFORM_C_LITE===1){
                $notice_url = 'https://wordpress-cost-estimator.zigaform.com/#demo-samples';
                $notice_heading = esc_html__( "Thanks for installing Zigaform. We hope you like it!", "FRocket_admin" );
                $notice_content = esc_html__( "And hey, if you do, you can check the PRO version and get access to more features!", "FRocket_admin" );
                $button_content = esc_html__( "Go Zigaform Pro", "FRocket_admin" );
            }else{
                $notice_url = 'https://codecanyon.net/item/zigaform-wordpress-calculator-cost-estimation-form-builder/13663682';
                $notice_heading = esc_html__( "Thanks for installing Zigaform. We hope you like it!", "FRocket_admin" );
                $notice_content = esc_html__( "And hey, if you do, give it a 5-star rating on Codencayon to help us spread the word and boost our motivation.", "FRocket_admin" );
                $button_content = esc_html__( "Go Zigaform", "FRocket_admin" );
            }
                
        } else {
            
            if(ZIGAFORM_C_LITE===1){
                $notice_heading = esc_html__( "Thanks for using zigaform!", "FRocket_admin" );
                $notice_url  = 'https://wordpress.org/support/plugin/zigaform-calculator-cost-estimation-form-builder-lite/reviews/?filter=5#new-post';
                $notice_content = sprintf( __( 'Please rate <strong>Zigaform</strong> <a href="%s" target="_blank" rel="noopener" >&#9733;&#9733;&#9733;&#9733;&#9733;</a> on <a href="%s" target="_blank">WordPress.org</a> to help us spread the word. Thank you from the Zigaform team!', 'FRocket_admin' ), $notice_url, $notice_url );
                $button_content = esc_html__( "Rate ZigaForm", "FRocket_admin" );
            }else{
                $notice_heading = esc_html__( "Thanks for using zigaform!", "FRocket_admin" );
                $notice_url  = 'https://codecanyon.net/item/zigaform-wordpress-calculator-cost-estimation-form-builder/reviews/13663682';
                $notice_content = sprintf( __( 'Please rate <strong>Zigaform</strong> <a href="%s" target="_blank" rel="noopener" >&#9733;&#9733;&#9733;&#9733;&#9733;</a> on <a href="%s" target="_blank">Codecanyon.net</a> to help us spread the word. Thank you from the Zigaform team!', 'FRocket_admin' ), $notice_url, $notice_url );
                $button_content = esc_html__( "Rate ZigaForm", "FRocket_admin" );
            }
            
                
        }
        
        ?>
        <div class="notice zgfm-ext-notice" style="display: none;">
                <div class="zgfm-ext-notice-logo"><span></span></div>
                <div
                        class="zgfm-ext-notice-message<?php echo 'new' == $install_type ? ' zgfm-builder-fresh' : ' zgfm-builder-existing'; ?>">
                        <strong><?php echo $notice_heading; ?></strong>
                        <?php echo $notice_content; ?>
                </div>
                <div class="zgfm-ext-notice-cta">
                        <a href="<?php echo esc_url( $notice_url ); ?>" class="zgfm-ext-notice-act button-primary" target="_blank">
                        <?php echo $button_content; ?>
                        </a>
                        <button class="zgfm-ext-notice-dismiss zgfm-dismiss-welcome" data-msg="<?php esc_html_e( 'Saving', 'FRocket_admin'); ?>"><?php esc_html_e( 'Dismiss', "FRocket_admin" ); ?></button>
                </div>
        </div><?php
        //Notice CSS
        wp_register_style( 'zgfm-style-global-css', UIFORM_FORMS_URL . '/assets/backend/css/global-ext.css', array(), UIFORM_VERSION );
        //Notice CSS
        wp_enqueue_style('zgfm-style-global-css');
        
        //Notice JS
        wp_register_script( 'zgfm-script-global-js', UIFORM_FORMS_URL . '/assets/backend/js/global-ext.js', array(
                'jquery'
        ), UIFORM_VERSION );
        
        //Notice JS
        wp_enqueue_script('zgfm-script-global-js', '', array(), '', true );
        
        
    }
    
    
    public function plugin_add_links($links, $file) {
                
		if (is_array($links) && (strpos($file, "zigaform-cost-estimator-lite.php") !== false)) {
			$settings_link = '<a href="'.admin_url('admin.php').'?page=zgfm_cost_estimate">'.__("Settings", "FRocket_admin").'</a>';
			array_unshift($links, $settings_link);
			$settings_link = '<a style="color: #3db634" target="_blank" href="https://wordpress-cost-estimator.zigaform.com/">'.__("Add-Ons / Pro Support", "FRocket_admin").'</a>';
			array_unshift($links, $settings_link);
		} elseif (is_array($links) && (strpos($file, "zigaform-wp-estimator.php") !== false)) {
                        $settings_link = '<a href="'.admin_url('admin.php').'?page=zgfm_cost_estimate">'.__("Settings", "FRocket_admin").'</a>';
			array_unshift($links, $settings_link);
                        $settings_link = '<b><a style="color: #3db634" target="_blank" href="https://wordpress-cost-estimator.zigaform.com/#contact">'.__("Support", "FRocket_admin").'</a></b>';
			array_unshift($links, $settings_link);
                } else {
            
                }
		return $links;
   }
    
      /**
     * add class to body
     *
     * @access public
     * @since 1.0.0
     * @return void
     */
    public function filter_body_class($classes) {
            $classes[] = 'sfdc-wrap';
            return $classes;
        
    }
    
    /**
     * add_endpoint function.
     *
     * @access public
     * @since 1.0.0
     * @return void
     */
    public function add_endpoint() {
        //assigning variable to rewrite
        add_rewrite_endpoint('uifm_costestimator_api_handler', EP_ALL);
        
    }
    
    /**
     * API request - Trigger any API requests
     *
     * @access public
     * @since 1.0.0
     * @return void
     */
    public function handle_api_requests() {
        global $wp;
        if (isset($_GET['zgfm_action']) && $_GET['zgfm_action'] == 'uifm_est_api_handler') {
            $wp->query_vars['uifm_costestimator_api_handler'] = $_GET['zgfm_action'];
        }

        // paypal-ipn-for-wordpress-api endpoint requests
        if (!empty($wp->query_vars['uifm_costestimator_api_handler'])) {

            // Buffer, we won't want any output here
            ob_start();

            // Get API trigger
            $api = $this->route_api_handler();
            // Trigger actions
            do_action('uifm_costestimator_api_' . $api);

            // Done, clear buffer and exit
            ob_end_clean();
            die('1');
        }
    }
    
    private function route_api_handler() {
      
        $mode=isset($_GET['uifm_mode']) ? Uiform_Form_Helper::sanitizeInput($_GET['uifm_mode']) :'';
        $return='';
        switch ($mode) {
            case 'ipn':
                $type_payment=isset($_GET['paygat']) ? Uiform_Form_Helper::sanitizeInput($_GET['paygat']) :'';
                switch ($type_payment) {
                    case 2:
                        $return='paypal_ipn_handler';
                        break;
                    default:
                        break;
                }
                break;
            case 'lmode':
                $type_mode=isset($_GET['uifm_action']) ? Uiform_Form_Helper::sanitizeInput($_GET['uifm_action']) :'';
                switch ($type_mode) {
                    case 1:
                        $return='lmode_iframe_handler';
                        break;
                    default:
                        break;
                }
                break;
            case 'pdf':
                $process=isset($_GET['uifm_action']) ? Uiform_Form_Helper::sanitizeInput($_GET['uifm_action']) :'';
                switch ($process) {
                    case 'show_record':
                        $return='pdf_show_record';
                        break;
                    case 'show_invoice':
                        $return='pdf_show_invoice';
                        break;
                    default:
                        break;
                };
                break;
            case 'csv':
                $process=isset($_GET['uifm_action']) ? Uiform_Form_Helper::sanitizeInput($_GET['uifm_action']) :'';
                switch ($process) {
                    case 'show_allrecords':
                        $return='csv_show_allrecords';
                        break;
                    default:
                        break;
                };
                break;
            default:
                break;
        }
        
        return $return;
    }
    
    public function action_pdf_show_invoice() {
      self::$_modules['formbuilder']['frontend']->pdf_show_invoice();
    }
    
    public function action_pdf_show_record() {
         self::$_modules['formbuilder']['frontend']->pdf_show_record();
    }
    
    public function action_csv_show_allrecords() {
       
       $form_id=isset($_GET['id']) ? Uiform_Form_Helper::sanitizeInput($_GET['id']) :'';
       
       self::$_modules['formbuilder']['records']->csv_showAllForms($form_id);
       
       die();
    }
    
    public function paypal_ipn_handler() {
        require_once( UIFORM_FORMS_DIR . '/modules/gateways/controllers/uiform-pg-paypal.php');
        $paypal=Uiform_Pg_Paypal::get_instance();
        $paypal->ipn();
        die();
    }
    
    public function lmode_iframe_handler() {
        $form_id=isset($_GET['id']) ? Uiform_Form_Helper::sanitizeInput($_GET['id']) :'';
        //removing actions
        remove_all_actions('wp_footer');
        remove_all_actions('wp_head');
        
        echo $this->modules['formbuilder']['frontend']->get_form_iframe($form_id);
        
        die();
    }
    
    function disable_plugin_updates( $value ) {
        if(isset($value->response['uiform-form-builder/uiform-form-builder.php'])){
            unset( $value->response['uiform-form-builder/uiform-form-builder.php'] );
        }
        return $value;
     }
     
    public function remove_unwanted_css(){
       /*
        //style
        wp_dequeue_style( 'bootstrap_css' );
        wp_deregister_style( 'bootstrap_css' );
        
        //script
        wp_dequeue_script( 'bootstrap.min_script' );*/
    } 
                    
    public function rockfm_lang_dir_filter($lang_dir) {
        if (is_admin() && Uiform_Form_Helper::is_uiform_page()) {
            $lang_dir = UIFORM_FORMS_DIR . '/i18n/languages/backend/';
        } else {
            //load frontend
            $lang_dir = UIFORM_FORMS_DIR . '/i18n/languages/front/';
        }
        return $lang_dir;
    }

    public function rockfm_lang_locale_filter($locale) {

        $tmp_lang = $this->models['formbuilder']['settings']->getLangOptions();
        if (!empty($tmp_lang->language)) {
            $locale = $tmp_lang->language;
        }

        return $locale;
    }

    public function rockfm_lang_domain_filter($domain) {
        if (is_admin() && Uiform_Form_Helper::is_uiform_page()) {
            $domain = 'FRocket_admin';
        } else {
            //load frontend
            $domain = 'FRocket_front';
        }
        return $domain;
    }

    function uiform_process_form() {
        $this->modules['formbuilder']['frontend']->process_form();
    }
    
    function my_external_plugins ($plugin_array) {
         $plugin_array['fullpage'] = UIFORM_FORMS_URL.'/assets/backend/js/tinymce/plugins/fullpage/plugin-4.0.js';
        return $plugin_array;
   }
    
    function wpver411_tiny_mce_before_init($initArray) {
       
        $initArray['plugins'] = 'tabfocus,paste,media,wpeditimage,wpgallery,wplink,wpdialogs,fullpage';
        $initArray['wpautop'] = true;
        
        $initArray['cleanup_on_startup'] = false;
        $initArray['trim_span_elements'] = false;
        $initArray['verify_html' ] = FALSE;
        $initArray['fix_table_elements' ] = FALSE;
        $initArray['cleanup'] = false;
        $initArray['convert_urls'] = false;
        
        $initArray["forced_root_block"] = false;
        $initArray["force_br_newlines"] = false;
        $initArray["force_p_newlines"] = false;
        $initArray["convert_newlines_to_brs"] = false;
        $initArray['apply_source_formatting'] = false;
        $initArray['theme_advanced_buttons1'] = 'formatselect,forecolor,|,bold,italic,underline,|,bullist,numlist,blockquote,|,justifyleft,justifycenter,justifyright,justifyfull,|,link,unlink,|,wp_adv';
        $initArray['theme_advanced_buttons2'] = 'fontsizeselect,pastetext,pasteword,removeformat,|,charmap,|,outdent,indent,|,undo,redo';
        $initArray['theme_advanced_buttons3'] = '';
        $initArray['theme_advanced_buttons4'] = '';
        $initArray['fontsize_formats'] = "7px 9px 10px 11px 12px 13px 14px 15px 16px 17px 18px 19px 20px 21px 22px 23px 24px 25px 26px 27px 28px 29px 30px 31px 32px 34px 36px 45px";
        // html elements being stripped
        $initArray['extended_valid_elements'] = '*[*]';
        $initArray['valid_elements'] = '*[*]';
        $initArray['valid_children'] = "+head[style],+body[meta],+div[h2|span|meta|object],+object[param|embed]";
           // don't remove line breaks
        $initArray['remove_linebreaks'] = false;

        // convert newline characters to BR
        $initArray['convert_newlines_to_brs'] = true;

        // don't remove redundant BR
        $initArray['remove_redundant_brs'] = false;
        
        
        
        $initArray['setup'] = <<<JS
[function(ed) {
      ed.on('change KeyUp', function(e) {
         rocketform.captureEventTinyMCE(ed,e);
      });
    ed.on('BeforeSetContent', function (e) {
        
    });
}][0]
JS;
        return $initArray;
    }

    function wpse24113_tiny_mce_before_init($initArray) {
        $initArray['plugins'] = 'tabfocus,paste,media,wpeditimage,wpgallery,wplink,wpdialogs';
        $initArray['wpautop'] = true;
        $initArray['verify_html' ] = FALSE;
        $initArray["forced_root_block"] = false;
        $initArray["force_br_newlines"] = true;
        $initArray["force_p_newlines"] = false;
        $initArray["convert_newlines_to_brs"] = true;
        $initArray['apply_source_formatting'] = true;
        $initArray['theme_advanced_buttons1'] = 'formatselect,forecolor,|,bold,italic,underline,|,bullist,numlist,blockquote,|,justifyleft,justifycenter,justifyright,justifyfull,|,link,unlink,|,wp_adv';
        $initArray['theme_advanced_buttons2'] = 'fontsizeselect,pastetext,pasteword,removeformat,|,charmap,|,outdent,indent,|,undo,redo';
        $initArray['theme_advanced_buttons3'] = '';
        $initArray['theme_advanced_buttons4'] = '';
        $initArray['fontsize_formats'] = "7px 9px 10px 11px 12px 13px 14px 15px 16px 17px 18px 19px 20px 21px 22px 23px 24px 25px 26px 27px 28px 29px 30px 31px 32px 34px 36px 45px";
        // html elements being stripped
        $initArray['extended_valid_elements'] = '*[*]';
        $initArray['valid_elements'] = '*[*]';
        $initArray['valid_children'] = "+head[style],+body[meta],+div[h2|span|meta|object],+object[param|embed]";
           // don't remove line breaks
        $initArray['remove_linebreaks'] = false;

        // convert newline characters to BR
        $initArray['convert_newlines_to_brs'] = true;

        // don't remove redundant BR
        $initArray['remove_redundant_brs'] = false;
        $initArray['setup'] = <<<JS
[function(ed) {
    ed.onKeyUp.add(function(ed, e) {
        rocketform.captureEventTinyMCE(ed,e);
    });
    ed.onClick.add(function(ed, e) {
        rocketform.captureEventTinyMCE(ed,e);
        });
    ed.onChange.add(function(ed, e) {
        rocketform.captureEventTinyMCE(ed,e);
    });
}][0]
JS;
        return $initArray;
    }
                    
    protected function loadBackendControllers() {
        
        //addon
        require_once( UIFORM_FORMS_DIR . '/modules/addon/controllers/backend.php');
        require_once( UIFORM_FORMS_DIR . '/modules/addon/controllers/common.php');
        require_once( UIFORM_FORMS_DIR . '/modules/addon/controllers/frontend.php');
        require_once( UIFORM_FORMS_DIR . '/modules/addon/models/addon.php');
        require_once( UIFORM_FORMS_DIR . '/modules/addon/models/addon_details.php');
        require_once( UIFORM_FORMS_DIR . '/modules/addon/models/addon_details_log.php');
        
        //default
        require_once( UIFORM_FORMS_DIR . '/modules/default/controllers/backend.php');
        
        //form builder            
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/controllers/uiform-fb-controller-forms.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/controllers/uiform-fb-controller-fields.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/models/uiform-model-form.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/models/uiform-model-form-log.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/models/uiform-model-fields.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/models/uiform-model-form-records.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/models/uiform-model-settings.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/controllers/uiform-fb-controller-frontend.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/controllers/uiform-fb-controller-records.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/controllers/uiform-fb-controller-settings.php');
        //gateway
        require_once( UIFORM_FORMS_DIR . '/modules/gateways/controllers/uiform-pg-controller-settings.php');
        require_once( UIFORM_FORMS_DIR . '/modules/gateways/controllers/uiform-pg-controller-records.php');
        require_once( UIFORM_FORMS_DIR . '/modules/gateways/models/uiform-model-gateways.php');
        require_once( UIFORM_FORMS_DIR . '/modules/gateways/models/uiform-model-gateways-records.php');
        require_once( UIFORM_FORMS_DIR . '/modules/gateways/models/uiform-model-gateways-logs.php');
        
        //visitor
        require_once( UIFORM_FORMS_DIR . '/modules/visitor/models/uiform-model-error.php');
        require_once( UIFORM_FORMS_DIR . '/modules/visitor/models/uiform-model-visitor.php');
        
        
        
        $this->models = array(
            'formbuilder' => array('form' => new Uiform_Model_Form(),
                'fields' => new Uiform_Model_Fields(),
                'form_log' => new Uiform_Model_Form_Log(),
                'settings' => new Uiform_Model_Settings(),
                'form_records' => new Uiform_Model_Form_Records()),
            'visitor' => array('visitor' => new Uiform_Model_Visitor(),
                'error' => new Uiform_Model_Visitor_Error()),
            'gateways' => array('gateways' => new Uiform_Model_Gateways(),
                                'records' => new Uiform_Model_Gateways_Records(),
                                'logs' => new Uiform_Model_Gateways_Logs()),
             'addon' => array('addon' => new Uiform_Model_Addon(),
                              'addon_details' => new Uiform_Model_Addon_Details(),
                              'addon_details_log' => new Uiform_Model_Addon_Details_Log() )   
                                
        );
        self::$_models = $this->models;
        
        $this->modules = array(
            'default' => array('backend' => Uiform_Fb_Default_Controller_Back::get_instance()),
            'formbuilder' => array('forms' => Uiform_Fb_Controller_Forms::get_instance(),
                'fields' => Uiform_Fb_Controller_Fields::get_instance(),
                'frontend' => Uiform_Fb_Controller_Frontend::get_instance(),
                'records' => Uiform_Fb_Controller_Records::get_instance(),
                'settings' => Uiform_Fb_Controller_Settings::get_instance()),
            'gateways' => array('settings' => Uiform_Pg_Controller_Settings::get_instance(),
                                'records' => Uiform_Pg_Controller_Records::get_instance()),
            'addon' => array('common' => zgfm_mod_addon_controller_common::get_instance(),
                'backend' => zgfm_mod_addon_controller_back::get_instance(),
                'frontend' => zgfm_mod_addon_controller_front::get_instance())
        );
        self::$_modules = $this->modules;
                    
        //add new modules
        $this->modules['addon']['backend']->load_addonsbyBack();
                    
        //add addon routes
        $this->modules['addon']['backend']->load_addRoutes();
        
        
    }

    protected function loadFrontendControllers() {
        //default
        require_once( UIFORM_FORMS_DIR . '/modules/default/controllers/backend.php');
        
        //form builder
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/controllers/uiform-fb-controller-frontend.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/controllers/uiform-fb-controller-records.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/models/uiform-model-form.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/models/uiform-model-fields.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/models/uiform-model-settings.php');
        require_once( UIFORM_FORMS_DIR . '/modules/formbuilder/models/uiform-model-form-records.php');
        //gateway
        require_once( UIFORM_FORMS_DIR . '/modules/gateways/models/uiform-model-gateways.php');
        require_once( UIFORM_FORMS_DIR . '/modules/gateways/models/uiform-model-gateways-records.php');
        require_once( UIFORM_FORMS_DIR . '/modules/gateways/models/uiform-model-gateways-logs.php');
        //visitor
        require_once( UIFORM_FORMS_DIR . '/modules/visitor/models/uiform-model-error.php');
        require_once( UIFORM_FORMS_DIR . '/modules/visitor/models/uiform-model-visitor.php');
        
        //addon
        //addon
        require_once( UIFORM_FORMS_DIR . '/modules/addon/controllers/frontend.php');
        require_once( UIFORM_FORMS_DIR . '/modules/addon/models/addon.php');
        require_once( UIFORM_FORMS_DIR . '/modules/addon/models/addon_details.php');
                    
        $this->models = array(
            'formbuilder' => array('form' => new Uiform_Model_Form(),
                'fields' => new Uiform_Model_Fields(),
                'settings' => new Uiform_Model_Settings(),
                'form_records' => new Uiform_Model_Form_Records()),
            'visitor' => array('visitor' => new Uiform_Model_Visitor(),
                'error' => new Uiform_Model_Visitor_Error()),
            'gateways' => array('gateways' => new Uiform_Model_Gateways(),
                                'records' => new Uiform_Model_Gateways_Records(),
                                'logs' => new Uiform_Model_Gateways_Logs()),
             'addon' => array('addon' => new Uiform_Model_Addon(),
                              'addon_details' => new Uiform_Model_Addon_Details() )   
        );
        self::$_models = $this->models;
        $this->modules = array(
            'default' => array('backend' => Uiform_Fb_Default_Controller_Back::get_instance()),
            'formbuilder' => array('frontend' => Uiform_Fb_Controller_Frontend::get_instance(),
                                    'records' => Uiform_Fb_Controller_Records::get_instance()),
            'addon' => array('frontend' => zgfm_mod_addon_controller_front::get_instance())
        );
        self::$_modules = $this->modules;
        
        //add new modules
        $this->modules['addon']['frontend']->load_addonsbyFront();
                    
        //add addon routes
        //$this->modules['addon']['frontend']->load_addRoutes();
    }

    public function wphidenag() {
        remove_action('admin_notices', 'update_nag', 3);
    }

    
    
    /**
     *  Redirects the clicked menu item to the correct location
     *
     * @return null
     */
    public function get_menu(){
        $current_page = isset($_REQUEST['page']) ? esc_html($_REQUEST['page']) : 'zgfm_cost_estimate';
                    
        switch ($current_page) 
		{
                case 'zgfm_cost_estimate': $this->route_page(); break;
                case 'zigaform-cost-help':	include(dirname(__DIR__) . '/views/help/help.php');			break;
                case 'zigaform-cost-about':	include(dirname(__DIR__) . '/views/help/about.php');			break; 
                case 'zigaform-cost-debug':	include(dirname(__DIR__) . '/views/help/debug.php');			break;
                case 'zigaform-cost-gopro':	include(dirname(__DIR__) . '/views/help/gopro.php');			break;
                default:
                    break;
                }
    }
    
     /**
     *  Hooked into `admin_menu`
     *
     * @return null
     */
    public function loadMenu() {

        if(ZIGAFORM_C_LITE == 1){
            add_menu_page('ZigaForm - Cost Estimator lite', 'ZigaForm Estimator Lite', "edit_posts", "zgfm_cost_estimate", array(&$this, "get_menu"), UIFORM_FORMS_URL . "/assets/backend/image/rockfm-logo-ico.png");
        }else{
            add_menu_page('ZigaForm - Cost Estimator', 'ZigaForm Estimator', "edit_posts", "zgfm_cost_estimate", array(&$this, "get_menu"), UIFORM_FORMS_URL . "/assets/backend/image/rockfm-logo-ico.png");
        }
        
        //add_submenu_page("zgfm_cost_estimate", __('Forms', 'FRocket_admin'), __('Forms', 'FRocket_admin'), $perms, '?page=zgfm_cost_estimate&zgfm_mod=formbuilder&zgfm_contr=records&zgfm_action=info_records_byforms');
        
        $perms = 'manage_options';    
        add_submenu_page("zgfm_cost_estimate", __('Forms', 'FRocket_admin'), __('Forms', 'FRocket_admin'), $perms, "zgfm_cost_estimate", array(&$this, "get_menu"));
        $page_help = add_submenu_page("zgfm_cost_estimate", __('Help', 'FRocket_admin'), __('Help', 'FRocket_admin'), $perms, "zigaform-cost-help", array(&$this, "get_menu"));
        $page_about = add_submenu_page("zgfm_cost_estimate", __('About', 'FRocket_admin'), __('About', 'FRocket_admin'), $perms, "zigaform-cost-about", array(&$this, "get_menu"));
        
        if (UIFORM_DEBUG == 1) {
         $page_debug = add_submenu_page("zgfm_cost_estimate", __('Debug', 'FRocket_admin'), __('Debug', 'FRocket_admin'), $perms, "zigaform-cost-debug", array(&$this, "get_menu"));
         add_action('admin_print_styles-' . $page_debug, array(&$this, "load_admin_resources"));       
        }
        
        if(ZIGAFORM_C_LITE == 1){
          //go pro page
            $submenu_txt = __('Go Pro!', 'FRocket_admin');
            $go_pro_link = '<span style="color:#f18500">' . $submenu_txt . '</span>';
            $page_gopro = add_submenu_page("zgfm_cost_estimate", $go_pro_link, $go_pro_link, $perms, "zigaform-cost-gopro", array(&$this, "get_menu"));  
            add_action('admin_print_styles-' . $page_gopro, array(&$this, "load_admin_resources"));
        }
        
        
        //load styles
        add_action('admin_print_styles-' . $page_help, array(&$this, "load_admin_resources"));
        add_action('admin_print_styles-' . $page_about, array(&$this, "load_admin_resources"));
        
        add_filter("plugin_row_meta", array(&$this, 'get_extra_meta_links'), 10, 4);
        add_action('admin_head', array($this, 'add_star_styles')); 
    }
    
    /**
     * Adds extra links to the plugin activation page
     *
     * @param  array  $meta   Extra meta links
     * @param  string $file   Specific file to compare against the base plugin
     * @param  string $data   Data for the meat links
     * @param  string $status Staus of the meta links
     * @return array          Return the meta links array
     */
    public function get_extra_meta_links($meta, $file, $data, $status) {
                
            if(ZIGAFORM_C_LITE===1){
                $pos_coincidencia = strpos($file,'zigaform-cost-estimator-lite.php');
            }else{
                $pos_coincidencia = strpos($file,'zigaform-wp-estimator.php');
            }
        
          if ($pos_coincidencia !== false) {
                $plugin_page = admin_url('admin.php?page=zgfm_form_builder');
                $meta[] = "<a href='https://wordpress-cost-estimator.zigaform.com//#contact' target='_blank'><span class='dashicons  dashicons-admin-users'></span>" . __('Support', 'FRocket_admin') . "</a>";
            if (ZIGAFORM_C_LITE===1) {
                $meta[] = "<a href='https://codecanyon.net/item/zigaform-wordpress-calculator-cost-estimation-form-builder/13663682?ref=Softdiscover' target='_blank'><span class='dashicons  dashicons-cart'></span>" . __('Get Premium', 'FRocket_admin') . "</a>";
               // $meta[] = "<a href='https://wordpress.org/support/plugin/zigaform-form-builder-lite/' target='_blank'>" . __('Support', 'FRocket_admin') . "</a>";
            }
            
            $meta[] = "<a href='https://wordpress-cost-estimator.zigaform.com/#demo-samples' target='_blank'><span class='dashicons  dashicons-laptop'></span>" . __('Live Demo', 'FRocket_admin') . "</a>";
            $meta[] = "<a href='https://kb.softdiscover.com/docs/zigaform-wordpress-cost-estimator/' target='_blank'><span class='dashicons  dashicons-search'></span>" . __('Documentation', 'FRocket_admin') . "</a>";
            
            
            if(ZIGAFORM_C_LITE===1){
                $meta[] = "<a href='https://wordpress.org/plugins/zigaform-calculator-cost-estimation-form-builder-lite/reviews#new-post' target='_blank' title='" . __('Leave a review', 'FRocket_admin') . "'><i class='ml-stars'><svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg><svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg><svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg><svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg><svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg></i></a>";
            }else{
                $meta[] = "<a href='https://codecanyon.net/item/zigaform-wordpress-calculator-cost-estimation-form-builder/reviews/13663682' target='_blank' title='" . __('Leave a review', 'FRocket_admin') . "'><i class='ml-stars'><svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg><svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg><svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg><svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg><svg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' class='feather feather-star'><polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2'/></svg></i></a>";
            }
            
          } 
                
        return $meta;
    }
    
    /**
    * Adds styles to admin head to allow for stars animation and coloring
    */
    public function add_star_styles() {
        if (Uiform_Form_Helper::zigaform_user_is_on_admin_page('plugins.php')) {?>
            <style>
                .ml-stars{display:inline-block;color:#ffb900;position:relative;top:3px}
                .ml-stars svg{fill:#ffb900}
                .ml-stars svg:hover{fill:#ffb900}
                .ml-stars svg:hover ~ svg{fill:none}
            </style>
    <?php }
    }
    
    public function route_page() {

        $route = Uiform_Form_Helper::getroute();
        if (!empty($route['module']) && !empty($route['controller']) && !empty($route['action'])) {
            if ( isset($this->modules[$route['module']][$route['controller']])
                    && method_exists($this->modules[$route['module']][$route['controller']], $route['action'])) {
                    
                //this call function work in php7 too
                call_user_func(array($this->modules[$route['module']][$route['controller']],$route['action']));
                
            } else {
                echo 'wrong url';
               
            }
        } else {
            $this->modules['formbuilder']['forms']->list_uiforms();
        }
    }

    /*
     * Static methods
     */

    /**
     * Enqueues CSS, JavaScript, etc
     *
     * @mvc Controller
     */
    public static function load_admin_resources() {
        //admin
        /*wp_register_script(
                self::PREFIX . 'rockfm_js_global_frontend', UIFORM_FORMS_URL . '/assets/backend/js/js_global_frontend.php', array(), UIFORM_VERSION, true
        );*/
        /*wp_register_script(
                self::PREFIX . 'uifm_js_recaptcha', 'https://www.google.com/recaptcha/api.js?render=explicit', array(), 1, true
        );*/
        
       
                   
          
        
        
        if(UIFORM_DEV===1){
            wp_enqueue_style('rockefform-dev-css-1', UIFORM_FORMS_URL . '/assets/common/bootstrap/3.3.7/css/bootstrap-sfdc.css');
            wp_enqueue_style('rockefform-dev-css-2', UIFORM_FORMS_URL . '/assets/common/bootstrap/3.3.7/css/bootstrap-theme-sfdc.css');
            wp_enqueue_style('rockefform-dev-css-3', UIFORM_FORMS_URL . '/assets/common/bootstrap/3.3.7/css/dropdown-sfdc.css');
            wp_enqueue_style('rockefform-dev-css-4', UIFORM_FORMS_URL . '/assets/common/bootstrap/3.3.7/css/modals-sfdc.css');
            wp_enqueue_style('rockefform-dev-css-5', UIFORM_FORMS_URL . '/assets/common/bootstrap/3.3.7/css/popovers-sfdc.css');
            wp_enqueue_style('rockefform-dev-css-6', UIFORM_FORMS_URL . '/assets/common/bootstrap/3.3.7/css/tooltip-sfdc.css');
            wp_enqueue_style('rockefform-dev-css-7', UIFORM_FORMS_URL . '/assets/backend/css/style.css');
            wp_enqueue_style('rockefform-dev-css-8', UIFORM_FORMS_URL . '/assets/backend/css/style-cost.css');
            wp_enqueue_style('rockefform-dev-css-9', UIFORM_FORMS_URL . '/assets/backend/css/global.css');
            wp_enqueue_style('rockefform-dev-css-10', UIFORM_FORMS_URL . '/assets/backend/css/shortcuts.css');
            wp_enqueue_style('rockefform-dev-css-11', UIFORM_FORMS_URL . '/assets/backend/css/shortcuts_intranet.css');
            wp_enqueue_style('rockefform-dev-css-12', UIFORM_FORMS_URL . '/assets/backend/css/uiform-textbox.css');
            wp_enqueue_style('rockefform-dev-css-13', UIFORM_FORMS_URL . '/assets/backend/css/responsive.css');
            wp_enqueue_style('rockefform-dev-css-14', UIFORM_FORMS_URL . '/assets/frontend/css/global.css');
            wp_enqueue_style('rockefform-dev-css-15', UIFORM_FORMS_URL . '/assets/common/css/custom.css');
            wp_enqueue_style('rockefform-dev-css-16', UIFORM_FORMS_URL . '/assets/common/css/custom2.css');
            wp_enqueue_style('rockefform-dev-css-17', UIFORM_FORMS_URL . '/assets/common/css/global-form.css');
            wp_enqueue_style('rockefform-dev-css-18', UIFORM_FORMS_URL . '/assets/backend/css/deprecated.css');
            wp_enqueue_style('rockefform-dev-css-19', UIFORM_FORMS_URL . '/assets/common/js/dcheckbox/uiform-dcheckbox.css');
            wp_enqueue_style('rockefform-dev-css-20', UIFORM_FORMS_URL . '/assets/common/js/dcheckbox/uiform-dradiobtn.css');
            wp_enqueue_style('rockefform-dev-css-21', UIFORM_FORMS_URL . '/assets/backend/css/extra.css');
            wp_enqueue_style('rockefform-dev-css-22', UIFORM_FORMS_URL . '/assets/backend/css/zigabuilder-grid-style.css');
            wp_enqueue_style('rockefform-dev-css-23', UIFORM_FORMS_URL . '/assets/backend/css/style-universal.css');
     
        }else{
            wp_register_style(
                self::PREFIX . 'admin', UIFORM_FORMS_URL . '/assets/backend/css/admin-css.css', array(), UIFORM_VERSION, 'all'
            );
        }
        
        
        
        



        
            
            global $wp_scripts;
            $jquery_ui_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.11.4';
            /* load css */
            //loas ui
            
            switch($jquery_ui_version){
                    case "1.11.4":
                        wp_register_style('jquery-ui-style', UIFORM_FORMS_URL . '/temp/jqueryui/1.11.4/themes/start/jquery-ui.min.css', array(), $jquery_ui_version);
                        wp_enqueue_style( 'jquery-ui-style');
                        break;
                    case "1.10.4":
                        wp_register_style('jquery-ui-style', UIFORM_FORMS_URL . '/temp/jqueryui/1.10.4/themes/start/jquery-ui.min.css', array(), $jquery_ui_version);
                        wp_enqueue_style( 'jquery-ui-style');
                        break;
                    default:
                        wp_enqueue_style('jquery-ui'); 
                        wp_enqueue_style('wp-jquery-ui-dialog' );
                }
            //bootstrap
            wp_enqueue_style('rockefform-bootstrap', UIFORM_FORMS_URL . '/assets/common/bootstrap/3.3.7/css/bootstrap-wrapper.css');
            wp_enqueue_style('rockefform-bootstrap-theme', UIFORM_FORMS_URL . '/assets/common/bootstrap/3.3.7/css/bootstrap-theme-wrapper.css'); 
            
            //sfdc bootstrap
            //wp_enqueue_style('sfdcgb-bootstrap', UIFORM_FORMS_URL . '/assets/common/js/bootstrap/3.2.0-sfdc/bootstrap-widget.css');
            //wp_enqueue_style('sfdcgb-bootstrap-theme', UIFORM_FORMS_URL . '/assets/common/js/bootstrap/3.2.0-sfdc/bootstrap-theme-widget.css');
                    
            wp_enqueue_style('rockefform-fontawesome', UIFORM_FORMS_URL . '/assets/common/css/fontawesome/4.7.0/css/font-awesome.min.css');
            
            //custom fonts
            wp_enqueue_style('rockefform-customfonts', UIFORM_FORMS_URL . '/assets/backend/css/custom/style.css');
            //animate
            wp_enqueue_style('rockefform-animate', UIFORM_FORMS_URL . '/assets/backend/css/animate.css');
            //jasny bootstrap
            wp_enqueue_style('rockefform-jasny-bootstrap', UIFORM_FORMS_URL . '/assets/common/js/bjasny/jasny-bootstrap.css');
            //jscrollpane
            wp_enqueue_style('rockefform-jscrollpane', UIFORM_FORMS_URL . '/assets/backend/js/jscrollpane/jquery.jscrollpane.css');
            wp_enqueue_style('rockefform-jscrollpane-lozenge', UIFORM_FORMS_URL . '/assets/backend/js/jscrollpane/jquery.jscrollpane.lozenge.css');
            //chosen
            wp_enqueue_style('rockefform-chosen', UIFORM_FORMS_URL . '/assets/backend/js/chosen/chosen.css');
            wp_enqueue_style('rockefform-chosen-style', UIFORM_FORMS_URL . '/assets/backend/js/chosen/style.css');
            //color picker
            wp_enqueue_style('rockefform-bootstrap-colorpicker', UIFORM_FORMS_URL . '/assets/backend/js/colorpicker/2.5/css/bootstrap-colorpicker.css');
            // bootstrap select
            wp_enqueue_style('rockefform-bootstrap-select', UIFORM_FORMS_URL . '/assets/common/js/bselect/1.12.4/css/bootstrap-select-mod.css');
            // bootstrap switch
            wp_enqueue_style('rockefform-bootstrap-switch', UIFORM_FORMS_URL . '/assets/backend/js/bswitch/bootstrap-switch.css');
            // bootstrap slider
            wp_enqueue_style('rockefform-bootstrap-slider', UIFORM_FORMS_URL . '/assets/backend/js/bslider/4.12.1/bootstrap-slider.min.css');
            // bootstrap touchspin
            wp_enqueue_style('rockefform-bootstrap-touchspin', UIFORM_FORMS_URL . '/assets/backend/js/btouchspin/jquery.bootstrap-touchspin.css');
            // bootstrap iconpicker
            wp_enqueue_style('rockefform-bootstrap-iconpicker', UIFORM_FORMS_URL . '/assets/backend/js/biconpicker/1.9.0/css/bootstrap-iconpicker.css');
            // bootstrap datetimepicker
            wp_enqueue_style('rockefform-bootstrap-datetimepicker', UIFORM_FORMS_URL . '/assets/backend/js/bdatetime/4.7.14/bootstrap-datetimepicker.css');
            
            // bootstrap datetimepicker2
            wp_enqueue_style('rockefform-bootstrap-datetimepicker2', UIFORM_FORMS_URL . '/assets/common/js/flatpickr/4.5.2/flatpickr.min.css');
            
            // star rating
            wp_enqueue_style('rockefform-star-rating', UIFORM_FORMS_URL . '/assets/backend/js/bratestar/star-rating.css');
            //datatable
            wp_enqueue_style('rockefform-dataTables', UIFORM_FORMS_URL . '/assets/backend/js/bdatatable/1.10.9/jquery.dataTables.css');
            //graph
            wp_enqueue_style('rockefform-graph-morris', UIFORM_FORMS_URL . '/assets/backend/js/graph/morris.css');
            //intro 
            wp_enqueue_style('rockefform-introjs', UIFORM_FORMS_URL . '/assets/backend/js/introjs/introjs.css');
            //blueimp
            wp_enqueue_style('rockefform-blueimp', UIFORM_FORMS_URL . '/assets/common/js/blueimp/2.16.0/css/blueimp-gallery.min.css');
            //bootstrap gallery
            wp_enqueue_style('rockefform-bootstrap-gal', UIFORM_FORMS_URL . '/assets/common/js/bgallery/3.1.3/css/bootstrap-image-gallery.css');
            
            //checkradio
            wp_enqueue_style('rockefform-checkradio', UIFORM_FORMS_URL . '/assets/common/js/checkradio/2.2.2/css/jquery.checkradios.css');
            
            //codemirror 
            wp_enqueue_style('rockefform-codemirror', UIFORM_FORMS_URL . '/assets/common/js/codemirror/codemirror.css');
            wp_enqueue_style('rockefform-codemirror-foldgutter', UIFORM_FORMS_URL . '/assets/common/js/codemirror/addon/fold/foldgutter.css');
            wp_enqueue_style('rockefform-codemirror-monokai', UIFORM_FORMS_URL . '/assets/common/js/codemirror/theme/monokai.css');
            
                    
            //load rocketform
            wp_enqueue_style(self::PREFIX . 'admin');


            /* load js */
            //load jquery
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-uiform-validation', UIFORM_FORMS_URL . '/assets/backend/js/jsvalidate/jquery.validate.min.js', array( 'jquery' ), '1.9.0', true );
            // load jquery ui
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-widget');
            wp_enqueue_script('jquery-ui-mouse');
            wp_enqueue_script("jquery-ui-dialog");
            wp_enqueue_script('jquery-ui-resizable');
            wp_enqueue_script('jquery-ui-position');
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('jquery-ui-draggable');
            wp_enqueue_script('jquery-ui-droppable');
            wp_enqueue_script('jquery-ui-accordion');
            wp_enqueue_script('jquery-ui-autocomplete');
            wp_enqueue_script('jquery-ui-menu');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_script('jquery-ui-slider');
            wp_enqueue_script('jquery-ui-spinner');
            wp_enqueue_script('jquery-ui-button');
            
            //prev jquery
            wp_enqueue_script('rockefform-prev-jquery', UIFORM_FORMS_URL . '/assets/common/js/init.js', array('jquery'));
            
            //bootstrap
            wp_enqueue_script('rockefform-bootstrap', UIFORM_FORMS_URL . '/assets/common/bootstrap/3.3.7/js/bootstrap.js', array('jquery','rockefform-prev-jquery'));
            
            //bootstrap sfdc
            wp_enqueue_script('rockefform-bootstrap-sfdc', UIFORM_FORMS_URL . '/assets/common/bootstrap/3.3.7/js/bootstrap-sfdc.js', array('jquery','rockefform-prev-jquery'));
            
            //jasny bootstrap
            wp_enqueue_script('rockefform-jasny-bootstrap', UIFORM_FORMS_URL . '/assets/common/js/bjasny/jasny-bootstrap.js', array('jquery', 'rockefform-bootstrap'), '1.0', true);
            
            //md5
            wp_enqueue_script('rockefform-md5', UIFORM_FORMS_URL . '/assets/backend/js/md5.js');
            //graph morris
            wp_enqueue_script('rockefform-graph-morris', UIFORM_FORMS_URL . '/assets/backend/js/graph/morris.min.js');
            wp_enqueue_script('rockefform-graph-raphael', UIFORM_FORMS_URL . '/assets/backend/js/graph/raphael-min.js');
            //retina
            wp_enqueue_script('rockefform-retina', UIFORM_FORMS_URL . '/assets/backend/js/retina.js');
            //jscrollpane
            wp_enqueue_script('rockefform-mousewheel', UIFORM_FORMS_URL . '/assets/backend/js/jscrollpane/jquery.mousewheel.js');
            wp_enqueue_script('rockefform-jscrollpane', UIFORM_FORMS_URL . '/assets/backend/js/jscrollpane/jquery.jscrollpane.min.js');
            //chosen
            wp_enqueue_script('rockefform-chosen', UIFORM_FORMS_URL . '/assets/backend/js/chosen/chosen.jquery.min.js');
            //color picker
            wp_enqueue_script('rockefform-bootstrap-colorpicker', UIFORM_FORMS_URL . '/assets/backend/js/colorpicker/2.5/js/bootstrap-colorpicker_mod.js', array('jquery', 'rockefform-bootstrap'), '1.0', true);
            //bootstrap select
            wp_enqueue_script('rockefform-bootstrap-select', UIFORM_FORMS_URL . '/assets/common/js/bselect/1.12.4/js/bootstrap-select-mod.js', array('jquery', 'rockefform-bootstrap'), '1.12.4', true);
            //bootstrap switch
            wp_enqueue_script('rockefform-bootstrap-switch', UIFORM_FORMS_URL . '/assets/backend/js/bswitch/bootstrap-switch.js', array('jquery', 'rockefform-bootstrap'), '1.0', true);
            //bootstrap slider
            wp_enqueue_script('rockefform-bootstrap-slider', UIFORM_FORMS_URL . '/assets/backend/js/bslider/4.12.1/bootstrap-slider.js', array('jquery', 'rockefform-bootstrap'), '1.0', true);
            //bootstrap touchspin
            wp_enqueue_script('rockefform-bootstrap-touchspin', UIFORM_FORMS_URL . '/assets/backend/js/btouchspin/jquery.bootstrap-touchspin.js', array('jquery', 'rockefform-bootstrap'), '1.0', true);
            //bootstrap datetimepicker
            wp_enqueue_script('rockefform-bootstrap-dtpicker-locales', UIFORM_FORMS_URL . '/assets/backend/js/bdatetime/4.7.14/moment-with-locales.js', array('jquery', 'rockefform-bootstrap'), '1.0', true);
            wp_enqueue_script('rockefform-bootstrap-datetimepicker', UIFORM_FORMS_URL . '/assets/backend/js/bdatetime/4.7.14/bootstrap-datetimepicker.js', array('jquery', 'rockefform-bootstrap'), '1.0', true);
            
            //bootstrap datetimepicker2
            wp_enqueue_script('rockefform-bootstrap-dtpicker-locales2', UIFORM_FORMS_URL . '/assets/common/js/flatpickr/4.5.2/flatpickr.js', array('jquery', 'rockefform-bootstrap'), '1.0', true);
            wp_enqueue_script('rockefform-bootstrap-datetimepicker2', UIFORM_FORMS_URL . '/assets/common/js/flatpickr/4.5.2/l10n/all-lang.js', array('jquery', 'rockefform-bootstrap'), '1.0', true);
            
            //autogrow
            wp_enqueue_script('rockefform-autogrow-textarea', UIFORM_FORMS_URL . '/assets/backend/js/jquery.autogrow-textarea.js');
            //bootstrap iconpicker
            wp_enqueue_script('rockefform-bootstrap-iconpicker-all', UIFORM_FORMS_URL . '/assets/backend/js/biconpicker/1.9.0/js/bootstrap-iconpicker-iconset-all.js', array('jquery', 'rockefform-bootstrap'), '1.0', true);
            wp_enqueue_script('rockefform-bootstrap-iconpicker', UIFORM_FORMS_URL . '/assets/backend/js/biconpicker/1.9.0/js/bootstrap-iconpicker.js', array('jquery', 'rockefform-bootstrap'), '1.0', true);
            //star rating
            wp_enqueue_script('rockefform-star-rating', UIFORM_FORMS_URL . '/assets/backend/js/bratestar/star-rating.js', array('jquery', 'rockefform-bootstrap'), '1.0', true);
            //datatables
            wp_enqueue_script('rockefform-dataTables', UIFORM_FORMS_URL . '/assets/backend/js/bdatatable/1.10.9/jquery.dataTables.js');
            //bootbox
            wp_enqueue_script('rockefform-bootbox', UIFORM_FORMS_URL . '/assets/backend/js/bootbox/bootbox.js');
            //intro
            wp_enqueue_script('rockefform-introjs', UIFORM_FORMS_URL . '/assets/backend/js/introjs/intro.js');
            //blueimp
            wp_enqueue_script('rockefform-blueimp', UIFORM_FORMS_URL . '/assets/common/js/blueimp/2.16.0/js/blueimp-gallery.min.js', array('jquery', 'rockefform-bootstrap'), '2.16.0', true);
            //bootstrap gallery
            wp_enqueue_script('rockefform-bootstrap-gal', UIFORM_FORMS_URL . '/assets/common/js/bgallery/3.1.3/js/bootstrap-image-gallery.js', array('jquery', 'rockefform-bootstrap','rockefform-blueimp'), '3.1.0', true);
            //lzstring
            wp_enqueue_script('rockefform-lzstring', UIFORM_FORMS_URL . '/assets/backend/js/lzstring/lz-string.min.js');
            
            //checkradio
            wp_enqueue_script('rockefform-checkradio', UIFORM_FORMS_URL . '/assets/common/js/checkradio/2.2.2/js/jquery.checkradios.js', array('jquery'), '2.2.2', true);
            
            //iframe
            wp_enqueue_script('rockefform-iframe', UIFORM_FORMS_URL . '/assets/frontend/js/iframe/3.5.5/iframeResizer.min.js');
            
            //codemirror
            wp_enqueue_script('rockefform-codemirror', UIFORM_FORMS_URL . '/assets/common/js/codemirror/codemirror.js', array(), '1.0', true);
            wp_enqueue_script('rockefform-codemirror-foldcode', UIFORM_FORMS_URL . '/assets/common/js/codemirror/addon/fold/foldcode.js', array(), '1.0', true);
            wp_enqueue_script('rockefform-codemirror-foldgutter', UIFORM_FORMS_URL . '/assets/common/js/codemirror/addon/fold/foldgutter.js', array(), '1.0', true);

            wp_enqueue_script('rockefform-codemirror-javascript', UIFORM_FORMS_URL . '/assets/common/js/codemirror/mode/javascript/javascript.js', array(), '1.0', true);
            wp_enqueue_script('rockefform-codemirror-xml', UIFORM_FORMS_URL . '/assets/common/js/codemirror/mode/xml/xml.js', array(), '1.0', true);
            wp_enqueue_script('rockefform-codemirror-css', UIFORM_FORMS_URL . '/assets/common/js/codemirror/mode/css/css.js', array(), '1.0', true);
            
            
            wp_enqueue_script('rockefform-codemirror-sublime', UIFORM_FORMS_URL . '/assets/common/js/codemirror/keymap/sublime.js', array(), '1.0', true);
            wp_enqueue_script('rockefform-codemirror-closebrackets', UIFORM_FORMS_URL . '/assets/common/js/codemirror/addon/edit/closebrackets.js', array(), '1.0', true);
            wp_enqueue_script('rockefform-codemirror-matchbrackets', UIFORM_FORMS_URL . '/assets/common/js/codemirror/addon/edit/matchbrackets.js', array(), '1.0', true);
            wp_enqueue_script('rockefform-codemirror-autorefresh', UIFORM_FORMS_URL . '/assets/common/js/codemirror/addon/display/autorefresh.js', array(), '1.0', true);
            
                    
            
               if(UIFORM_DEV===1){
            
           wp_enqueue_script('rockefform-dev-1', UIFORM_FORMS_URL . '/assets/backend/js/prev.js', array(), UIFORM_VERSION, true);
          // wp_enqueue_script('rockefform-dev-2', UIFORM_FORMS_URL . '/assets/backend/js/init.js', array('jquery', 'rockefform-bootstrap'));
           wp_enqueue_script('rockefform-dev-3', UIFORM_FORMS_URL . '/assets/backend/js/uiform-core.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-4', UIFORM_FORMS_URL . '/assets/backend/js/zgfm-back-helper.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-4-1', UIFORM_FORMS_URL . '/assets/backend/js/zgfm-back-tools.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-5', UIFORM_FORMS_URL . '/assets/backend/js/zgfm-back-upgrade.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-6', UIFORM_FORMS_URL . '/assets/backend/js/zgfm-back-calc.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-7', UIFORM_FORMS_URL . '/assets/backend/js/zgfm-back-err.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-7-1', UIFORM_FORMS_URL . '/assets/backend/js/zgfm-back-general.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-7-2', UIFORM_FORMS_URL . '/assets/backend/js/zgfm-back-fld-options.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-8', UIFORM_FORMS_URL . '/assets/backend/js/uiform_textbox.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-9', UIFORM_FORMS_URL . '/assets/backend/js/uiform_textarea.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-10', UIFORM_FORMS_URL . '/assets/backend/js/uiform_radiobtn.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-11', UIFORM_FORMS_URL . '/assets/backend/js/uiform_checkbox.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-12', UIFORM_FORMS_URL . '/assets/backend/js/uiform_select.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-13', UIFORM_FORMS_URL . '/assets/backend/js/uiform_multiselect.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-14', UIFORM_FORMS_URL . '/assets/backend/js/uiform_fileupload.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-15', UIFORM_FORMS_URL . '/assets/backend/js/uiform_imageupload.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-16', UIFORM_FORMS_URL . '/assets/backend/js/uiform_customhtml.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-17', UIFORM_FORMS_URL . '/assets/backend/js/uiform_password.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-18', UIFORM_FORMS_URL . '/assets/backend/js/uiform_preptext.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-19', UIFORM_FORMS_URL . '/assets/backend/js/uiform_appetext.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-20', UIFORM_FORMS_URL . '/assets/backend/js/uiform_prepapptext.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-21', UIFORM_FORMS_URL . '/assets/backend/js/uiform_slider.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-22', UIFORM_FORMS_URL . '/assets/backend/js/uiform_range.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-23', UIFORM_FORMS_URL . '/assets/backend/js/uiform_spinner.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-24', UIFORM_FORMS_URL . '/assets/backend/js/uiform_captcha.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-25', UIFORM_FORMS_URL . '/assets/backend/js/uiform_recaptcha.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-26', UIFORM_FORMS_URL . '/assets/backend/js/uiform_datepicker.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-26-1', UIFORM_FORMS_URL . '/assets/backend/js/uiform_datepicker2.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-27', UIFORM_FORMS_URL . '/assets/backend/js/uiform_timepicker.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-28', UIFORM_FORMS_URL . '/assets/backend/js/uiform_datetime.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-29', UIFORM_FORMS_URL . '/assets/backend/js/uiform_divider.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-30', UIFORM_FORMS_URL . '/assets/backend/js/uiform_colorpicker.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-31', UIFORM_FORMS_URL . '/assets/backend/js/uiform_ratingstar.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-32', UIFORM_FORMS_URL . '/assets/backend/js/uiform_hiddeninput.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-33', UIFORM_FORMS_URL . '/assets/backend/js/uiform_submitbtn.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-34', UIFORM_FORMS_URL . '/assets/backend/js/uiform_panelfld.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-35', UIFORM_FORMS_URL . '/assets/backend/js/uiform_heading.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-36', UIFORM_FORMS_URL . '/assets/backend/js/uiform_wizardbtn.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-37', UIFORM_FORMS_URL . '/assets/backend/js/uiform_switch.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-38', UIFORM_FORMS_URL . '/assets/backend/js/uiform_dyncheckbox.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-39', UIFORM_FORMS_URL . '/assets/backend/js/uiform_dynradiobtn.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-40', UIFORM_FORMS_URL . '/assets/backend/js/uiform-f-gridsystem.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-41', UIFORM_FORMS_URL . '/assets/backend/js/uiform-panel.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-42', UIFORM_FORMS_URL . '/assets/common/js/uiform-sticky.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-43', UIFORM_FORMS_URL . '/assets/common/js/dcheckbox/uiform-dcheckbox.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-44', UIFORM_FORMS_URL . '/assets/backend/js/zgpbld-grid.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-45', UIFORM_FORMS_URL . '/assets/backend/js/functions.js',array(), UIFORM_VERSION, true);
           wp_enqueue_script('rockefform-dev-46', UIFORM_FORMS_URL . '/assets/backend/js/global.js',array(), UIFORM_VERSION, true);
           
           
            wp_register_script(
                self::PREFIX . 'admin', UIFORM_FORMS_URL . '/assets/backend/js/blank.js', array(), UIFORM_VERSION, true
            );
        }else{
            wp_register_script(
                self::PREFIX . 'admin', UIFORM_FORMS_URL . '/assets/backend/js/admin-js.js', array(), UIFORM_VERSION, true
            );
        }
            
            //load recaptcha api
            //wp_enqueue_script(self::PREFIX . 'uifm_js_recaptcha');
            //load rocket form
            wp_enqueue_script(self::PREFIX . 'admin');
            
            
            
            $zgfm_vars = apply_filters('zgfm_back_filter_globalvars', array('url_site' => site_url(),
                'url_admin' => admin_url(), 
                'url_plugin' => UIFORM_FORMS_URL,
                'app_version' => UIFORM_VERSION,
                'app_is_lite' => ZIGAFORM_C_LITE,
                'app_demo_st' => UIFORM_DEMO,
                'url_assets' => UIFORM_FORMS_URL . "/assets",
                'ajax_nonce' => wp_create_nonce('zgfm_ajax_nonce')));
            
            
            wp_localize_script(self::PREFIX . 'admin', 'uiform_vars', $zgfm_vars);
            
            //load form variables
            $form_variables=array();
            $form_variables['ajaxurl']='';
            $form_variables['uifm_baseurl']=UIFORM_FORMS_URL;
            $form_variables['uifm_siteurl']=UIFORM_FORMS_URL;
            
            $form_variables['uifm_sfm_baseurl']=UIFORM_FORMS_URL . "/libraries/styles-font-menu/styles-fonts/png/";
            $form_variables['imagesurl']=UIFORM_FORMS_URL . "/assets/frontend/images";

            wp_localize_script('rockefform-prev-jquery', 'rockfm_vars', $form_variables);
         
            
        
    }

    /**
     * Internationalization.
     * Loads the plugin language files
     *
     * @access public
     * @return void
     */
    public function i18n() {

        // Set filter for plugin's languages directory
        $lang_dir = UIFORM_FORMS_DIR . '/i18n/languages/';
        $lang_dir = apply_filters('rockfm_languages_directory', $lang_dir);

        $lang_domain = 'FRocket_admin';
        $lang_domain = apply_filters('rockfm_languages_domain', $lang_domain);

        // Traditional WordPress plugin locale filter
        $locale = apply_filters('plugin_locale', get_locale(), 'zgfm_cost_estimate');
        $mofile = sprintf('%1$s-%2$s.mo', 'wprockf', $locale);

        // Setup paths to current locale file
        $mofile_local = $lang_dir . $mofile;
 
        if (file_exists($mofile_local)) {
            // Look in local /wp-content/plugins/wpbp/languages/ folder
            load_textdomain($lang_domain, $mofile_local);
        } else {
            // Load the default language files - but this is not working for some reason
            load_plugin_textdomain($lang_domain, false, dirname(plugin_basename(__FILE__)) . '/i18n/languages/');
        }
    }

    /**
     * Initializes variables
     *
     * @mvc Controller
     */
    public function init() {
        try {
            
        } catch (Exception $exception) {
            add_notice(__METHOD__ . ' error: ' . $exception->getMessage(), 'error');
        }
    }

    /*
     * Instance methods
     */

    /**
     * Prepares sites to use the plugin during single or network-wide activation
     *
     * @mvc Controller
     *
     * @param bool $network_wide
     */
    public function activate($network_wide = false) {
        require_once( UIFORM_FORMS_DIR . '/classes/uiform-installdb.php');
        $installdb = new Uiform_InstallDB();
        $installdb->install($network_wide);
        return true;
    }

    /**
     * Rolls back activation procedures when de-activating the plugin
     *
     * @mvc Controller
     */
    public function deactivate() {
        
        return true;
    }
    
   
    
    /**
     * Checks if the plugin was recently updated and upgrades if necessary
     *
     * @mvc Controller
     *
     * @param string $db_version
     */
    public function upgrade($db_version = 0) {
        return true;
    }

    /**
     * Checks that the object is in a correct state
     *
     * @mvc Model
     *
     * @param string $property An individual property to check, or 'all' to check all of them
     * @return bool
     */
    protected function is_valid($property = 'all') {
        return true;
    }

}

?>
