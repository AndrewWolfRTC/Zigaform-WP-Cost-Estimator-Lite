<?php
/*
 * Plugin Name: ZigaForm - Wordpress Calculator & Cost Estimation Form Builder Lite
 * Plugin URI: http://wordpress-cost-estimator.zigaform.com
 * Description: The ZigaForm Calculator & Cost Estimation makes you build estimation forms in few steps.
 * Version: 3.7.6.5
 * Author: ZigaForm.Com
 * Author URI: http://wordpress-cost-estimator.zigaform.com/
 */

if (!defined('ABSPATH')) {
    die('Access denied.');
}
if (!class_exists('UiformCostEst')) {

    final class UiformCostEst {

        /**
         * The only instance of the class
         *
         * @var RocketForm
         * @since 1.0
         */
        private static $instance;

        /**
         * The Plug-in version.
         *
         * @var string
         * @since 1.0
         */
        public $version = '3.7.6.5';

        /**
         * The minimal required version of WordPress for this plug-in to function correctly.
         *
         * @var string
         * @since 1.0
         */
        public $wp_version = '3.6';

        /**
         * The minimal required version of WordPress for this plug-in to function correctly.
         *
         * @var string
         * @since 1.0
         */
        public $php_version = '5.3';

        /**
         * Class name
         *
         * @var string
         * @since 1.0
         */
        public $class_name;

        /**
         * An array of defined constants names
         *
         * @var array
         * @since 1.0
         */
        public $defined_constants;
        
        

        /**
         * Create a new instance of the main class
         *
         * @since 1.0
         * @static
         * @return RocketForm
         */
        public static function instance() 
        {
            $class_name = get_class();
            if (!isset(self::$instance) && !( self::$instance instanceof $class_name )) {
                self::$instance = new $class_name;
            }

            return self::$instance;
        }

        public function __construct() 
        {
            // Save the class name for later use
            $this->class_name = get_class();
             //
            //  Plug-in requirements
            //
            if (!$this->check_requirements()) {
                add_action('admin_notices', array(&$this, 'uiform_requirements_error'));
                return;
            }
            
            //
            // Declare constants and load dependencies
            //
            $this->define_constants();
            $this->load_dependencies();
            $this->check_updateChanges();

            try {

                if (class_exists('Uiform_Bootstrap')) {
                    $GLOBALS['wprockf'] = Uiform_Bootstrap::get_instance();
                    
                   /* global $zgfm_f_addon;
                    $zgfm_f_addon= new zgfm_core_addon();
                    */
                    
                    register_activation_hook(__FILE__, array($GLOBALS['wprockf'], 'activate'));
                    register_deactivation_hook(__FILE__, array($GLOBALS['wprockf'], 'deactivate'));
                    
                }
            } catch (exception $e) {
                $error = $e->getMessage() . "\n";
                echo $error;
            }
        }

        
        /**
        * check_requirements()
        * Checks that the WordPress setup meets the plugin requirements
        * 
        * @return boolean
        */
        private function check_requirements() {
            global $wp_version;
            if (!version_compare($wp_version, $this->wp_version, '>=')) {
                add_action('admin_notices', array(&$this, 'display_req_notice'));

                return false;
            }

            if (version_compare(PHP_VERSION, $this->php_version, '<')) {
                return false;
            }
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
            if (is_plugin_active( 'uiform-form-builder/uiform-form-builder.php' ) ) {
               return false;
            }
	

            return true;
        }

        public function uiform_requirements_error() {
            global $wp_version;
            require_once dirname(__FILE__) . '/views/requirements-error.php';
        }

        /**
         * Define constants needed across the plug-in.
         */
        private function define_constants() {
            $this->define('UIFORM_FILE', __FILE__);
            $this->define('UIFORM_FOLDER', plugin_basename(dirname(__FILE__)));
            $this->define('UIFORM_BASENAME', plugin_basename(__FILE__));
            $this->define('UIFORM_ABSFILE', __FILE__);
            $this->define('UIFORM_ADMINPATH', get_admin_url());
            $this->define('UIFORM_APP_NAME', "Zigaform - Calculator & Cost Estimation Form Builder");
            $this->define('UIFORM_VERSION', $this->version);
            $this->define('UIFORM_FORMS_DIR', dirname(__FILE__));
            $this->define('UIFORM_FORMS_URL', plugins_url() . '/'.UIFORM_FOLDER);
            $this->define('UIFORM_FORMS_LIBS', UIFORM_FORMS_DIR . '/libraries');
            $this->define('UIFORM_DEBUG', 0);
            $this->define('UIFORM_DEMO', 0);
            $this->define('UIFORM_DEV', 0);
            if (UIFORM_DEBUG == 1) {
                error_reporting(E_ALL);
                ini_set('display_errors', 1);
                ini_set("memory_limit","850M");
                set_time_limit(0);
            }
            
            $this->define('ZIGAFORM_C_LITE', 1);
        }

        /**
         * Define constant if not already set
         * @param  string $name
         * @param  string|bool $value
         */
        private function define($name, $value) 
        {
            if (!defined($name)) {
                define($name, $value);
                $this->defined_constants[] = $name;
            }
        }

        /**
         * Loads PHP files that required by the plug-in
         */
        private function load_dependencies() {
            // Admin Panel
            if (is_admin()) {
                require_once UIFORM_FORMS_DIR . '/classes/uiform-base-module.php';
                require_once UIFORM_FORMS_DIR . '/classes/uiform-form-helper.php';
                require_once UIFORM_FORMS_DIR . '/classes/uiform-bootstrap.php';
                include UIFORM_FORMS_DIR . '/helpers/styles-font-menu/plugin.php';
                require_once UIFORM_FORMS_DIR . '/classes/zigaform-notice.php';
                //require_once UIFORM_FORMS_DIR . '/classes/zgfm_core_addon.php';
            }

            // Front-End Site
            if (!is_admin()) {
                require_once UIFORM_FORMS_DIR . '/classes/uiform-base-module.php';
                require_once UIFORM_FORMS_DIR . '/classes/uiform-form-helper.php';
                require_once UIFORM_FORMS_DIR . '/classes/uiform-bootstrap.php';
            }
        }
        
        /**
         * Loads PHP files that required by the plug-in
         */
        private function check_updateChanges() {
            global $wpdb;
            $version=UIFORM_VERSION;
            $install_ver = get_option("uifmcostest_version");
             
            if (!$install_ver || version_compare($version,$install_ver, '>')) {
                
                if (!$install_ver || version_compare($install_ver,"1.6", '<')) {
                    $tbname = $wpdb->prefix . "cest_uiform_fields";
                   
                    if ((string)$wpdb->get_var("SHOW TABLES LIKE '$tbname'") === $tbname) {
                        
                        $row= $wpdb->get_var("SHOW COLUMNS FROM " . $tbname . " LIKE 'order_frm'");
                        
                        
                        if (empty($row)) {
                            $sql = "ALTER TABLE " . $tbname . " ADD  order_frm smallint(5) DEFAULT NULL;";
                            $wpdb->query($sql);
                        }

                        $row = $wpdb->get_var("SHOW COLUMNS FROM " . $tbname . " LIKE 'order_rec'");
                        
                        if (empty($row)) {
                            $sql = "ALTER TABLE " . $tbname . " ADD  order_rec smallint(5) DEFAULT NULL;";
                            $wpdb->query($sql);
                        }
                    }
                     
                }
                
                if (!$install_ver || version_compare($install_ver,"3", '<')) {
                    
                    $tbname = $wpdb->prefix . "cest_uiform_form_log";
                   
                    if ((string)$wpdb->get_var("SHOW TABLES LIKE '$tbname'") != $tbname) {
                            $charset = '';
                            //form log
                            $sql="CREATE  TABLE IF NOT EXISTS $tbname (
                                `log_id` int(6) NOT NULL AUTO_INCREMENT,
                                `log_frm_data` longtext,
                                `log_frm_name` varchar(255) DEFAULT NULL,
                                `log_frm_html` longtext,
                                `log_frm_html_backend` longtext,
                                `log_frm_html_css` longtext,
                                `log_frm_id` int(6) NOT NULL,
                                `log_frm_hash` varchar(255) NOT NULL,
                                `flag_status` smallint(5) DEFAULT '1',
                                `created_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                                `updated_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                `created_ip` varchar(50) DEFAULT NULL,
                                `updated_ip` varchar(50) DEFAULT NULL,
                                `created_by` int(6) DEFAULT NULL,
                                `updated_by` int(6) DEFAULT NULL,
                                PRIMARY KEY (`log_id`)
                            ) " . $charset . ";";
                        
                            
                            $wpdb->query($sql);
                        
                    }
                     
                }
                
                //below 3.3.2
                if (!$install_ver || version_compare($install_ver,"3.4", '<')) {
                    $tbname = $wpdb->prefix . "cest_uiform_form_records";
                   
                    if ((string)$wpdb->get_var("SHOW TABLES LIKE '$tbname'") === $tbname) {
                        
                        $row= $wpdb->get_var("SHOW COLUMNS FROM " . $tbname . " LIKE 'fbh_data2'");
                        
                        
                        if (empty($row)) {
                            $sql = "ALTER TABLE " . $tbname . " ADD  fbh_data2 longtext;";
                            $wpdb->query($sql);
                        }

                        $row = $wpdb->get_var("SHOW COLUMNS FROM " . $tbname . " LIKE 'fbh_data_rec2'");
                        
                        if (empty($row)) {
                            $sql = "ALTER TABLE " . $tbname . " ADD  fbh_data_rec2 longtext;";
                            $wpdb->query($sql);
                        }
                        
                        
                        $row = $wpdb->get_var("SHOW COLUMNS FROM " . $tbname . " LIKE 'fbh_data_rec2_xml'");
                        
                        if (empty($row)) {
                            $sql = "ALTER TABLE " . $tbname . " ADD  fbh_data_rec2_xml longtext;";
                            $wpdb->query($sql);
                        }
                    }
                     
                }
                
                
                //below 3.4.3
                if (!$install_ver || version_compare($install_ver,"3.4.3", '<')) {
                    
                    $tbname = $wpdb->prefix . "cest_uiform_form";
                    if ((string)$wpdb->get_var("SHOW TABLES LIKE '$tbname'") === $tbname) {
                        
                        $row = $wpdb->get_var("SHOW COLUMNS FROM " . $tbname . " LIKE 'fmb_data2'");
                        
                        if (!empty($row)) {
                            $sql = "ALTER TABLE " . $tbname . " MODIFY  fmb_data2 longtext;";
                            $wpdb->query($sql);
                        }  
                    }
                    
                    $tbname = $wpdb->prefix . "cest_uiform_fields";
                    if ((string)$wpdb->get_var("SHOW TABLES LIKE '$tbname'") === $tbname) {
                        
                        $row = $wpdb->get_var("SHOW COLUMNS FROM " . $tbname . " LIKE 'fmf_data'");
                        
                        if (!empty($row)) {
                            $sql = "ALTER TABLE " . $tbname . " MODIFY  fmf_data longtext;";
                            $wpdb->query($sql);
                        }  
                    }
                    
                    $tbname = $wpdb->prefix . "cest_uiform_form_records";
                    if ((string)$wpdb->get_var("SHOW TABLES LIKE '$tbname'") === $tbname) {
                        
                        $row = $wpdb->get_var("SHOW COLUMNS FROM " . $tbname . " LIKE 'fbh_error'");
                        
                        if (!empty($row)) {
                            $sql = "ALTER TABLE " . $tbname . " MODIFY  fbh_error longtext;";
                            $wpdb->query($sql);
                        }  
                    }
                    
                    $tbname = $wpdb->prefix . "cest_uiform_pay_logs";
                    if ((string)$wpdb->get_var("SHOW TABLES LIKE '$tbname'") === $tbname) {
                        
                        $row = $wpdb->get_var("SHOW COLUMNS FROM " . $tbname . " LIKE 'pgl_message'");
                        
                        if (!empty($row)) {
                            $sql = "ALTER TABLE " . $tbname . " MODIFY  pgl_message longtext;";
                            $wpdb->query($sql);
                        }  
                    }
                     
                }
                
                 //below 3.7
                if (!$install_ver || version_compare($install_ver,"3.7", '<')) {
                    
                    $charset = '';
                    if( $wpdb->has_cap( 'collation' ) ){
                        if( !empty($wpdb->charset) )
                            $charset = "DEFAULT CHARACTER SET $wpdb->charset";
                        if( !empty($wpdb->collate) )
                            $charset .= " COLLATE $wpdb->collate";
                    }
                    
                    
                    $tbname = $wpdb->prefix . "cest_addon";
                   
                    if ((string)$wpdb->get_var("SHOW TABLES LIKE '$tbname'") != $tbname) {
                       
                         //addon
                        $sql="CREATE  TABLE IF NOT EXISTS $tbname (
                            `add_name` varchar(45) NOT NULL DEFAULT '',
                            `add_title` text ,
                            `add_info` text ,
                            `add_system` smallint(5) DEFAULT NULL,
                            `add_hasconfig` smallint(5) DEFAULT NULL,
                            `add_version` varchar(45)  DEFAULT NULL,
                            `add_icon` text ,
                            `add_installed` smallint(5) DEFAULT NULL,
                            `add_order` int(5) DEFAULT NULL,
                            `add_params` text ,
                            `add_log` text ,
                            `addonscol` varchar(45) DEFAULT NULL,
                            `flag_status` smallint(5)  DEFAULT 1,
                            `created_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                            `updated_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `created_ip` varchar(50)  DEFAULT NULL,
                            `updated_ip` varchar(50)  DEFAULT NULL,
                            `created_by` int(6) DEFAULT NULL,
                            `updated_by` int(6) DEFAULT NULL,
                            `add_xml` text ,
                            `add_load_back` smallint(5) DEFAULT NULL,
                            `add_load_front` smallint(5) DEFAULT NULL,
                            `is_field` smallint(5) DEFAULT NULL,
                            PRIMARY KEY (`add_name`) 
                        ) " . $charset . ";";

                         $wpdb->query($sql);
                         
                          if(ZIGAFORM_C_LITE!=1){
                         $sql="INSERT INTO $tbname VALUES ('func_anim', 'Animation effect', 'Animation effects to fields', 1, 1, NULL, NULL, NULL, 1, NULL, NULL, NULL, 1, '0000-00-00 00:00:00', '2018-01-31 10:35:14', NULL, NULL, NULL, NULL, NULL, 1, 1, 1);";
                         $wpdb->query($sql);
                          }
                         
                    }
                    
                    $tbname = $wpdb->prefix . "cest_addon_details";
                   
                    if ((string)$wpdb->get_var("SHOW TABLES LIKE '$tbname'") != $tbname) {
                          //addon detail
                            $sql="CREATE  TABLE IF NOT EXISTS $tbname (
                                `add_name` varchar(45)  NOT NULL,
                                `fmb_id` int(5) NOT NULL,
                                `adet_data` longtext ,
                                `flag_status` smallint(5) DEFAULT 1,
                                `created_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                                `updated_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                `created_ip` varchar(20) DEFAULT NULL,
                                `updated_ip` varchar(20) DEFAULT NULL,
                                `created_by` int(6) DEFAULT NULL,
                                `updated_by` int(6) DEFAULT NULL,
                                PRIMARY KEY (`add_name`, `fmb_id`) 
                            ) " . $charset . ";";

                             $wpdb->query($sql);
                        
                    }
                    
                    $tbname = $wpdb->prefix . "cest_addon_details_log";
                   
                    if ((string)$wpdb->get_var("SHOW TABLES LIKE '$tbname'") != $tbname) {
                        
                        //addon log
                        $sql="CREATE  TABLE IF NOT EXISTS $tbname (
                            `add_log_id` int(5) NOT NULL AUTO_INCREMENT,
                            `add_name` varchar(45)  NOT NULL,
                            `fmb_id` int(5) NOT NULL,
                            `adet_data` longtext  NULL,
                            `flag_status` smallint(5) DEFAULT 1,
                            `created_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
                            `updated_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            `created_ip` varchar(20) DEFAULT NULL,
                            `updated_ip` varchar(20) DEFAULT NULL,
                            `created_by` int(6) DEFAULT NULL,
                            `updated_by` int(6) DEFAULT NULL,
                            `log_id` int(5) NOT NULL,
                            PRIMARY KEY (`add_log_id`) 
                        ) " . $charset . ";";

                         $wpdb->query($sql);
                    }
                     
                }
                
                //below 3.7.6.3
                if (!$install_ver || version_compare($install_ver,"3.7.6.3", '<')) {
                    
                     $tbname = $wpdb->prefix . "cest_addon";
                   
                    if ((string)$wpdb->get_var("SHOW TABLES LIKE '$tbname'") === $tbname) {
                        
                        $row= @$wpdb->get_var("SHOW COLUMNS FROM " . $tbname . " LIKE `add_id`");
                        if (!empty($row)) {
                            $sql = "ALTER TABLE " . $tbname . " DROP COLUMN `add_id`;";
                            $wpdb->query($sql);
                        }
                    }
                    
                }
                
                 update_option("uifmcostest_version", $version);
            }
            
            
        }

    }

}

function uiform_uninstall()
{
   require_once( UIFORM_FORMS_DIR . '/classes/uiform-installdb.php');
   $installdb = new Uiform_InstallDB();
   $installdb->uninstall();
   //removing options
    delete_option('uifmcostest_version' );
   return true;
}
function wpRCEST() {
    register_uninstall_hook(__FILE__, 'uiform_uninstall');
    return UiformCostEst::instance();
}

wpRCEST();
?>