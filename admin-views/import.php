<?php
if (!defined('ABSPATH')) {
    exit;
}

$analysis_url = admin_url('admin.php?page=tgs-shop-management&view=purchase-analysis');
?>

<div class="tgs-htsoft-pa-page" id="tgs-htsoft-pa-app">
    <div class="tgs-htsoft-pa-head">
        <div>
            <h1>Phan tich cac file Excel tu HTSoft</h1>
            <p>Nhap rieng 4 loai file, chon sheet, group theo ma hang roi cap nhat vao bang du lieu global.</p>
        </div>
        <a href="<?php echo esc_url($analysis_url); ?>" class="btn btn-outline-primary">
            <i class="bx bx-table"></i> Xem Phan tich mua hang
        </a>
    </div>

    <div class="tgs-htsoft-pa-grid">
        <section class="tgs-htsoft-pa-panel">
            <div class="tgs-htsoft-pa-field">
                <label>Loai file</label>
                <select id="tgs-htsoft-pa-type" class="form-select">
                    <option value="sales_week">1. Doanh so ban toan shop theo tuan lien ke</option>
                    <option value="sales_month">2. Doanh so ban toan shop theo thang lien ke</option>
                    <option value="shop_analysis">3. Phan tich toan shop</option>
                    <option value="warehouse_analysis">4. Phan tich toan kho</option>
                </select>
            </div>

            <div class="tgs-htsoft-pa-field">
                <label>File Excel</label>
                <input id="tgs-htsoft-pa-file" type="file" class="form-control" accept=".xlsx,.xls,.csv">
            </div>

            <div class="tgs-htsoft-pa-field">
                <label>Sheet</label>
                <div class="tgs-htsoft-pa-inline">
                    <select id="tgs-htsoft-pa-sheet" class="form-select" disabled>
                        <option value="">Chua chon file</option>
                    </select>
                    <button type="button" id="tgs-htsoft-pa-parse" class="btn btn-primary" disabled>
                        <i class="bx bx-search-alt"></i> Phan tich
                    </button>
                </div>
            </div>

            <div class="tgs-htsoft-pa-hint" id="tgs-htsoft-pa-hint"></div>
        </section>

        <section class="tgs-htsoft-pa-panel">
            <div class="tgs-htsoft-pa-stats">
                <div>
                    <span>Dong doc duoc</span>
                    <strong id="tgs-htsoft-pa-total">0</strong>
                </div>
                <div>
                    <span>Ma hang sau group</span>
                    <strong id="tgs-htsoft-pa-grouped">0</strong>
                </div>
                <div>
                    <span>Dong bo qua</span>
                    <strong id="tgs-htsoft-pa-skipped">0</strong>
                </div>
            </div>
            <div class="tgs-htsoft-pa-actions">
                <button type="button" id="tgs-htsoft-pa-save" class="btn btn-success" disabled>
                    <i class="bx bx-save"></i> Luu vao DB
                </button>
                <span id="tgs-htsoft-pa-status"></span>
            </div>
        </section>
    </div>

    <section class="tgs-htsoft-pa-panel tgs-htsoft-pa-preview">
        <div class="tgs-htsoft-pa-preview-head">
            <strong>Preview sau khi group theo ma hang</strong>
            <span id="tgs-htsoft-pa-preview-note">Toi da hien 300 dong dau.</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead id="tgs-htsoft-pa-preview-head"></thead>
                <tbody id="tgs-htsoft-pa-preview-body">
                    <tr><td class="text-muted">Chon file va bam Phan tich de xem du lieu.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
