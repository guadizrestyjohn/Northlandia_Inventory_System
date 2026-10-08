<?php
$categories = $categories ?? [];
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Reports</h3>
    </div>

    <div class="card-body">
        <p class="text-muted">
            Generate read-only inventory, stock movement, and Order / Job Order reports using the selected filters.
        </p>

        <form id="reports-filter-form" class="mb-4">
            <div class="form-row align-items-end">
                <div class="form-group col-lg-3 col-md-6">
                    <label for="reports-type">Report Type</label>
                    <select class="form-control" id="reports-type" name="type">
                        <option value="inventory">Inventory Report</option>
                        <option value="movements">Inventory Movement Report</option>
                        <option value="orders">Order / Job Order Report</option>
                    </select>
                </div>

                <div class="form-group col-lg-3 col-md-6" data-report-filter="keyword">
                    <label for="reports-keyword">Keyword</label>
                    <input
                        class="form-control"
                        id="reports-keyword"
                        name="keyword"
                        placeholder="Item, reference, product, user..."
                    >
                </div>

                <div class="form-group col-lg-2 col-md-6" data-report-filter="category">
                    <label for="reports-category">Category</label>
                    <select class="form-control" id="reports-category" name="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category->category_id ?>">
                                <?= htmlspecialchars((string) $category->category_name, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group col-lg-2 col-md-6" data-report-filter="status">
                    <label for="reports-status" id="reports-status-label">Status</label>
                    <select class="form-control" id="reports-status" name="status">
                        <option value="">All Statuses</option>
                    </select>
                </div>

                <div class="form-group col-lg-2 col-md-6" data-report-filter="customer">
                    <label for="reports-customer">Customer</label>
                    <input class="form-control" id="reports-customer" name="customer">
                </div>

                <div class="form-group col-lg-2 col-md-6" data-report-filter="start-date">
                    <label for="reports-start-date">From Date</label>
                    <input class="form-control" id="reports-start-date" name="start_date" type="date">
                </div>

                <div class="form-group col-lg-2 col-md-6" data-report-filter="end-date">
                    <label for="reports-end-date">To Date</label>
                    <input class="form-control" id="reports-end-date" name="end_date" type="date">
                </div>

                <div class="form-group col-auto">
                    <button type="submit" class="btn btn-primary" id="reports-run">
                        <i class="fas fa-search"></i> Generate Report
                    </button>
                    <button type="reset" class="btn btn-outline-secondary" id="reports-reset">
                        Clear
                    </button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table id="reports-table" class="table table-bordered table-hover">
                <thead><tr></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
