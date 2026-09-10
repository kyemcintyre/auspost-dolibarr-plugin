<?php
/**
 * Australia Post Shipping Calculator Module Descriptor
 * Compatible with Dolibarr 21.0.2 (and 16.0 - 21.x)
 *
 * @package   AusPost
 * @author    Kye McIntyre
 * @license   GPL-3.0-or-later
 */

// Dolibarr environment detection
if (!class_exists('DolibarrModules')) {
    // Stub class when running outside of Dolibarr environment (e.g. composer autoload or standalone check)
    class DolibarrModules {
        public $db;
        public $numero;
        public $rights_class;
        public $family;
        public $module_position;
        public $name;
        public $description;
        public $version;
        public $const_name;
        public $picto;
        public $module_parts = array();
        public $dirs = array();
        public $config_page_url = array();
        public $rights = array();
        public $menu = array();
        public $const = array();
        public $tabs = array();

        public function __construct($db = null) {
            $this->db = $db;
        }

        public function _init($sql, $options = '') {
            return 1;
        }

        public function _remove($sql, $options = '') {
            return 1;
        }
    }
}

class modAuspost extends DolibarrModules
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // Unique ID for module (> 100000 for external modules)
        $this->numero = 500120;

        // Family: 'interface', 'other', or 'products'
        $this->family = "interface";
        $this->module_position = '50';

        // Module label & description
        $this->name = "auspost";
        $this->description = "Calculate Australia Post shipping rates and estimates for proposals, orders, and shipments";
        $this->version = "1.0.5";
        $this->const_name = "MAIN_MODULE_" . strtoupper($this->name);
        $this->special = 0;
        $this->picto = "fa-truck";

        // Configuration page URL
        $this->config_page_url = array("setup.php@auspost");

        // Dependencies
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array("auspost@auspost");

        // Constants
        $this->const = array(
            0 => array(
                'AUSPOST_API_BASE_URL',
                'chaine',
                'https://digitalapi.auspost.com.au',
                'Australia Post PAC API Base URL',
                0,
                'current',
                1
            ),
            1 => array(
                'AUSPOST_DEFAULT_LENGTH',
                'chaine',
                '22',
                'Default parcel length (cm)',
                0,
                'current',
                1
            ),
            2 => array(
                'AUSPOST_DEFAULT_WIDTH',
                'chaine',
                '16',
                'Default parcel width (cm)',
                0,
                'current',
                1
            ),
            3 => array(
                'AUSPOST_DEFAULT_HEIGHT',
                'chaine',
                '8',
                'Default parcel height (cm)',
                0,
                'current',
                1
            ),
            4 => array(
                'AUSPOST_DEFAULT_WEIGHT',
                'chaine',
                '1.0',
                'Default parcel weight (kg)',
                0,
                'current',
                1
            ),
            5 => array(
                'AUSPOST_HANDLING_FEE_TYPE',
                'chaine',
                'none',
                'Handling markup type (none, flat, percent)',
                0,
                'current',
                1
            ),
            6 => array(
                'AUSPOST_HANDLING_FEE_AMOUNT',
                'chaine',
                '0.0',
                'Handling fee or markup amount',
                0,
                'current',
                1
            ),
            7 => array(
                'AUSPOST_SHIPPING_VAT_RATE',
                'chaine',
                '10.0',
                'Default GST/VAT rate for shipping line',
                0,
                'current',
                1
            ),
        );

        // Directories created when module is enabled
        $this->dirs = array();

        // Hooks enabled
        $this->module_parts = array(
            'hooks' => array(
                'propalcard',
                'ordercard',
                'shipmentcard'
            ),
            'css' => array('/auspost/css/auspost.css'),
            'js'  => array('/auspost/js/auspost.js'),
        );

        // Permissions
        $this->rights_class = 'auspost';
        $this->rights = array();

        $r = 0;
        $this->rights[$r][0] = 500121;
        $this->rights[$r][1] = 'Read and calculate Australia Post shipping estimates';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 1; // Default for internal users
        $this->rights[$r][4] = 'read';
        $r++;

        $this->rights[$r][0] = 500122;
        $this->rights[$r][1] = 'Configure Australia Post API settings';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0; // Only administrators by default
        $this->rights[$r][4] = 'setup';
        $r++;

        // Menus
        $this->menu = array();
        $m = 0;

        // Left menu entry under Commercial / Commerce
        $this->menu[$m] = array(
            'fk_menu'   => 'fk_mainmenu=commercial',
            'type'      => 'left',
            'titre'     => 'AusPostCalculator',
            'mainmenu'  => 'commercial',
            'leftmenu'  => 'auspost_calculator',
            'url'       => '/auspost/calculator.php',
            'langs'     => 'auspost@auspost',
            'position'  => 550,
            'enabled'   => '$conf->auspost->enabled',
            'perms'     => '$user->rights->auspost->read',
            'target'    => '',
            'user'      => 2,
        );
        $m++;
    }

    /**
     * Function called when module is enabled.
     * Inserts constants, permissions, menus, and creates/verifies shipping modes in llx_c_shipment_mode.
     *
     * @param string $options Options when enabling module
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        global $conf;

        $sql = array();

        $result = $this->_init($sql, $options);
        if ($result <= 0) {
            return 0;
        }

        // Insert Australia Post default shipping modes if not already present
        if (!empty($this->db)) {
            $shippingModes = array(
                array('code' => 'AUSPOST_REG', 'libelle' => 'Australia Post - Parcel Post', 'description' => 'Regular parcel delivery with tracking'),
                array('code' => 'AUSPOST_EXP', 'libelle' => 'Australia Post - Express Post', 'description' => 'Guaranteed next business day express delivery'),
            );

            foreach ($shippingModes as $mode) {
                $checkSql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "c_shipment_mode WHERE code = '" . $this->db->escape($mode['code']) . "'";
                $res = $this->db->query($checkSql);
                if ($res && $this->db->num_rows($res) == 0) {
                    $insertSql = "INSERT INTO " . MAIN_DB_PREFIX . "c_shipment_mode (code, libelle, description, active)";
                    $insertSql .= " VALUES ('" . $this->db->escape($mode['code']) . "', '" . $this->db->escape($mode['libelle']) . "', '" . $this->db->escape($mode['description']) . "', 1)";
                    $this->db->query($insertSql);
                }
            }
        }

        return 1;
    }

    /**
     * Function called when module is disabled.
     *
     * @param string $options Options when disabling module
     * @return int 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
