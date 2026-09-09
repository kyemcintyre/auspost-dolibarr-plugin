<?php
/**
 * Australia Post Plugin About / Help Page
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

// Security check
if (!$user->admin && empty($user->rights->auspost->setup)) {
    accessforbidden();
}

$langs->loadLangs(array("admin", "auspost@auspost"));

$page_name = $langs->trans("About");
llxHeader('', $page_name);

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($page_name, $linkback, 'title_setup');

$head = auspost_admin_prepare_head();
print dol_get_fiche_head($head, 'about', $langs->trans("AusPostSetup"), -1, 'fa-truck');

require_once __DIR__ . '/../core/modules/modAuspost.class.php';
$modAuspost = new modAuspost($db);
$moduleVersion = !empty($modAuspost->version) ? $modAuspost->version : '1.0.0';

print '<div class="fichecenter">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . $langs->trans("AusPostModuleInformation") . '</th></tr>';

print '<tr class="oddeven"><td class="titlefieldfield">' . $langs->trans("Name") . '</td><td>Australia Post Shipping Calculator (PAC API)</td></tr>';
print '<tr class="oddeven"><td>' . $langs->trans("Version") . '</td><td>' . dol_escape_htmltag($moduleVersion) . '</td></tr>';
print '<tr class="oddeven"><td>' . $langs->trans("Compatibility") . '</td><td>Dolibarr 21.0.2 (and 16.0 - 21.x), PHP 8.1 - 8.3+</td></tr>';
print '<tr class="oddeven"><td>' . $langs->trans("Author") . '</td><td>Kye McIntyre</td></tr>';
print '<tr class="oddeven"><td>' . $langs->trans("License") . '</td><td>GPL-3.0-or-later</td></tr>';
print '<tr class="oddeven"><td>' . $langs->trans("Australia Post API") . '</td><td>Postage Assessment Calculator (PAC) API</td></tr>';
print '</table>';

print '<br>';

print '<div class="info-box">';
print '<h3>' . $langs->trans("AusPostHowItWorks") . '</h3>';
print '<p>' . $langs->trans("AusPostHowItWorksText") . '</p>';
print '<ol>';
print '<li>' . $langs->trans("AusPostStep1") . '</li>';
print '<li>' . $langs->trans("AusPostStep2") . '</li>';
print '<li>' . $langs->trans("AusPostStep3") . '</li>';
print '<li>' . $langs->trans("AusPostStep4") . '</li>';
print '</ol>';

print '<h4>' . $langs->trans("UsefulLinks") . '</h4>';
print '<ul>';
print '<li><a href="https://developers.auspost.com.au/" target="_blank" rel="noopener noreferrer">Australia Post Developer Centre</a></li>';
print '<li><a href="https://developers.auspost.com.au/apis/pac/" target="_blank" rel="noopener noreferrer">Postage Assessment Calculator (PAC) API Reference</a></li>';
print '<li><a href="https://auspost.com.au/parcels-mail/calculate-postage-delivery-times" target="_blank" rel="noopener noreferrer">Australia Post Public Postage Calculator</a></li>';
print '</ul>';
print '</div>';

print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
