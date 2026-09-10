/**
 * Australia Post Plugin JavaScript
 * Compatible with Dolibarr 21.0.2
 */

(function($) {
    'use strict';

    // Helper to resolve AJAX endpoint URL
    function getAjaxEndpoint() {
        if (window.auspost_ajax_url) {
            return window.auspost_ajax_url;
        }
        if (typeof dol_buildpath !== 'undefined') {
            return dol_buildpath('/auspost/ajax/calculate.php', 1);
        }
        return (window.location.pathname.indexOf('/custom/') !== -1)
            ? '../custom/auspost/ajax/calculate.php'
            : '../../auspost/ajax/calculate.php';
    }

    // Helper to retrieve CSRF token from page
    function getCsrfToken() {
        if (window.auspost_token) {
            return window.auspost_token;
        }
        var $token = $('input[name="token"]');
        if ($token.length && $token.val()) {
            return $token.val();
        }
        return '';
    }

    // Global toggle for API key input field
    window.auspostToggleKeyVis = function() {
        var input = document.getElementById('AUSPOST_API_KEY');
        var icon = document.getElementById('auspost-key-eye');
        if (!input) return;

        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.className = 'fa fa-eye-slash';
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.className = 'fa fa-eye';
            }
        }
    };

    // Test API connection from setup page
    window.auspostTestApiConnection = function() {
        var apiKeyInput = document.getElementById('AUSPOST_API_KEY');
        var apiBaseInput = document.getElementById('AUSPOST_API_BASE_URL');
        var resultDiv = document.getElementById('auspost-test-result');
        var btn = document.getElementById('auspost-btn-test-conn');

        if (!resultDiv) return;

        var apiKey = apiKeyInput ? apiKeyInput.value.trim() : '';
        var apiBase = apiBaseInput ? apiBaseInput.value.trim() : '';

        if (!apiKey) {
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<span class="badge badge-danger"><i class="fa fa-times-circle"></i> Please enter an API key first.</span>';
            return;
        }

        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<span class="opacitymedium"><i class="fa fa-spinner fa-spin"></i> Connecting to Australia Post...</span>';
        if (btn) btn.disabled = true;

        var ajaxUrl = getAjaxEndpoint();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'test_connection',
                token: getCsrfToken(),
                api_key: apiKey,
                api_base_url: apiBase
            }
        }).done(function(res) {
            if (res && res.success) {
                resultDiv.innerHTML = '<div class="badge badge-success" style="padding: 6px 12px; font-size: 0.95em;"><i class="fa fa-check-circle"></i> ' +
                    res.message + ' (' + res.latency_ms + ' ms)</div>';
            } else {
                var msg = (res && res.message) ? res.message : 'Connection failed.';
                resultDiv.innerHTML = '<div class="badge badge-danger" style="padding: 6px 12px; font-size: 0.95em;"><i class="fa fa-times-circle"></i> ' +
                    msg + '</div>';
            }
        }).fail(function(xhr, status, error) {
            var msg = error;
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseText && xhr.responseText.length < 200) {
                msg = xhr.responseText;
            }
            resultDiv.innerHTML = '<div class="badge badge-danger" style="padding: 6px 12px; font-size: 0.95em;"><i class="fa fa-times-circle"></i> Request failed (' + xhr.status + '): ' +
                msg + '</div>';
        }).always(function() {
            if (btn) btn.disabled = false;
        });
    };

    // Country change handler for calculator page
    window.auspostOnCountryChange = function(countryCode) {
        var destRow = document.getElementById('row_dest_postcode');
        if (!destRow) return;
        if (countryCode === 'AU') {
            destRow.style.display = '';
        } else {
            destRow.style.display = 'none';
        }
    };

    // Modal dialog controller for Card Pages (Proposals / Orders / Shipments)
    $(document).ready(function() {
        // Delegate click for AusPost calculation trigger button
        $(document).on('click', '.auspost-calc-trigger', function(e) {
            e.preventDefault();
            var $btn = $(this);

            var docType      = $btn.data('doctype');
            var docId        = $btn.data('docid');
            var fromPostcode = $btn.data('from-postcode') || '2000';
            var toPostcode   = $btn.data('to-postcode') || '';
            var toCountry    = $btn.data('to-country') || 'AU';
            var weight       = $btn.data('weight') || '1.0';
            var length       = $btn.data('length') || '22';
            var width        = $btn.data('width') || '16';
            var height       = $btn.data('height') || '8';

            openAusPostModal({
                docType: docType,
                docId: docId,
                fromPostcode: fromPostcode,
                toPostcode: toPostcode,
                toCountry: toCountry,
                weight: weight,
                length: length,
                width: width,
                height: height
            });
        });
    });

    function openAusPostModal(data) {
        var $overlay = $('#auspost-modal-overlay');

        if ($overlay.length === 0) {
            var modalHtml = [
                '<div id="auspost-modal-overlay" class="auspost-modal-overlay">',
                '  <div class="auspost-modal-container">',
                '    <div class="auspost-modal-header">',
                '      <h3><i class="fa fa-truck auspost-red-icon"></i> Australia Post Shipping Calculator</h3>',
                '      <button type="button" class="auspost-modal-close" id="auspost-modal-close-btn">&times;</button>',
                '    </div>',
                '    <div class="auspost-modal-body">',
                '      <div class="auspost-form-grid">',
                '        <div class="auspost-form-group">',
                '          <label>Origin Postcode (From)</label>',
                '          <input type="text" id="m_auspost_from" maxlength="4">',
                '        </div>',
                '        <div class="auspost-form-group">',
                '          <label>Destination Postcode (To)</label>',
                '          <input type="text" id="m_auspost_to" maxlength="4">',
                '        </div>',
                '        <div class="auspost-form-group">',
                '          <label>Weight (kg)</label>',
                '          <input type="number" step="0.05" id="m_auspost_weight">',
                '        </div>',
                '        <div class="auspost-form-group">',
                '          <label>Dimensions L x W x H (cm)</label>',
                '          <div class="auspost-dim-inputs">',
                '            <input type="number" step="0.1" id="m_auspost_l" placeholder="L">',
                '            <span>&times;</span>',
                '            <input type="number" step="0.1" id="m_auspost_w" placeholder="W">',
                '            <span>&times;</span>',
                '            <input type="number" step="0.1" id="m_auspost_h" placeholder="H">',
                '          </div>',
                '        </div>',
                '      </div>',
                '      <div style="text-align: right; margin-bottom: 15px;">',
                '        <button type="button" class="button butAction" id="m_auspost_btn_recalc"><i class="fa fa-refresh"></i> Recalculate Rates</button>',
                '      </div>',
                '      <div id="m_auspost_results_container"></div>',
                '    </div>',
                '  </div>',
                '</div>'
            ].join('');

            $('body').append(modalHtml);
            $overlay = $('#auspost-modal-overlay');

            // Close events
            $('#auspost-modal-close-btn').on('click', function() {
                $overlay.removeClass('active');
            });

            $overlay.on('click', function(e) {
                if (e.target === this) {
                    $overlay.removeClass('active');
                }
            });

            $('#m_auspost_btn_recalc').on('click', function() {
                fetchRatesForModal();
            });
        }

        // Populate fields
        $('#m_auspost_from').val(data.fromPostcode);
        $('#m_auspost_to').val(data.toPostcode);
        $('#m_auspost_weight').val(data.weight);
        $('#m_auspost_l').val(data.length);
        $('#m_auspost_w').val(data.width);
        $('#m_auspost_h').val(data.height);

        $overlay.data('doctype', data.docType);
        $overlay.data('docid', data.docId);
        $overlay.data('country', data.toCountry);

        $overlay.addClass('active');

        // Trigger rate calculation
        fetchRatesForModal();
    }

    function fetchRatesForModal() {
        var $overlay = $('#auspost-modal-overlay');
        var $resContainer = $('#m_auspost_results_container');

        var fromPostcode = $('#m_auspost_from').val();
        var toPostcode   = $('#m_auspost_to').val();
        var weight       = $('#m_auspost_weight').val();
        var length       = $('#m_auspost_l').val();
        var width        = $('#m_auspost_w').val();
        var height       = $('#m_auspost_h').val();
        var toCountry    = $overlay.data('country') || 'AU';
        var docType      = $overlay.data('doctype');
        var docId        = $overlay.data('docid');

        $resContainer.html(
            '<div class="auspost-loading-wrap">' +
            '  <div class="auspost-spinner"></div>' +
            '  <div>Calculating live Australia Post rates...</div>' +
            '</div>'
        );

        $.ajax({
            url: getAjaxEndpoint(),
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_rates',
                token: getCsrfToken(),
                from_postcode: fromPostcode,
                to_postcode: toPostcode,
                to_country: toCountry,
                weight: weight,
                length: length,
                width: width,
                height: height
            }
        }).done(function(res) {
            if (!res || !res.success) {
                var err = (res && res.message) ? res.message : 'Failed to fetch rates from Australia Post.';
                $resContainer.html('<div class="badge badge-danger" style="display:block; padding: 10px;"><i class="fa fa-exclamation-circle"></i> ' + err + '</div>');
                return;
            }

            if (!res.rates || res.rates.length === 0) {
                $resContainer.html('<div class="badge badge-warning" style="display:block; padding: 10px;">No available Australia Post services found for these parameters.</div>');
                return;
            }

            var html = '';
            html += '<div class="auspost-weights-summary">';
            html += '<span><strong>Weight:</strong> ' + res.actual_weight + ' kg</span> &nbsp;|&nbsp; ';
            html += '<span><strong>Cubic:</strong> ' + res.cubic_weight + ' kg</span> &nbsp;|&nbsp; ';
            html += '<span><strong>Billable:</strong> <span class="badge badge-info">' + res.billable_weight + ' kg</span></span>';
            html += '</div>';

            $.each(res.rates, function(i, rate) {
                var isExpress = (rate.code.indexOf('EXPRESS') !== -1 || rate.code.indexOf('EXP') !== -1);
                var badgeClass = isExpress ? 'auspost-badge-express' : 'auspost-badge-standard';

                html += '<div class="auspost-card-rate">';
                html += '  <div class="auspost-card-rate-info">';
                html += '    <div class="auspost-card-rate-title"><span class="badge ' + badgeClass + '">' + rate.name + '</span></div>';
                html += '    <div class="opacitymedium small">' + rate.code + (rate.markup > 0 ? ' (Includes +' + rate.markup + ' handling fee)' : '') + '</div>';
                html += '  </div>';
                html += '  <div class="auspost-card-rate-pricing">';
                html += '    <div class="auspost-price-cost">Our cost: ' + rate.formatted_base + '</div>';
                html += '    <div class="auspost-price-ttc">Sell: ' + rate.formatted_ttc + ' <span class="small" style="font-size:0.6em; color:#64748b;">(incl. GST)</span></div>';
                html += '    <div class="auspost-price-ht">' + rate.formatted_ht + ' excl. tax</div>';
                html += '    <button type="button" class="button butAction auspost-btn-apply" ' +
                    'data-service-code="' + rate.code + '" ' +
                    'data-service-name="' + rate.name + '" ' +
                    'data-price-ht="' + rate.price_ht + '" ' +
                    'data-base-price="' + rate.base_price + '" ' +
                    'data-vat-rate="' + rate.vat_rate + '" ' +
                    'data-weight="' + res.actual_weight + '" ' +
                    'data-length="' + res.length + '" ' +
                    'data-width="' + res.width + '" ' +
                    'data-height="' + res.height + '" ' +
                    'data-doctype="' + docType + '" ' +
                    'data-docid="' + docId + '">';
                html += '<i class="fa fa-plus-circle"></i> ' + (docType === 'shipment' ? 'Set as Shipping Mode' : 'Apply to ' + (docType === 'propal' ? 'Proposal' : 'Order'));
                html += '</button>';
                html += '  </div>';
                html += '</div>';
            });

            $resContainer.html(html);

            // Bind click to apply rate
            $resContainer.find('.auspost-btn-apply').on('click', function() {
                var $applyBtn = $(this);
                applyShippingLine($applyBtn);
            });

        }).fail(function(xhr, status, error) {
            var msg = error;
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseText && xhr.responseText.length < 200) {
                msg = xhr.responseText;
            }
            $resContainer.html('<div class="badge badge-danger" style="display:block; padding: 10px;">Request failed (' + xhr.status + '): ' + msg + '</div>');
        });
    }

    function applyShippingLine($btn) {
        var docType     = $btn.data('doctype');
        var docId       = $btn.data('docid');
        var serviceCode = $btn.data('service-code');
        var serviceName = $btn.data('service-name');
        var priceHt     = $btn.data('price-ht');
        var basePrice   = $btn.data('base-price');
        var vatRate     = $btn.data('vat-rate');
        var weight      = $btn.data('weight');
        var length      = $btn.data('length');
        var width       = $btn.data('width');
        var height      = $btn.data('height');

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');

        $.ajax({
            url: getAjaxEndpoint(),
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'apply_to_document',
                token: getCsrfToken(),
                doctype: docType,
                docid: docId,
                service_code: serviceCode,
                service_name: serviceName,
                price_ht: priceHt,
                base_price: basePrice,
                vat_rate: vatRate,
                weight: weight,
                length: length,
                width: width,
                height: height
            }
        }).done(function(res) {
            if (res && res.success) {
                $btn.html('<i class="fa fa-check"></i> Added!');
                // Reload the parent document page to show the added line
                setTimeout(function() {
                    window.location.reload();
                }, 400);
            } else {
                var err = (res && res.message) ? res.message : 'Could not add shipping line.';
                alert(err);
                $btn.prop('disabled', false).html('<i class="fa fa-plus-circle"></i> Retry');
            }
        }).fail(function(xhr, status, error) {
            var msg = error;
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseText && xhr.responseText.length < 200) {
                msg = xhr.responseText;
            }
            alert('Request error (' + xhr.status + '): ' + msg);
            $btn.prop('disabled', false).html('<i class="fa fa-plus-circle"></i> Retry');
        });
    }

})(jQuery);
