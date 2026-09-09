<?php
/**
 * Library of functions for Australia Post Dolibarr Plugin
 *
 * @package   AusPost
 * @author    Kye McIntyre
 * @license   GPL-3.0-or-later
 */

/**
 * Prepare array with list of tabs for admin page
 *
 * @return array Array of tabs
 */
function auspost_admin_prepare_head()
{
    global $langs, $conf;

    $langs->load("auspost@auspost");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/auspost/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath("/auspost/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Complete_head from Dolibarr hooks
    complete_head_from_modules($conf, $langs, null, $head, $h, 'auspost');

    return $head;
}

/**
 * Get configured Australia Post API Key
 *
 * @return string API key
 */
function auspost_get_api_key()
{
    if (function_exists('getDolGlobalString')) {
        return trim(getDolGlobalString('AUSPOST_API_KEY', ''));
    }
    global $conf;
    return isset($conf->global->AUSPOST_API_KEY) ? trim($conf->global->AUSPOST_API_KEY) : '';
}

/**
 * Get configured Australia Post API Base URL
 *
 * @return string Base API URL
 */
function auspost_get_api_base_url()
{
    $default = 'https://digitalapi.auspost.com.au';
    if (function_exists('getDolGlobalString')) {
        $url = trim(getDolGlobalString('AUSPOST_API_BASE_URL', $default));
        return !empty($url) ? rtrim($url, '/') : $default;
    }
    return $default;
}

/**
 * Get sender postcode (defaults to configured sender postcode, or company postcode)
 *
 * @param object|null $mysoc Dolibarr company object
 * @return string Postcode
 */
function auspost_get_sender_postcode($mysoc = null)
{
    $postcode = '';
    if (function_exists('getDolGlobalString')) {
        $postcode = trim(getDolGlobalString('AUSPOST_DEFAULT_SENDER_POSTCODE', ''));
    }

    if (empty($postcode) && !empty($mysoc) && !empty($mysoc->zip)) {
        $postcode = trim($mysoc->zip);
    }

    if (empty($postcode)) {
        global $mysoc;
        if (!empty($mysoc) && !empty($mysoc->zip)) {
            $postcode = trim($mysoc->zip);
        }
    }

    // Default fallback to Sydney CBD if nothing configured
    return !empty($postcode) ? $postcode : '2000';
}

/**
 * Calculate final price applying markup/handling fee rules
 *
 * @param float  $basePrice    Original price from AusPost
 * @param string $markupType   'none', 'flat', or 'percent'
 * @param float  $markupAmount Value of the markup
 * @return float Final price rounded to 2 decimal places
 */
function auspost_calculate_markup($basePrice, $markupType = 'none', $markupAmount = 0.0)
{
    $basePrice = (float)$basePrice;
    $markupAmount = (float)$markupAmount;

    if ($basePrice <= 0) {
        return 0.0;
    }

    switch ($markupType) {
        case 'flat':
            $final = $basePrice + max(0.0, $markupAmount);
            break;
        case 'percent':
            $multiplier = 1.0 + (max(0.0, $markupAmount) / 100.0);
            $final = $basePrice * $multiplier;
            break;
        case 'none':
        default:
            $final = $basePrice;
            break;
    }

    return round($final, 2);
}

/**
 * Convert weight in Dolibarr unit scale to kilograms (kg)
 *
 * Dolibarr standard unit scale for weight (field fk_unit or scale exponent):
 *  0 = kg
 * -3 = g
 * -6 = mg
 *  3 = ton
 * 98 = oz (28.3495 g = 0.0283495 kg)
 * 99 = lb (453.592 g = 0.453592 kg)
 *
 * @param float     $weight Weight value
 * @param int|float $unit   Dolibarr unit scale (e.g. 0, -3, 98, 99)
 * @return float Weight in kilograms
 */
function auspost_convert_weight_to_kg($weight, $unit = 0)
{
    $weight = (float)$weight;
    if ($weight <= 0) {
        return 0.0;
    }

    $unit = (int)$unit;

    switch ($unit) {
        case 0:
            return $weight; // kg
        case -3:
            return $weight / 1000.0; // grams to kg
        case -6:
            return $weight / 1000000.0; // mg to kg
        case 3:
            return $weight * 1000.0; // ton to kg
        case 98:
            return $weight * 0.0283495; // ounces to kg
        case 99:
            return $weight * 0.453592; // pounds to kg
        default:
            // If scale is an exponent (e.g. -3 for 10^-3)
            if ($unit < 0 && $unit >= -6) {
                return $weight * pow(10, $unit);
            }
            return $weight;
    }
}

/**
 * Convert dimension in Dolibarr unit scale to centimeters (cm)
 *
 * Dolibarr standard unit scale for length/dimensions:
 *  0 = m
 * -1 = dm
 * -2 = cm
 * -3 = mm
 * 98 = in (2.54 cm)
 * 99 = ft (30.48 cm)
 *
 * @param float     $dim  Dimension value
 * @param int|float $unit Dolibarr unit scale
 * @return float Dimension in centimeters
 */
function auspost_convert_dim_to_cm($dim, $unit = -2)
{
    $dim = (float)$dim;
    if ($dim <= 0) {
        return 0.0;
    }

    $unit = (int)$unit;

    switch ($unit) {
        case -2:
            return $dim; // cm
        case 0:
            return $dim * 100.0; // m to cm
        case -1:
            return $dim * 10.0; // dm to cm
        case -3:
            return $dim / 10.0; // mm to cm
        case 98:
            return $dim * 2.54; // inch to cm
        case 99:
            return $dim * 30.48; // foot to cm
        default:
            if ($unit > -2) {
                return $dim * pow(10, $unit + 2);
            } elseif ($unit < -2 && $unit >= -4) {
                return $dim * pow(10, $unit + 2);
            }
            return $dim;
    }
}

/**
 * Calculate volumetric / cubic weight for Australia Post
 * Formula: Length (m) * Width (m) * Height (m) * 250 kg/m3
 * Or in cm: (Length * Width * Height) / 4000
 *
 * @param float $lengthCm Length in cm
 * @param float $widthCm  Width in cm
 * @param float $heightCm Height in cm
 * @return float Volumetric weight in kg
 */
function auspost_calculate_cubic_weight($lengthCm, $widthCm, $heightCm)
{
    $l = max(0.0, (float)$lengthCm);
    $w = max(0.0, (float)$widthCm);
    $h = max(0.0, (float)$heightCm);

    if ($l <= 0 || $w <= 0 || $h <= 0) {
        return 0.0;
    }

    // (L * W * H in cm) / 4000 = Cubic Weight in kg
    return round(($l * $w * $h) / 4000.0, 3);
}

/**
 * Get billable weight (the higher of actual weight and cubic weight)
 *
 * @param float $actualWeightKg Actual weight in kg
 * @param float $lengthCm       Length in cm
 * @param float $widthCm        Width in cm
 * @param float $heightCm       Height in cm
 * @return float Billable weight in kg
 */
function auspost_get_billable_weight($actualWeightKg, $lengthCm, $widthCm, $heightCm)
{
    $actual = max(0.0, (float)$actualWeightKg);
    $cubic = auspost_calculate_cubic_weight($lengthCm, $widthCm, $heightCm);
    return max($actual, $cubic);
}
