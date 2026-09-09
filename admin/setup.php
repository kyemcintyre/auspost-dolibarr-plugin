<?php
/**
 * Australia Post Plugin Configuration / Setup Page
 * Compatible with Dolibarr 21.0.2
 *
 * @package   AusPost
 * @author    Kye McIntyre
 * @license   GPL-3.0-or-later
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php in root directories
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res && file_exists("../../../../../main.inc.php")) $res = @include "../../../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once __DIR__ . '/../lib/auspost.lib.php';
require_once __DIR__ . '/../class/auspostapi.class.php';

// Security check
if (!$user->admin && empty($user->rights->auspost->setup)) {
    accessforbidden();
}

$langs->loadLangs(array("admin", "auspost@auspost", "products"));

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

/*
 * Actions
 */
if ($action == 'save') {
    $error = 0;

    $apiKey             = GETPOST('AUSPOST_API_KEY', 'alpha');
    $apiBaseUrl         = GETPOST('AUSPOST_API_BASE_URL', 'alpha');
    $senderPostcode     = GETPOST('AUSPOST_DEFAULT_SENDER_POSTCODE', 'alpha');
    $defaultLength      = GETPOST('AUSPOST_DEFAULT_LENGTH', 'alpha');
    $defaultWidth       = GETPOST('AUSPOST_DEFAULT_WIDTH', 'alpha');
    $defaultHeight      = GETPOST('AUSPOST_DEFAULT_HEIGHT', 'alpha');
    $defaultWeight      = GETPOST('AUSPOST_DEFAULT_WEIGHT', 'alpha');
    $handlingType       = GETPOST('AUSPOST_HANDLING_FEE_TYPE', 'alpha');
    $handlingAmount     = GETPOST('AUSPOST_HANDLING_FEE_AMOUNT', 'alpha');
    $vatRate            = GETPOST('AUSPOST_SHIPPING_VAT_RATE', 'alpha');
    $shippingProductId  = GETPOST('AUSPOST_SHIPPING_PRODUCT_ID', 'int');
    $enableIntl         = GETPOST('AUSPOST_ENABLE_INTL', 'int');

    if (!empty($apiBaseUrl) && !filter_var($apiBaseUrl, FILTER_VALIDATE_URL)) {
        setEventMessages($langs->trans("ErrorInvalidApiUrl"), null, 'errors');
        $error++;
    }

    if (!$error) {
        dolibarr_set_const($db, 'AUSPOST_API_KEY', trim($apiKey), 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'AUSPOST_API_BASE_URL', !empty($apiBaseUrl) ? rtrim(trim($apiBaseUrl), '/') : 'https://digitalapi.auspost.com.au', 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'AUSPOST_DEFAULT_SENDER_POSTCODE', trim($senderPostcode), 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'AUSPOST_DEFAULT_LENGTH', (float)$defaultLength > 0 ? (float)$defaultLength : 22, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'AUSPOST_DEFAULT_WIDTH', (float)$defaultWidth > 0 ? (float)$defaultWidth : 16, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'AUSPOST_DEFAULT_HEIGHT', (float)$defaultHeight > 0 ? (float)$defaultHeight : 8, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'AUSPOST_DEFAULT_WEIGHT', (float)$defaultWeight > 0 ? (float)$defaultWeight : 1.0, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'AUSPOST_HANDLING_FEE_TYPE', in_array($handlingType, array('none', 'flat', 'percent')) ? $handlingType : 'none', 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'AUSPOST_HANDLING_FEE_AMOUNT', (float)$handlingAmount >= 0 ? (float)$handlingAmount : 0.0, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'AUSPOST_SHIPPING_VAT_RATE', (float)$vatRate >= 0 ? (float)$vatRate : 10.0, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'AUSPOST_SHIPPING_PRODUCT_ID', (int)$shippingProductId, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'AUSPOST_ENABLE_INTL', $enableIntl ? 1 : 0, 'chaine', 0, '', $conf->entity);

        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    }
}

/*
 * View
 */
$page_name = $langs->trans("AusPostSetup");
llxHeader('', $page_name, '', '', 0, 0, array('/auspost/js/auspost.js'), array('/auspost/css/auspost.css'));

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($page_name, $linkback, 'title_setup');

$head = auspost_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $langs->trans("AusPostSetup"), -1, 'fa-truck');

print '<script type="text/javascript">';
print 'window.auspost_ajax_url = "' . dol_escape_js(dol_buildpath('/auspost/ajax/calculate.php', 1)) . '";';
print 'window.auspost_token = "' . dol_escape_js(newToken()) . '";';
print '</script>';

// Info banner
print '<div class="info-box auspost-banner">';
print '<div class="auspost-banner-icon"><i class="fa fa-truck fa-2x"></i></div>';
print '<div class="auspost-banner-text">';
print '<strong>' . $langs->trans("AusPostPacApiNotice") . '</strong><br>';
print $langs->trans("AusPostApiKeyHelp", '<a href="https://developers.auspost.com.au/apis/pac/" target="_blank" rel="noopener noreferrer"><strong>Australia Post Developer Centre (PAC API)</strong></a>');
print '</div>';
print '</div><br>';

$apiKey             = getDolGlobalString('AUSPOST_API_KEY');
$apiBaseUrl         = getDolGlobalString('AUSPOST_API_BASE_URL', 'https://digitalapi.auspost.com.au');
$senderPostcode     = getDolGlobalString('AUSPOST_DEFAULT_SENDER_POSTCODE', auspost_get_sender_postcode($mysoc));
$defaultLength      = getDolGlobalString('AUSPOST_DEFAULT_LENGTH', '22');
$defaultWidth       = getDolGlobalString('AUSPOST_DEFAULT_WIDTH', '16');
$defaultHeight      = getDolGlobalString('AUSPOST_DEFAULT_HEIGHT', '8');
$defaultWeight      = getDolGlobalString('AUSPOST_DEFAULT_WEIGHT', '1.0');
$handlingType       = getDolGlobalString('AUSPOST_HANDLING_FEE_TYPE', 'none');
$handlingAmount     = getDolGlobalString('AUSPOST_HANDLING_FEE_AMOUNT', '0.0');
$vatRate            = getDolGlobalString('AUSPOST_SHIPPING_VAT_RATE', '10.0');
$shippingProductId  = getDolGlobalInt('AUSPOST_SHIPPING_PRODUCT_ID', 0);
$enableIntl         = getDolGlobalInt('AUSPOST_ENABLE_INTL', 1);

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save">';

// 1. API Configuration Section
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . $langs->trans("AusPostApiCredentials") . '</th></tr>';

// API Key
print '<tr class="oddeven">';
print '<td class="titlefieldfield required"><label for="AUSPOST_API_KEY">' . $langs->trans("AusPostApiKey") . '</label></td>';
print '<td>';
print '<input type="password" class="minwidth300" id="AUSPOST_API_KEY" name="AUSPOST_API_KEY" value="' . dol_escape_htmltag($apiKey) . '" autocomplete="off"> ';
print '<button type="button" class="button button-toggle-vis" onclick="auspostToggleKeyVis()"><i class="fa fa-eye" id="auspost-key-eye"></i></button> ';
print '<button type="button" class="button butAction" id="auspost-btn-test-conn" onclick="auspostTestApiConnection()"><i class="fa fa-plug"></i> ' . $langs->trans("AusPostTestConnection") . '</button>';
print '<div id="auspost-test-result" style="margin-top: 8px; display: none;"></div>';
print '</td></tr>';

// Base URL
print '<tr class="oddeven">';
print '<td><label for="AUSPOST_API_BASE_URL">' . $langs->trans("AusPostApiBaseUrl") . '</label></td>';
print '<td><input type="text" class="minwidth300" id="AUSPOST_API_BASE_URL" name="AUSPOST_API_BASE_URL" value="' . dol_escape_htmltag($apiBaseUrl) . '"> ';
print '<span class="opacitymedium">(' . $langs->trans("AusPostApiBaseUrlHelp") . ')</span></td>';
print '</tr>';

// 2. Sender / Origin Defaults
print '<tr class="liste_titre"><th colspan="2">' . $langs->trans("AusPostSenderDefaults") . '</th></tr>';

print '<tr class="oddeven">';
print '<td class="required"><label for="AUSPOST_DEFAULT_SENDER_POSTCODE">' . $langs->trans("AusPostDefaultSenderPostcode") . '</label></td>';
print '<td><input type="text" class="width100" id="AUSPOST_DEFAULT_SENDER_POSTCODE" name="AUSPOST_DEFAULT_SENDER_POSTCODE" maxlength="4" value="' . dol_escape_htmltag($senderPostcode) . '"> ';
print '<span class="opacitymedium">' . $langs->trans("AusPostSenderPostcodeHelp", !empty($mysoc->zip) ? $mysoc->zip : 'N/A') . '</span></td>';
print '</tr>';

// 3. Package Defaults
print '<tr class="liste_titre"><th colspan="2">' . $langs->trans("AusPostPackageDefaults") . '</th></tr>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans("AusPostDefaultDimensions") . ' (cm)</td>';
print '<td>';
print '<span class="inline-block">' . $langs->trans("Length") . ': <input type="number" step="0.1" class="width75" name="AUSPOST_DEFAULT_LENGTH" value="' . dol_escape_htmltag($defaultLength) . '"> cm</span> &nbsp; ';
print '<span class="inline-block">' . $langs->trans("Width") . ': <input type="number" step="0.1" class="width75" name="AUSPOST_DEFAULT_WIDTH" value="' . dol_escape_htmltag($defaultWidth) . '"> cm</span> &nbsp; ';
print '<span class="inline-block">' . $langs->trans("Height") . ': <input type="number" step="0.1" class="width75" name="AUSPOST_DEFAULT_HEIGHT" value="' . dol_escape_htmltag($defaultHeight) . '"> cm</span>';
print '<br><span class="opacitymedium">' . $langs->trans("AusPostDimensionsHelp") . '</span>';
print '</td></tr>';

print '<tr class="oddeven">';
print '<td><label for="AUSPOST_DEFAULT_WEIGHT">' . $langs->trans("AusPostDefaultWeight") . ' (kg)</label></td>';
print '<td><input type="number" step="0.05" class="width100" id="AUSPOST_DEFAULT_WEIGHT" name="AUSPOST_DEFAULT_WEIGHT" value="' . dol_escape_htmltag($defaultWeight) . '"> kg ';
print '<span class="opacitymedium">' . $langs->trans("AusPostWeightHelp") . '</span></td>';
print '</tr>';

// 4. Handling & Pricing Rules
print '<tr class="liste_titre"><th colspan="2">' . $langs->trans("AusPostPricingMarkup") . '</th></tr>';

print '<tr class="oddeven">';
print '<td><label for="AUSPOST_HANDLING_FEE_TYPE">' . $langs->trans("AusPostMarkupType") . '</label></td>';
print '<td>';
print '<select id="AUSPOST_HANDLING_FEE_TYPE" name="AUSPOST_HANDLING_FEE_TYPE" class="flat">';
print '<option value="none"' . ($handlingType == 'none' ? ' selected' : '') . '>' . $langs->trans("AusPostMarkupNone") . '</option>';
print '<option value="flat"' . ($handlingType == 'flat' ? ' selected' : '') . '>' . $langs->trans("AusPostMarkupFlat") . '</option>';
print '<option value="percent"' . ($handlingType == 'percent' ? ' selected' : '') . '>' . $langs->trans("AusPostMarkupPercent") . '</option>';
print '</select> &nbsp; ';
print '<label for="AUSPOST_HANDLING_FEE_AMOUNT">' . $langs->trans("Amount") . ' / %: </label>';
print '<input type="number" step="0.01" class="width100" id="AUSPOST_HANDLING_FEE_AMOUNT" name="AUSPOST_HANDLING_FEE_AMOUNT" value="' . dol_escape_htmltag($handlingAmount) . '">';
print '</td></tr>';

print '<tr class="oddeven">';
print '<td><label for="AUSPOST_SHIPPING_VAT_RATE">' . $langs->trans("AusPostShippingVatRate") . '</label></td>';
print '<td><input type="number" step="0.1" class="width100" id="AUSPOST_SHIPPING_VAT_RATE" name="AUSPOST_SHIPPING_VAT_RATE" value="' . dol_escape_htmltag($vatRate) . '"> % ';
print '<span class="opacitymedium">' . $langs->trans("AusPostVatHelp") . '</span></td>';
print '</tr>';

// Link to predefined product/service
print '<tr class="oddeven">';
print '<td><label for="AUSPOST_SHIPPING_PRODUCT_ID">' . $langs->trans("AusPostLinkedProduct") . '</label></td>';
print '<td>';
require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
$formproduct = new FormProduct($db);
print $formproduct->select_produits($shippingProductId, 'AUSPOST_SHIPPING_PRODUCT_ID', 1, 0, 0, 1, 2, '', 0, array(), 0, '1', 0, 'minwidth300');
print ' <span class="opacitymedium">' . $langs->trans("AusPostLinkedProductHelp") . '</span>';
print '</td></tr>';

// 5. International options
print '<tr class="liste_titre"><th colspan="2">' . $langs->trans("AusPostInternationalDelivery") . '</th></tr>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans("AusPostEnableInternational") . '</td>';
print '<td>';
print '<input type="checkbox" id="AUSPOST_ENABLE_INTL" name="AUSPOST_ENABLE_INTL" value="1"' . ($enableIntl ? ' checked' : '') . '> ';
print '<label for="AUSPOST_ENABLE_INTL">' . $langs->trans("AusPostEnableInternationalHelp") . '</label>';
print '</td></tr>';

print '</table>';

print '<div class="tabsAction">';
print '<input type="submit" class="button button-save" value="' . $langs->trans("Save") . '">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
