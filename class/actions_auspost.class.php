<?php
/**
 * Australia Post Plugin Hook Actions
 * Compatible with Dolibarr 21.0.2
 *
 * @package   AusPost
 * @author    Kye McIntyre
 * @license   GPL-3.0-or-later
 */

class ActionsAuspost
{
    /** @var DoliDB */
    public $db;

    /** @var string Error message */
    public $error = '';

    /** @var array Error messages */
    public $errors = array();

    /** @var array Hook results */
    public $results = array();

    /** @var string Hook return output */
    public $resprints = '';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Hook to inject action buttons into cards (Proposals, Orders, Shipments)
     *
     * @param array       $parameters Parameters of the current context
     * @param CommonObject $object    Current business object (Propal, Commande, Expedition)
     * @param string      $action     Current action
     * @param HookManager $hookmanager Hook manager instance
     * @return int 0 on success
     */
    public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $langs, $user, $mysoc;

        if (empty($conf->auspost->enabled)) {
            return 0;
        }

        if (empty($user->rights->auspost->read)) {
            return 0;
        }

        $context = isset($parameters['currentcontext']) ? $parameters['currentcontext'] : '';
        $contexts = array('propalcard', 'ordercard', 'shipmentcard');

        if (!in_array($context, $contexts)) {
            return 0;
        }

        // Only allow calculation on draft or open/validated documents
        $canCalculate = false;
        $docType = '';

        if ($context == 'propalcard') {
            $docType = 'propal';
            // Status: 0=Draft, 1=Open
            if ($object->statut == 0 || $object->statut == 1) {
                $canCalculate = true;
            }
        } elseif ($context == 'ordercard') {
            $docType = 'order';
            // Status: 0=Draft, 1=Validated
            if ($object->statut == 0 || $object->statut == 1) {
                $canCalculate = true;
            }
        } elseif ($context == 'shipmentcard') {
            $docType = 'shipment';
            if ($object->statut == 0 || $object->statut == 1) {
                $canCalculate = true;
            }
        }

        if (!$canCalculate) {
            return 0;
        }

        $langs->loadLangs(array("auspost@auspost", "propal", "orders", "sendings"));

        require_once __DIR__ . '/../lib/auspost.lib.php';

        // Extract destination details from thirdparty/customer
        $destPostcode = '';
        $destSuburb   = '';
        $destCountry  = 'AU';

        $soc = null;
        if (!empty($object->thirdparty)) {
            $soc = $object->thirdparty;
        } elseif (!empty($object->socid) && method_exists($object, 'fetch_thirdparty')) {
            $object->fetch_thirdparty();
            $soc = $object->thirdparty;
        }

        if ($soc) {
            $destPostcode = !empty($soc->zip) ? trim($soc->zip) : '';
            $destSuburb   = !empty($soc->town) ? trim($soc->town) : '';
            $destCountry  = !empty($soc->country_code) ? strtoupper(trim($soc->country_code)) : 'AU';
        }

        // Calculate total weight from product lines
        $totalWeightKg = 0.0;
        if (!empty($object->lines) && is_array($object->lines)) {
            require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
            $prodCache = array();

            foreach ($object->lines as $line) {
                $fk_product = !empty($line->fk_product) ? $line->fk_product : 0;
                $qty = !empty($line->qty) ? (float)$line->qty : 1.0;

                if ($fk_product > 0) {
                    if (!isset($prodCache[$fk_product])) {
                        $p = new Product($this->db);
                        if ($p->fetch($fk_product) > 0) {
                            $prodCache[$fk_product] = $p;
                        } else {
                            $prodCache[$fk_product] = false;
                        }
                    }

                    $p = $prodCache[$fk_product];
                    if ($p && $p->weight > 0) {
                        $lineWeightKg = auspost_convert_weight_to_kg($p->weight, $p->weight_units);
                        $totalWeightKg += ($lineWeightKg * $qty);
                    }
                }
            }
        }

        // Fallback defaults from settings
        $defaultWeight = (float)getDolGlobalString('AUSPOST_DEFAULT_WEIGHT', '1.0');
        if ($totalWeightKg <= 0) {
            $totalWeightKg = $defaultWeight > 0 ? $defaultWeight : 1.0;
        }
        $totalWeightKg = round($totalWeightKg, 3);

        $defaultL = getDolGlobalString('AUSPOST_DEFAULT_LENGTH', '22');
        $defaultW = getDolGlobalString('AUSPOST_DEFAULT_WIDTH', '16');
        $defaultH = getDolGlobalString('AUSPOST_DEFAULT_HEIGHT', '8');
        $senderPostcode = auspost_get_sender_postcode($mysoc);

        $btnHtml = '<a class="butAction auspost-calc-trigger" href="javascript:void(0);" ';
        $btnHtml .= 'data-doctype="' . dol_escape_htmltag($docType) . '" ';
        $btnHtml .= 'data-docid="' . (int)$object->id . '" ';
        $btnHtml .= 'data-from-postcode="' . dol_escape_htmltag($senderPostcode) . '" ';
        $btnHtml .= 'data-to-postcode="' . dol_escape_htmltag($destPostcode) . '" ';
        $btnHtml .= 'data-to-suburb="' . dol_escape_htmltag($destSuburb) . '" ';
        $btnHtml .= 'data-to-country="' . dol_escape_htmltag($destCountry) . '" ';
        $btnHtml .= 'data-weight="' . dol_escape_htmltag($totalWeightKg) . '" ';
        $btnHtml .= 'data-length="' . dol_escape_htmltag($defaultL) . '" ';
        $btnHtml .= 'data-width="' . dol_escape_htmltag($defaultW) . '" ';
        $btnHtml .= 'data-height="' . dol_escape_htmltag($defaultH) . '">';
        $btnHtml .= '<i class="fa fa-truck auspost-red-icon"></i> ' . $langs->trans("AusPostCalculateShipping");
        $btnHtml .= '</a>';

        print $btnHtml;

        return 0;
    }
}
