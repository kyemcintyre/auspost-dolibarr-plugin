<?php
/**
 * AJAX Endpoint for Australia Post Shipping Rate Calculation and Line Application
 * Compatible with Dolibarr 21.0.2
 *
 * @package   AusPost
 * @author    Kye McIntyre
 * @license   GPL-3.0-or-later
 */

if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU'))   define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML'))   define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX'))   define('NOREQUIREAJAX', '1');

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res && file_exists("../../../../../main.inc.php")) $res = @include "../../../../../main.inc.php";
if (!$res) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('success' => false, 'message' => 'Unable to load Dolibarr environment'));
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/auspost.lib.php';
require_once __DIR__ . '/../class/auspostapi.class.php';

$action = GETPOST('action', 'alpha');

// 1. Action: test_connection
if ($action == 'test_connection') {
    if (!$user->admin && empty($user->rights->auspost->setup)) {
        echo json_encode(array('success' => false, 'message' => $langs->trans("AccessForbidden")));
        exit;
    }

    $apiKey = GETPOST('api_key', 'alpha');
    $apiBaseUrl = GETPOST('api_base_url', 'alpha');

    $api = new AusPostApi(
        !empty($apiKey) ? $apiKey : null,
        !empty($apiBaseUrl) ? $apiBaseUrl : null
    );

    $result = $api->testConnection();
    echo json_encode($result);
    exit;
}

// Security check for calculation & document updates
if (empty($conf->auspost->enabled) || empty($user->rights->auspost->read)) {
    echo json_encode(array('success' => false, 'message' => $langs->trans("AccessForbidden")));
    exit;
}

// 2. Action: get_rates
if ($action == 'get_rates') {
    $fromPostcode = GETPOST('from_postcode', 'alpha');
    $toPostcode   = GETPOST('to_postcode', 'alpha');
    $toCountry    = strtoupper(trim(GETPOST('to_country', 'alpha')));
    $length       = (float)GETPOST('length', 'alpha');
    $width        = (float)GETPOST('width', 'alpha');
    $height       = (float)GETPOST('height', 'alpha');
    $weight       = (float)GETPOST('weight', 'alpha');

    if (empty($toCountry)) {
        $toCountry = 'AU';
    }

    if (empty($fromPostcode)) {
        $fromPostcode = auspost_get_sender_postcode($mysoc);
    }

    // Default dimensions and weights if missing
    if ($length <= 0) $length = (float)getDolGlobalString('AUSPOST_DEFAULT_LENGTH', '22');
    if ($width <= 0)  $width  = (float)getDolGlobalString('AUSPOST_DEFAULT_WIDTH', '16');
    if ($height <= 0) $height = (float)getDolGlobalString('AUSPOST_DEFAULT_HEIGHT', '8');
    if ($weight <= 0) $weight = (float)getDolGlobalString('AUSPOST_DEFAULT_WEIGHT', '1.0');

    $cubicWeight = auspost_calculate_cubic_weight($length, $width, $height);
    $billableWeight = auspost_get_billable_weight($weight, $length, $width, $height);

    $api = new AusPostApi();

    if ($toCountry === 'AU') {
        if (empty($toPostcode)) {
            echo json_encode(array('success' => false, 'message' => $langs->trans("AusPostErrorDestinationPostcodeRequired")));
            exit;
        }

        $services = $api->getDomesticServices($fromPostcode, $toPostcode, $length, $width, $height, $billableWeight);
    } else {
        $services = $api->getInternationalServices($toCountry, $billableWeight);
    }

    if ($services === false) {
        echo json_encode(array(
            'success' => false,
            'message' => $api->getLastError() ?: $langs->trans("AusPostErrorFailedToFetchRates")
        ));
        exit;
    }

    $markupType   = getDolGlobalString('AUSPOST_HANDLING_FEE_TYPE', 'none');
    $markupAmount = (float)getDolGlobalString('AUSPOST_HANDLING_FEE_AMOUNT', '0.0');
    $vatRate      = (float)getDolGlobalString('AUSPOST_SHIPPING_VAT_RATE', '10.0');

    $rates = array();
    foreach ($services as $svc) {
        $basePrice = (float)$svc['price'];
        $priceHt = auspost_calculate_markup($basePrice, $markupType, $markupAmount);
        $markupValue = round($priceHt - $basePrice, 2);
        $taxAmount = round($priceHt * ($vatRate / 100.0), 2);
        $priceTtc = round($priceHt + $taxAmount, 2);

        $rates[] = array(
            'code'             => $svc['code'],
            'name'             => $svc['name'],
            'base_price'       => $basePrice,
            'markup'           => $markupValue,
            'price_ht'         => $priceHt,
            'vat_rate'         => $vatRate,
            'tax_amount'       => $taxAmount,
            'price_ttc'        => $priceTtc,
            'max_extra_cover'  => isset($svc['max_extra_cover']) ? $svc['max_extra_cover'] : 0,
            'formatted_ht'     => price($priceHt, 0, $langs, 1, -1, -1, $conf->currency),
            'formatted_ttc'    => price($priceTtc, 0, $langs, 1, -1, -1, $conf->currency),
        );
    }

    echo json_encode(array(
        'success'         => true,
        'rates'           => $rates,
        'from_postcode'   => $fromPostcode,
        'to_postcode'     => $toPostcode,
        'to_country'      => $toCountry,
        'actual_weight'   => $weight,
        'cubic_weight'    => $cubicWeight,
        'billable_weight' => $billableWeight,
        'length'          => $length,
        'width'           => $width,
        'height'          => $height,
    ));
    exit;
}

// 3. Action: apply_to_document
if ($action == 'apply_to_document') {
    $docType     = GETPOST('doctype', 'alpha');
    $docId       = GETPOST('docid', 'int');
    $serviceCode = GETPOST('service_code', 'alpha');
    $serviceName = GETPOST('service_name', 'alpha');
    $priceHt     = (float)GETPOST('price_ht', 'alpha');
    $vatRate     = (float)GETPOST('vat_rate', 'alpha');

    if ($docId <= 0 || empty($docType) || $priceHt < 0) {
        echo json_encode(array('success' => false, 'message' => $langs->trans("ErrorBadParameters")));
        exit;
    }

    $shippingProductId = getDolGlobalInt('AUSPOST_SHIPPING_PRODUCT_ID', 0);
    $desc = "Shipping: Australia Post - " . $serviceName;

    if ($docType == 'propal') {
        if (empty($user->rights->propal->creer)) {
            echo json_encode(array('success' => false, 'message' => $langs->trans("NotEnoughPermissions")));
            exit;
        }

        require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
        $propal = new Propal($db);
        if ($propal->fetch($docId) <= 0) {
            echo json_encode(array('success' => false, 'message' => $langs->trans("ErrorRecordNotFound")));
            exit;
        }

        // Product type 1 = Service
        $result = $propal->addline(
            $desc,
            $priceHt,
            1,
            $vatRate,
            0,
            0,
            $shippingProductId > 0 ? $shippingProductId : 0,
            0,
            'HT',
            0,
            1
        );

        if ($result > 0) {
            echo json_encode(array(
                'success' => true,
                'message' => $langs->trans("AusPostShippingLineAddedSuccess", $serviceName),
                'total_ht' => price($propal->total_ht, 0, $langs, 1, -1, -1, $conf->currency),
                'total_ttc' => price($propal->total_ttc, 0, $langs, 1, -1, -1, $conf->currency),
            ));
            exit;
        } else {
            echo json_encode(array(
                'success' => false,
                'message' => $propal->error ?: $langs->trans("AusPostErrorAddingLine")
            ));
            exit;
        }

    } elseif ($docType == 'order') {
        if (empty($user->rights->commande->creer)) {
            echo json_encode(array('success' => false, 'message' => $langs->trans("NotEnoughPermissions")));
            exit;
        }

        require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
        $order = new Commande($db);
        if ($order->fetch($docId) <= 0) {
            echo json_encode(array('success' => false, 'message' => $langs->trans("ErrorRecordNotFound")));
            exit;
        }

        $result = $order->addline(
            $desc,
            $priceHt,
            1,
            $vatRate,
            0,
            0,
            $shippingProductId > 0 ? $shippingProductId : 0,
            0,
            0,
            0,
            'HT',
            0,
            '',
            '',
            1
        );

        if ($result > 0) {
            echo json_encode(array(
                'success' => true,
                'message' => $langs->trans("AusPostShippingLineAddedSuccess", $serviceName),
                'total_ht' => price($order->total_ht, 0, $langs, 1, -1, -1, $conf->currency),
                'total_ttc' => price($order->total_ttc, 0, $langs, 1, -1, -1, $conf->currency),
            ));
            exit;
        } else {
            echo json_encode(array(
                'success' => false,
                'message' => $order->error ?: $langs->trans("AusPostErrorAddingLine")
            ));
            exit;
        }

    } elseif ($docType == 'shipment') {
        // For shipments, update shipping method or note
        require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';
        $shipment = new Expedition($db);
        if ($shipment->fetch($docId) <= 0) {
            echo json_encode(array('success' => false, 'message' => $langs->trans("ErrorRecordNotFound")));
            exit;
        }

        // Try mapping service to shipment mode
        $modeCode = ($serviceCode === 'AUS_PARCEL_EXPRESS' || strpos($serviceCode, 'EXP') !== false) ? 'AUSPOST_EXP' : 'AUSPOST_REG';
        $check = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "c_shipment_mode WHERE code = '" . $db->escape($modeCode) . "'");
        if ($check && $row = $db->fetch_object($check)) {
            $shipment->shipping_method_id = $row->rowid;
            $shipment->update($user);
        }

        echo json_encode(array(
            'success' => true,
            'message' => $langs->trans("AusPostShipmentUpdatedSuccess", $serviceName)
        ));
        exit;
    }

    echo json_encode(array('success' => false, 'message' => $langs->trans("ErrorUnknownDocumentType")));
    exit;
}

echo json_encode(array('success' => false, 'message' => 'Invalid action'));
exit;
