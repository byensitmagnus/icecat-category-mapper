/**
 * Icecat Category Mapper — Admin JavaScript
 * Handles: search, AJAX calls, progress bars, confirmations.
 */

(function ($) {
    'use strict';

    /* ───────────────────────────────────────────────
     *  Toggle add-form visibility
     * ─────────────────────────────────────────────── */

    $('#icm-toggle-add-form').on('click', function () {
        $('#icm-mapping-form-wrapper').slideToggle(200);
    });

    $('#icm-cancel-add').on('click', function () {
        $('#icm-mapping-form-wrapper').slideUp(200);
    });

    /* ───────────────────────────────────────────────
     *  Fallback category row toggle
     * ─────────────────────────────────────────────── */

    $('input[name="icm_fallback_behavior"]').on('change', function () {
        if ($(this).val() === 'fallback') {
            $('#icm-fallback-cat-row').show();
        } else {
            $('#icm-fallback-cat-row').hide();
        }
    });

    /* ───────────────────────────────────────────────
     *  Fetch Icecat categories
     * ─────────────────────────────────────────────── */

    $('#icm-fetch-icecat').on('click', function () {
        var $btn = $(this);
        var $status = $('#icm-cache-status');

        $btn.prop('disabled', true).text(icm_admin.i18n.fetching);

        $.post(icm_admin.ajax_url, {
            action: 'icm_fetch_icecat',
            nonce: icm_admin.nonce
        })
        .done(function (response) {
            if (response.success) {
                $status.text('Icecat cache: ' + response.data.cache_time + ' (' + response.data.count + ' kategorier)');
                $btn.text(icm_admin.i18n.fetch_done);
                setTimeout(function () {
                    $btn.text('Hent Icecat kategorier').prop('disabled', false);
                }, 2000);
            } else {
                alert(icm_admin.i18n.fetch_error + '\n' + (response.data || ''));
                $btn.text('Hent Icecat kategorier').prop('disabled', false);
            }
        })
        .fail(function () {
            alert(icm_admin.i18n.fetch_error);
            $btn.text('Hent Icecat kategorier').prop('disabled', false);
        });
    });

    /* ───────────────────────────────────────────────
     *  Search Icecat categories (from cached list)
     * ─────────────────────────────────────────────── */

    var searchTimer = null;

    $('#icm-search-icecat-btn').on('click', function () {
        var query = $('#icecat_cat_name').val().trim();
        if (query.length < 2) {
            return;
        }
        doIcecatSearch(query);
    });

    // Also trigger search on typing with debounce
    $('#icecat_cat_name').on('input', function () {
        clearTimeout(searchTimer);
        var query = $(this).val().trim();
        if (query.length < 2) {
            $('#icm-icecat-search-results').hide();
            return;
        }
        searchTimer = setTimeout(function () {
            doIcecatSearch(query);
        }, 300);
    });

    function doIcecatSearch(query) {
        $.post(icm_admin.ajax_url, {
            action: 'icm_search_icecat',
            nonce: icm_admin.nonce,
            query: query
        })
        .done(function (response) {
            var $dropdown = $('#icm-icecat-search-results');
            $dropdown.empty();

            if (response.success && response.data.length > 0) {
                response.data.forEach(function (cat) {
                    var $item = $('<div class="icm-search-dropdown-item">')
                        .data('cat', cat)
                        .html(
                            '<strong>' + escHtml(cat.name_en) + '</strong>' +
                            '<span class="icm-search-id">#' + cat.id + '</span>' +
                            (cat.name_da ? '<span class="icm-search-da">' + escHtml(cat.name_da) + '</span>' : '')
                        );
                    $dropdown.append($item);
                });
                $dropdown.show();
            } else {
                $dropdown.html('<div class="icm-search-dropdown-item">Ingen resultater</div>').show();
            }
        });
    }

    // Click on a search result to populate the form
    $(document).on('click', '.icm-search-dropdown-item', function () {
        var cat = $(this).data('cat');
        if (!cat) return;

        $('#icecat_cat_id').val(cat.id);
        $('#icecat_cat_name').val(cat.name_en);
        $('#icecat_cat_name_da').val(cat.name_da || '');
        $('#icm-icecat-search-results').hide();
    });

    // Close dropdown when clicking outside
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#icm-icecat-search-results, #icecat_cat_name, #icm-search-icecat-btn').length) {
            $('#icm-icecat-search-results').hide();
        }
    });

    /* ───────────────────────────────────────────────
     *  Test connection
     * ─────────────────────────────────────────────── */

    $('#icm-test-connection').on('click', function () {
        var $btn = $(this);
        var $result = $('#icm-test-result');

        $btn.prop('disabled', true);
        $result.text(icm_admin.i18n.testing).removeClass('success error');

        $.post(icm_admin.ajax_url, {
            action: 'icm_test_connection',
            nonce: icm_admin.nonce
        })
        .done(function (response) {
            if (response.success) {
                $result.text(icm_admin.i18n.test_ok).addClass('success');
            } else {
                $result.text(response.data || icm_admin.i18n.test_fail).addClass('error');
            }
        })
        .fail(function () {
            $result.text(icm_admin.i18n.test_fail).addClass('error');
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

    /* ───────────────────────────────────────────────
     *  Batch recheck
     * ─────────────────────────────────────────────── */

    var batchTotalRemapped = 0;
    var batchTotalUnmapped = 0;

    $('#icm-batch-recheck-btn').on('click', function () {
        if (!confirm('Er du sikker? Dette genkontrollerer alle produkters kategorier.')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);
        $('#icm-batch-progress').show();
        batchTotalRemapped = 0;
        batchTotalUnmapped = 0;

        doBatchRecheck(0);
    });

    function doBatchRecheck(offset) {
        $.post(icm_admin.ajax_url, {
            action: 'icm_batch_recheck',
            nonce: icm_admin.nonce,
            offset: offset
        })
        .done(function (response) {
            if (!response.success) {
                $('#icm-batch-status').text('Fejl: ' + (response.data || 'Ukendt fejl'));
                $('#icm-batch-recheck-btn').prop('disabled', false);
                return;
            }

            var data = response.data;
            batchTotalRemapped += data.remapped;
            batchTotalUnmapped += data.unmapped;

            var pct = data.total > 0 ? Math.round((data.processed / data.total) * 100) : 100;
            $('#icm-progress-fill').css('width', pct + '%');
            $('#icm-batch-status').text(
                data.processed + ' af ' + data.total + ' produkter behandlet. ' +
                batchTotalRemapped + ' omdirigeret, ' + batchTotalUnmapped + ' umappede.'
            );

            if (data.has_more) {
                doBatchRecheck(data.next_offset);
            } else {
                $('#icm-batch-status').append(' Faerdigt!');
                $('#icm-batch-recheck-btn').prop('disabled', false);
            }
        })
        .fail(function () {
            $('#icm-batch-status').text('AJAX-fejl. Proev igen.');
            $('#icm-batch-recheck-btn').prop('disabled', false);
        });
    }

    /* ───────────────────────────────────────────────
     *  Helper: escape HTML
     * ─────────────────────────────────────────────── */

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})(jQuery);
