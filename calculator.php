<?php
/**
 * Standalone Australia Post Shipping Rate Calculator Page
 * Compatible with Dolibarr 21.0.2
 *
 * @package   AusPost
 * @author    Kye McIntyre
 * @license   GPL-3.0-or-later
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php in root directories
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once __DIR__ . '/lib/auspost.lib.php';
require_once __DIR__ . '/class/auspostapi.class.php';

// Security check
if (empty($conf->auspost->enabled) || empty($user->rights->auspost->read)) {
    accessforbidden();
}

$langs->loadLangs(array("auspost@auspost", "commercial", "products"));

$action        = GETPOST('action', 'alpha');
$fromPostcode  = GETPOST('from_postcode', 'alpha') ?: auspost_get_sender_postcode($mysoc);
$toPostcode    = GETPOST('to_postcode', 'alpha');
$toCountry     = strtoupper(trim(GETPOST('to_country', 'alpha') ?: 'AU'));
$length        = (float)(GETPOST('length', 'alpha') ?: getDolGlobalString('AUSPOST_DEFAULT_LENGTH', '22'));
$width         = (float)(GETPOST('width', 'alpha') ?: getDolGlobalString('AUSPOST_DEFAULT_WIDTH', '16'));
$height        = (float)(GETPOST('height', 'alpha') ?: getDolGlobalString('AUSPOST_DEFAULT_HEIGHT', '8'));
$weight        = (float)(GETPOST('weight', 'alpha') ?: getDolGlobalString('AUSPOST_DEFAULT_WEIGHT', '1.0'));

$rates = array();
$errorMsg = '';
$cubicWeight = 0;
$billableWeight = 0;

if ($action == 'calculate') {
    $cubicWeight = auspost_calculate_cubic_weight($length, $width, $height);
    $billableWeight = auspost_get_billable_weight($weight, $length, $width, $height);

    $api = new AusPostApi();

    if ($toCountry === 'AU') {
        if (empty($toPostcode)) {
            $errorMsg = $langs->trans("AusPostErrorDestinationPostcodeRequired");
        } else {
            $services = $api->getDomesticServices($fromPostcode, $toPostcode, $length, $width, $height, $billableWeight);
        }
    } else {
        $services = $api->getInternationalServices($toCountry, $billableWeight);
    }

    if (!empty($errorMsg)) {
        // Handled
    } elseif ($services === false) {
        $errorMsg = $api->getLastError() ?: $langs->trans("AusPostErrorFailedToFetchRates");
    } else {
        $markupType   = getDolGlobalString('AUSPOST_HANDLING_FEE_TYPE', 'none');
        $markupAmount = (float)getDolGlobalString('AUSPOST_HANDLING_FEE_AMOUNT', '0.0');
        $vatRate      = (float)getDolGlobalString('AUSPOST_SHIPPING_VAT_RATE', '10.0');

        foreach ($services as $svc) {
            $basePrice = (float)$svc['price'];
            $priceHt = auspost_calculate_markup($basePrice, $markupType, $markupAmount);
            $markupValue = round($priceHt - $basePrice, 2);
            $taxAmount = round($priceHt * ($vatRate / 100.0), 2);
            $priceTtc = round($priceHt + $taxAmount, 2);

            $rates[] = array(
                'code'            => $svc['code'],
                'name'            => $svc['name'],
                'base_price'      => $basePrice,
                'markup'          => $markupValue,
                'price_ht'        => $priceHt,
                'vat_rate'        => $vatRate,
                'tax_amount'      => $taxAmount,
                'price_ttc'       => $priceTtc,
                'max_extra_cover' => isset($svc['max_extra_cover']) ? $svc['max_extra_cover'] : 0,
            );
        }
    }
}

$page_name = $langs->trans("AusPostRateCalculator");
llxHeader('', $page_name, '', '', 0, 0, array('/auspost/js/auspost.js'), array('/auspost/css/auspost.css'));

print load_fiche_titre($page_name, '', 'fa-truck');

print '<div class="fichecenter">';

// Check API key configuration warning
$apiKey = auspost_get_api_key();
if (empty($apiKey)) {
    print '<div class="warning"><i class="fa fa-exclamation-triangle"></i> ' . $langs->trans("AusPostApiKeyNotConfiguredWarning", dol_buildpath('/auspost/admin/setup.php', 1)) . '</div><br>';
}

if (!empty($errorMsg)) {
    print '<div class="error"><i class="fa fa-exclamation-circle"></i> ' . dol_escape_htmltag($errorMsg) . '</div><br>';
}

print '<div class="underbanner clearboth"></div>';

// Form
print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="calculate">';

print '<table class="border centpercent">';
print '<tr class="liste_titre"><th colspan="4">' . $langs->trans("AusPostShipmentDetails") . '</th></tr>';

// Origin & Destination
print '<tr class="oddeven">';
print '<td class="titlefieldfield required"><label for="from_postcode">' . $langs->trans("AusPostOriginPostcode") . '</label></td>';
print '<td><input type="text" class="width100" id="from_postcode" name="from_postcode" maxlength="4" value="' . dol_escape_htmltag($fromPostcode) . '" required></td>';
print '<td class="titlefieldfield required"><label for="to_country">' . $langs->trans("Country") . '</label></td>';
print '<td>';
print '<select id="to_country" name="to_country" class="flat" onchange="auspostOnCountryChange(this.value)">';
print '<option value="AU"' . ($toCountry == 'AU' ? ' selected' : '') . '>Australia (Domestic)</option>';
print '<option value="NZ"' . ($toCountry == 'NZ' ? ' selected' : '') . '>New Zealand</option>';
print '<option value="US"' . ($toCountry == 'US' ? ' selected' : '') . '>United States</option>';
print '<option value="GB"' . ($toCountry == 'GB' ? ' selected' : '') . '>United Kingdom</option>';
print '<option value="CA"' . ($toCountry == 'CA' ? ' selected' : '') . '>Canada</option>';
print '<option value="JP"' . ($toCountry == 'JP' ? ' selected' : '') . '>Japan</option>';
print '<option value="SG"' . ($toCountry == 'SG' ? ' selected' : '') . '>Singapore</option>';
print '</select>';
print '</td>';
print '</tr>';

print '<tr class="oddeven" id="row_dest_postcode"' . ($toCountry !== 'AU' ? ' style="display:none;"' : '') . '>';
print '<td class="required"><label for="to_postcode">' . $langs->trans("AusPostDestinationPostcode") . '</label></td>';
print '<td colspan="3"><input type="text" class="width100" id="to_postcode" name="to_postcode" maxlength="4" value="' . dol_escape_htmltag($toPostcode) . '"> ';
print '<span class="opacitymedium">(' . $langs->trans("AusPostDestinationPostcodeHelp") . ')</span></td>';
print '</tr>';

// Dimensions & Weight
print '<tr class="liste_titre"><th colspan="4">' . $langs->trans("AusPostParcelDimensionsWeight") . '</th></tr>';

print '<tr class="oddeven">';
print '<td><label for="weight">' . $langs->trans("Weight") . ' (kg)</label></td>';
print '<td><input type="number" step="0.05" class="width100" id="weight" name="weight" value="' . dol_escape_htmltag($weight) . '" required> kg</td>';
print '<td>' . $langs->trans("Dimensions") . ' (L x W x H cm)</td>';
print '<td>';
print '<input type="number" step="0.1" class="width75" name="length" value="' . dol_escape_htmltag($length) . '" placeholder="L"> x ';
print '<input type="number" step="0.1" class="width75" name="width" value="' . dol_escape_htmltag($width) . '" placeholder="W"> x ';
print '<input type="number" step="0.1" class="width75" name="height" value="' . dol_escape_htmltag($height) . '" placeholder="H"> cm';
print '</td>';
print '</tr>';

print '</table>';

print '<div class="tabsAction">';
print '<button type="submit" class="button butAction"><i class="fa fa-calculator"></i> ' . $langs->trans("AusPostCalculateRates") . '</button>';
print '</div>';

print '</form>';

// Results
if ($action == 'calculate' && !empty($rates)) {
    print '<br>';
    print '<div class="auspost-weights-summary">';
    print '<span><strong>' . $langs->trans("AusPostActualWeight") . ':</strong> ' . $weight . ' kg</span> &nbsp;|&nbsp; ';
    print '<span><strong>' . $langs->trans("AusPostCubicWeight") . ':</strong> ' . $cubicWeight . ' kg</span> &nbsp;|&nbsp; ';
    print '<span><strong>' . $langs->trans("AusPostBillableWeight") . ':</strong> <span class="badge badge-info">' . $billableWeight . ' kg</span></span>';
    print '</div>';
    print '<br>';

    print '<table class="noborder centpercent auspost-rate-table">';
    print '<tr class="liste_titre">';
    print '<th>' . $langs->trans("AusPostService") . '</th>';
    print '<th class="right">' . $langs->trans("AusPostBaseCost") . '</th>';
    print '<th class="right">' . $langs->trans("AusPostMarkup") . '</th>';
    print '<th class="right">' . $langs->trans("PriceHT") . '</th>';
    print '<th class="right">' . $langs->trans("VAT") . '</th>';
    print '<th class="right">' . $langs->trans("PriceTTC") . '</th>';
    print '</tr>';

    foreach ($rates as $r) {
        $isExpress = (strpos($r['code'], 'EXPRESS') !== false || strpos($r['code'], 'EXP') !== false);
        $badgeClass = $isExpress ? 'auspost-badge-express' : 'auspost-badge-standard';

        print '<tr class="oddeven">';
        print '<td>';
        print '<span class="badge ' . $badgeClass . '">' . dol_escape_htmltag($r['name']) . '</span>';
        print '<div class="opacitymedium small">' . dol_escape_htmltag($r['code']) . '</div>';
        print '</td>';
        print '<td class="right">' . price($r['base_price'], 0, $langs, 1, -1, -1, $conf->currency) . '</td>';
        print '<td class="right">' . ($r['markup'] > 0 ? '+' . price($r['markup'], 0, $langs, 1, -1, -1, $conf->currency) : '-') . '</td>';
        print '<td class="right"><strong>' . price($r['price_ht'], 0, $langs, 1, -1, -1, $conf->currency) . '</strong></td>';
        print '<td class="right">' . price($r['tax_amount'], 0, $langs, 1, -1, -1, $conf->currency) . ' (' . $r['vat_rate'] . '%)</td>';
        print '<td class="right font-weight-bold" style="font-size: 1.1em; color: #dc143c;"><strong>' . price($r['price_ttc'], 0, $langs, 1, -1, -1, $conf->currency) . '</strong></td>';
        print '</tr>';
    }

    print '</table>';
}

print '</div>';

llxFooter();
$db->close();
