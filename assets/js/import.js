(function ($) {
    'use strict';

    var wb = null;
    var groupedRows = [];
    var config = {
        sales_week: {
            hint: 'Cot 1 ma hang, cot 2 ten hang, cot 4 tong so luong. Khi luu chi cap nhat cot SL ban 1 tuan lien ke.',
            headers: ['Ma hang', 'Ten hang', 'SL ban 1 tuan'],
            preview: function (row) { return [row.sku, row.name, row.qty]; }
        },
        sales_month: {
            hint: 'Cot 1 ma hang, cot 2 ten hang, cot 4 tong so luong. Khi luu chi cap nhat cot SL ban thang lien ke.',
            headers: ['Ma hang', 'Ten hang', 'SL ban thang lien ke'],
            preview: function (row) { return [row.sku, row.name, row.qty]; }
        },
        shop_analysis: {
            hint: 'Cot 2 ma hang, cot 3 ten hang, cot 5 so luong ton, cot 9 SL di duong, cot 10 ton max. Khi luu cap nhat 3 cot toan shop.',
            headers: ['Ma hang', 'Ten hang', 'Ton shop', 'Di duong shop', 'Ton max shop'],
            preview: function (row) { return [row.sku, row.name, row.stock_qty, row.in_transit_qty, row.max_qty]; }
        },
        warehouse_analysis: {
            hint: 'Cot 2 ma hang, cot 3 ten hang, cot 5 so luong ton, cot 9 SL di duong, cot 10 ton max. Khi luu cap nhat 3 cot toan kho.',
            headers: ['Ma hang', 'Ten hang', 'Ton kho', 'Di duong kho', 'Ton max kho'],
            preview: function (row) { return [row.sku, row.name, row.stock_qty, row.in_transit_qty, row.max_qty]; }
        }
    };

    function fmt(value) {
        var number = Number(value || 0);
        return number.toLocaleString('en-US', { maximumFractionDigits: 3 });
    }

    function setStatus(text, isError) {
        $('#tgs-htsoft-pa-status').text(text || '').toggleClass('text-danger', !!isError);
    }

    function setStats(total, grouped, skipped) {
        $('#tgs-htsoft-pa-total').text(fmt(total));
        $('#tgs-htsoft-pa-grouped').text(fmt(grouped));
        $('#tgs-htsoft-pa-skipped').text(fmt(skipped));
    }

    function selectedType() {
        return $('#tgs-htsoft-pa-type').val() || 'sales_week';
    }

    function normalizeSku(value) {
        return String(value || '').trim().toUpperCase();
    }

    function compactText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[đĐ]/g, 'd')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '');
    }

    function isHeaderSku(value) {
        var compact = compactText(value);
        return ['mahang', 'masp', 'masanpham', 'sku', 'code', 'productcode'].indexOf(compact) !== -1;
    }

    function num(value) {
        if (value === null || value === undefined || value === '') {
            return 0;
        }
        if (typeof value === 'string') {
            value = value.replace(/\s+/g, '');
            var comma = value.lastIndexOf(',');
            var dot = value.lastIndexOf('.');
            if (comma >= 0 && dot >= 0) {
                value = comma > dot
                    ? value.replace(/\./g, '').replace(',', '.')
                    : value.replace(/,/g, '');
            } else if (comma >= 0) {
                value = value.replace(',', '.');
            }
            value = value.replace(/[^0-9.\-]/g, '');
        }
        var parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function firstName(current, value) {
        value = String(value || '').trim();
        return current || value;
    }

    function parseRows(rows, type) {
        var map = {};
        var skipped = 0;

        rows.forEach(function (r) {
            if (!r || !r.length) {
                skipped++;
                return;
            }

            var sku = '';
            var name = '';
            var target;

            if (type === 'sales_week' || type === 'sales_month') {
                sku = normalizeSku(r[0]);
                name = String(r[1] || '').trim();
                if (!sku || isHeaderSku(sku)) {
                    skipped++;
                    return;
                }
                target = map[sku] || { sku: sku, name: '', qty: 0 };
                target.name = firstName(target.name, name);
                target.qty += num(r[3]);
                map[sku] = target;
                return;
            }

            sku = normalizeSku(r[1]);
            name = String(r[2] || '').trim();
            if (!sku || isHeaderSku(sku)) {
                skipped++;
                return;
            }

            target = map[sku] || { sku: sku, name: '', stock_qty: 0, in_transit_qty: 0, max_qty: 0 };
            target.name = firstName(target.name, name);
            target.stock_qty += num(r[4]);
            target.in_transit_qty += num(r[8]);
            target.max_qty += num(r[9]);
            map[sku] = target;
        });

        return {
            total: rows.length,
            skipped: skipped,
            rows: Object.keys(map).sort().map(function (sku) { return map[sku]; })
        };
    }

    function renderPreview() {
        var type = selectedType();
        var cfg = config[type];
        var head = '<tr>' + cfg.headers.map(function (h) {
            return '<th>' + escapeHtml(h) + '</th>';
        }).join('') + '</tr>';
        $('#tgs-htsoft-pa-preview-head').html(head);

        if (!groupedRows.length) {
            $('#tgs-htsoft-pa-preview-body').html('<tr><td colspan="' + cfg.headers.length + '" class="text-muted">Chua co du lieu preview.</td></tr>');
            return;
        }

        var html = groupedRows.slice(0, 300).map(function (row) {
            return '<tr>' + cfg.preview(row).map(function (value, index) {
                var numeric = index >= 2;
                return '<td class="' + (numeric ? 'tgs-htsoft-pa-num' : '') + '">' + escapeHtml(numeric ? fmt(value) : value) + '</td>';
            }).join('') + '</tr>';
        }).join('');
        $('#tgs-htsoft-pa-preview-body').html(html);
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function updateHint() {
        $('#tgs-htsoft-pa-hint').text(config[selectedType()].hint);
    }

    function resetWorkbook() {
        wb = null;
        groupedRows = [];
        $('#tgs-htsoft-pa-sheet').prop('disabled', true).html('<option value="">Chua chon file</option>');
        $('#tgs-htsoft-pa-parse').prop('disabled', true);
        $('#tgs-htsoft-pa-save').prop('disabled', true);
        setStats(0, 0, 0);
        renderPreview();
    }

    function readFile(file) {
        if (typeof XLSX === 'undefined') {
            alert('Thu vien SheetJS chua tai xong. Vui long thu lai.');
            return;
        }

        var reader = new FileReader();
        setStatus('Dang doc file...');
        reader.onload = function (event) {
            try {
                wb = XLSX.read(event.target.result, { type: 'array' });
                var options = wb.SheetNames.map(function (name, idx) {
                    return '<option value="' + idx + '">' + escapeHtml(name) + '</option>';
                }).join('');
                $('#tgs-htsoft-pa-sheet').prop('disabled', false).html(options);
                $('#tgs-htsoft-pa-parse').prop('disabled', false);
                setStatus('Da doc file. Chon sheet roi bam Phan tich.');
            } catch (error) {
                resetWorkbook();
                setStatus('Khong doc duoc file: ' + error.message, true);
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function parseSelectedSheet() {
        if (!wb) {
            return;
        }
        var sheetIndex = parseInt($('#tgs-htsoft-pa-sheet').val(), 10) || 0;
        var sheetName = wb.SheetNames[sheetIndex];
        var sheet = wb.Sheets[sheetName];
        var rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: false });
        var parsed = parseRows(rows, selectedType());
        groupedRows = parsed.rows;
        setStats(parsed.total, groupedRows.length, parsed.skipped);
        renderPreview();
        $('#tgs-htsoft-pa-save').prop('disabled', groupedRows.length < 1);
        setStatus(groupedRows.length ? 'Da group xong theo ma hang.' : 'Khong co ma hang hop le.', groupedRows.length < 1);
    }

    function saveRows() {
        if (!groupedRows.length) {
            alert('Chua co du lieu de luu.');
            return;
        }

        if (!window.confirm('Luu ' + fmt(groupedRows.length) + ' ma hang vao DB?')) {
            return;
        }

        var $btn = $('#tgs-htsoft-pa-save');
        var original = $btn.html();
        $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin"></i> Dang luu');
        setStatus('Dang luu vao DB...');

        $.post(tgsHtsoftPurchaseAnalysis.ajaxUrl, {
            action: 'tgs_htsoft_pa_save_rows',
            nonce: tgsHtsoftPurchaseAnalysis.nonce,
            import_type: selectedType(),
            rows: JSON.stringify(groupedRows)
        }).done(function (res) {
            if (!res || !res.success) {
                setStatus((res && res.data && res.data.message) || 'Luu that bai.', true);
                return;
            }
            setStatus(res.data.message || 'Da luu xong.');
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : 'Luu that bai.';
            setStatus(message, true);
        }).always(function () {
            $btn.prop('disabled', false).html(original);
        });
    }

    $('#tgs-htsoft-pa-type').on('change', function () {
        groupedRows = [];
        updateHint();
        setStats(0, 0, 0);
        renderPreview();
        $('#tgs-htsoft-pa-save').prop('disabled', true);
        setStatus('');
    });

    $('#tgs-htsoft-pa-file').on('change', function (event) {
        resetWorkbook();
        if (event.target.files && event.target.files[0]) {
            readFile(event.target.files[0]);
        }
    });

    $('#tgs-htsoft-pa-parse').on('click', parseSelectedSheet);
    $('#tgs-htsoft-pa-save').on('click', saveRows);

    updateHint();
    renderPreview();
})(jQuery);
