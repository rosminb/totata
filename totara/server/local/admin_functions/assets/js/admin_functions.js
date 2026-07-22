/**
 * Interactive JS asset for local_admin_functions plugin.
 * Performs live AJAX table searching, column filtering, Admin Debug modal confirmation toggle, Super Admin Table Selector Modal, and SQL query execution.
 *
 * @package    local_admin_functions
 * @copyright  2026 Rosmin Babu
 */

document.addEventListener('DOMContentLoaded', function() {
    const appLevel1 = document.getElementById('admin-functions-tables-app');
    const ajaxUrl = appLevel1 ? (appLevel1.getAttribute('data-ajax-url') || 'ajax.php') : 'ajax.php';

    // 1. Admin Debug Confirmation Modal Handler
    const debugToggle = document.getElementById('btn-toggle-admin-debug');
    const debugModal = document.getElementById('debug-confirm-modal');
    const debugBackdrop = document.getElementById('debug-modal-backdrop');
    const modalMessage = document.getElementById('debug-modal-message');
    const btnConfirm = document.getElementById('modal-btn-confirm');
    const btnCancel = document.getElementById('modal-btn-cancel');
    const btnCloseX = document.getElementById('modal-close-x');

    let targetDebugState = false;

    function openDebugModal(enable) {
        targetDebugState = enable;
        if (modalMessage) {
            if (enable) {
                modalMessage.innerHTML = 'Are you sure you want to <strong>ENABLE Developer Debug Mode</strong> and show all PHP error messages, notices, and stack traces?';
            } else {
                modalMessage.innerHTML = 'Are you sure you want to <strong>DISABLE Debug Mode</strong> and suppress error displays?';
            }
        }
        if (btnConfirm) {
            btnConfirm.className = enable ? 'btn btn-danger px-4 font-weight-bold' : 'btn btn-primary px-4 font-weight-bold';
            btnConfirm.innerHTML = enable ? '<i class="fa fa-check mr-1"></i> Enable All Errors' : '<i class="fa fa-check mr-1"></i> Disable Debug';
        }
        if (debugModal) {
            debugModal.style.display = 'block';
            debugModal.classList.add('show');
        }
        if (debugBackdrop) {
            debugBackdrop.style.display = 'block';
            debugBackdrop.classList.add('show');
        }
    }

    function closeDebugModal(revertToggle = true) {
        if (debugModal) {
            debugModal.style.display = 'none';
            debugModal.classList.remove('show');
        }
        if (debugBackdrop) {
            debugBackdrop.style.display = 'none';
            debugBackdrop.classList.remove('show');
        }
        if (revertToggle && debugToggle) {
            debugToggle.checked = !targetDebugState;
        }
    }

    if (debugToggle) {
        debugToggle.addEventListener('click', function(e) {
            e.preventDefault();
            const willEnable = !this.checked;
            this.checked = !willEnable;
            openDebugModal(willEnable);
        });
    }

    if (btnCancel) btnCancel.addEventListener('click', function() { closeDebugModal(true); });
    if (btnCloseX) btnCloseX.addEventListener('click', function() { closeDebugModal(true); });

    if (btnConfirm) {
        btnConfirm.addEventListener('click', function() {
            btnConfirm.disabled = true;
            fetch(ajaxUrl + '?action=toggle_debug')
                .then(res => res.json())
                .then(res => {
                    btnConfirm.disabled = false;
                    if (res.success) {
                        if (debugToggle) debugToggle.checked = !!res.debug;
                        closeDebugModal(false);
                        window.location.reload();
                    } else {
                        alert(res.error || 'Failed to update Debug Mode.');
                        closeDebugModal(true);
                    }
                })
                .catch(err => {
                    btnConfirm.disabled = false;
                    console.error('Debug toggle error:', err);
                    closeDebugModal(true);
                });
        });
    }

    // 2. Super Admin Table Selector Modal Handler with Event Delegation
    const tsModal = document.getElementById('table-selector-modal');
    const tsBackdrop = document.getElementById('ts-modal-backdrop');
    const tsCloseX = document.getElementById('ts-modal-close-x');
    const tsCancel = document.getElementById('ts-modal-btn-cancel');
    const tsSave = document.getElementById('ts-modal-btn-save');
    const tsSearch = document.getElementById('ts-modal-search');
    const tsSelectCustomOnly = document.getElementById('ts-select-custom-only');
    const tsSelectAll = document.getElementById('ts-select-all');
    const tsDeselectAll = document.getElementById('ts-deselect-all');
    const tsCountBadge = document.getElementById('ts-selected-count-badge');

    function openTsModal() {
        if (tsModal) {
            tsModal.style.display = 'block';
            tsModal.classList.add('show');
        }
        if (tsBackdrop) {
            tsBackdrop.style.display = 'block';
            tsBackdrop.classList.add('show');
        }
        updateTsCount();
    }

    function closeTsModal() {
        if (tsModal) {
            tsModal.style.display = 'none';
            tsModal.classList.remove('show');
        }
        if (tsBackdrop) {
            tsBackdrop.style.display = 'none';
            tsBackdrop.classList.remove('show');
        }
    }

    function updateTsCount() {
        const totalItems = document.querySelectorAll('.ts-table-checkbox').length;
        const checkedItems = document.querySelectorAll('.ts-table-checkbox:checked').length;
        if (tsCountBadge) {
            tsCountBadge.innerHTML = 'Selected: <strong>' + checkedItems + '</strong> of ' + totalItems + ' tables';
        }
    }

    // Delegation for opening modal
    document.addEventListener('click', function(e) {
        const openBtn = e.target.closest('#btn-open-table-selector');
        if (openBtn) {
            e.preventDefault();
            openTsModal();
        }
    });

    if (tsCloseX) tsCloseX.addEventListener('click', closeTsModal);
    if (tsCancel) tsCancel.addEventListener('click', closeTsModal);
    if (tsBackdrop) tsBackdrop.addEventListener('click', closeTsModal);

    // Live search inside Table Selector Modal
    if (tsSearch) {
        tsSearch.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            document.querySelectorAll('.ts-table-item').forEach(item => {
                const name = item.getAttribute('data-table-name') || '';
                if (query === '' || name.toLowerCase().includes(query)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // Quick selection buttons
    if (tsSelectCustomOnly) {
        tsSelectCustomOnly.addEventListener('click', function() {
            document.querySelectorAll('.ts-table-item').forEach(item => {
                const isCustom = item.getAttribute('data-is-custom') === '1';
                const chk = item.querySelector('.ts-table-checkbox');
                if (chk) chk.checked = isCustom;
            });
            updateTsCount();
        });
    }

    if (tsSelectAll) {
        tsSelectAll.addEventListener('click', function() {
            document.querySelectorAll('.ts-table-checkbox').forEach(chk => chk.checked = true);
            updateTsCount();
        });
    }

    if (tsDeselectAll) {
        tsDeselectAll.addEventListener('click', function() {
            document.querySelectorAll('.ts-table-checkbox').forEach(chk => chk.checked = false);
            updateTsCount();
        });
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('ts-table-checkbox')) {
            updateTsCount();
        }
    });

    // Save Selected Tables via AJAX
    if (tsSave) {
        tsSave.addEventListener('click', function() {
            const selected = [];
            document.querySelectorAll('.ts-table-checkbox:checked').forEach(chk => {
                selected.push(chk.value);
            });

            tsSave.disabled = true;
            tsSave.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Saving...';

            const formData = new FormData();
            formData.append('action', 'save_custom_tables');
            formData.append('tables', JSON.stringify(selected));

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                tsSave.disabled = false;
                tsSave.innerHTML = '<i class="fa fa-save mr-1"></i> Save Selected Tables';
                if (res.success) {
                    closeTsModal();
                    window.location.reload();
                } else {
                    alert(res.error || 'Failed to save table selection.');
                }
            })
            .catch(err => {
                tsSave.disabled = false;
                tsSave.innerHTML = '<i class="fa fa-save mr-1"></i> Save Selected Tables';
                console.error('Save tables error:', err);
                alert('An error occurred while saving table selection.');
            });
        });
    }

    // 3. Level 1: Tables List AJAX Handler
    if (appLevel1) {
        const tbody1 = document.querySelector('#db-tables-list tbody');
        const summary1 = document.getElementById('tables-summary-container');
        const pagination1 = document.getElementById('tables-pagination-container');
        const filterForm1 = document.querySelector('.filter-bar-single');

        let searchTimeout = null;

        function fetchTablesList(page = 1) {
            if (!tbody1) return;
            const searchInput = document.getElementById('table-search-input');
            const statusSelect = document.querySelector('select[name="status_filter"]');
            const createdBySelect = document.querySelector('select[name="created_by"]');
            const scopeSelect = document.getElementById('table-scope-select');

            const search = searchInput ? searchInput.value.trim() : '';
            const status_filter = statusSelect ? statusSelect.value : '';
            const created_by = createdBySelect ? createdBySelect.value : '';
            const scope = scopeSelect ? scopeSelect.value : 'custom';

            tbody1.style.opacity = '0.4';

            const params = new URLSearchParams({
                action: 'fetch_tables',
                search: search,
                status_filter: status_filter,
                created_by: created_by,
                scope: scope,
                page: page
            });

            fetch(ajaxUrl + '?' + params.toString())
                .then(res => res.json())
                .then(res => {
                    tbody1.style.opacity = '1';
                    if (res.success) {
                        tbody1.innerHTML = res.html;
                        if (summary1) summary1.textContent = res.summary;
                        if (pagination1) pagination1.innerHTML = res.pagination;
                    }
                })
                .catch(err => {
                    tbody1.style.opacity = '1';
                    console.error('AJAX Fetch Tables Error:', err);
                });
        }

        const searchInput = document.getElementById('table-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() { fetchTablesList(1); }, 300);
            });
        }

        if (filterForm1) {
            filterForm1.addEventListener('submit', function(e) {
                e.preventDefault();
                fetchTablesList(1);
            });
            filterForm1.querySelectorAll('select').forEach(select => {
                select.addEventListener('change', function() {
                    if (this.name !== 'table_select') fetchTablesList(1);
                });
            });
        }

        document.addEventListener('click', function(e) {
            const link = e.target.closest('#tables-pagination-container .paging a');
            if (link) {
                e.preventDefault();
                const href = link.getAttribute('href');
                if (href) {
                    const match = href.match(/page=(\d+)/);
                    fetchTablesList(match ? match[1] : 1);
                }
            }
        });
    }

    // 4. Level 2: Table Data Explorer AJAX Handler
    const appLevel2 = document.getElementById('table-records-explorer-app');
    if (appLevel2) {
        const ajaxUrl2 = appLevel2.getAttribute('data-ajax-url') || 'ajax.php';
        const tableName = appLevel2.getAttribute('data-table');
        const tbody2 = document.querySelector('#viewer-data-table tbody');
        const summary2 = document.getElementById('table-data-summary-container');
        const pagination2 = document.getElementById('table-data-pagination-container');
        const columnFiltersForm = document.getElementById('column-filters-form');

        let colFilterTimeout = null;

        function fetchTableData(page = 1) {
            if (!tbody2 || !tableName) return;

            tbody2.style.opacity = '0.4';

            const params = new URLSearchParams({
                action: 'fetch_table_data',
                table: tableName,
                page: page
            });

            if (columnFiltersForm) {
                const inputs = columnFiltersForm.querySelectorAll('.column-filter-input');
                inputs.forEach(input => {
                    if (input.value.trim() !== '') {
                        params.append(input.name, input.value.trim());
                    }
                });
            }

            fetch(ajaxUrl2 + '?' + params.toString())
                .then(res => res.json())
                .then(res => {
                    tbody2.style.opacity = '1';
                    if (res.success) {
                        tbody2.innerHTML = res.html;
                        if (summary2) summary2.textContent = res.summary;
                        if (pagination2) pagination2.innerHTML = res.pagination;
                    }
                })
                .catch(err => {
                    tbody2.style.opacity = '1';
                    console.error('AJAX Fetch Table Data Error:', err);
                });
        }

        if (columnFiltersForm) {
            columnFiltersForm.addEventListener('submit', function(e) {
                e.preventDefault();
                fetchTableData(1);
            });

            columnFiltersForm.querySelectorAll('.column-filter-input').forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(colFilterTimeout);
                    colFilterTimeout = setTimeout(function() { fetchTableData(1); }, 350);
                });
            });
        }

        document.addEventListener('click', function(e) {
            const link = e.target.closest('#table-data-pagination-container .paging a');
            if (link) {
                e.preventDefault();
                const href = link.getAttribute('href');
                if (href) {
                    const match = href.match(/page=(\d+)/);
                    fetchTableData(match ? match[1] : 1);
                }
            }
        });
    }

    // 5. SQL Runner AJAX Execution & Error Rendering
    const sqlForm = document.getElementById('sql-runner-form');
    const sqlResultsContainer = document.getElementById('sql-results-container');
    if (sqlForm && sqlResultsContainer) {
        sqlForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const sqlTextarea = document.getElementById('sql-input-textarea');
            const sqlVal = sqlTextarea ? sqlTextarea.value.trim() : '';
            if (!sqlVal) return;

            sqlResultsContainer.style.opacity = '0.4';

            const formData = new FormData();
            formData.append('action', 'run_sql');
            formData.append('sql', sqlVal);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                sqlResultsContainer.style.opacity = '1';
                if (res.success) {
                    sqlResultsContainer.innerHTML = res.html;
                } else if (res.error_html) {
                    sqlResultsContainer.innerHTML = res.error_html;
                } else {
                    sqlResultsContainer.innerHTML = '<div class="alert alert-danger m-3 font-size-14">' + (res.error || 'SQL Execution Failed.') + '</div>';
                }
            })
            .catch(err => {
                sqlResultsContainer.style.opacity = '1';
                console.error('SQL Runner Error:', err);
            });
        });
    }
});
