<?php
/**
 * Local plugin admin_functions: Database explorer and SQL query runner.
 * Custom Listing Page with 2 tabs, Custom Tables filtering, Super Admin Table Selector Modal, and Admin Debug.
 *
 * @package    local_admin_functions
 * @copyright  2026 Rosmin Babu
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Apply admin-only debug mode rules.
local_admin_functions_apply_debug_settings();

// Force user login — superadmin only.
require_login();
if (!is_siteadmin()) {
    local_admin_functions_access_denied();
}
$context = context_system::instance();

// Fetch parameters.
$tab     = optional_param('tab', 'tables', PARAM_ALPHA);
$page    = optional_param('page', 1, PARAM_INT);
$search  = optional_param('search', '', PARAM_TEXT);
$scope   = optional_param('scope', 'custom', PARAM_ALPHA);
$sql     = trim(optional_param('sql', '', PARAM_RAW));
$perpage = 100;

$allowed_tabs = array('tables', 'logs', 'tasks', 'sql');
if (!in_array($tab, $allowed_tabs)) {
    $tab = 'tables';
}

// Set up page.
$PAGE->set_url(new moodle_url('/local/admin_functions/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_admin_functions'));
$PAGE->set_heading(get_string('pluginname', 'local_admin_functions'));

// CSS only — JS is loaded via js_init_code using Totara's AMD require(['jquery']).
$PAGE->requires->css(new moodle_url('/local/admin_functions/styles.css'));

// Build the AJAX URL to pass into JS.
$ajax_url = (new moodle_url('/local/admin_functions/ajax.php'))->out(false);

// Inject all JS via Totara's AMD system using jQuery (Bootstrap 4 needs it).
$PAGE->requires->js_init_code("
require(['jquery'], function($) {

    var ajaxUrl = " . json_encode($ajax_url) . ";

    // === 1. Bootstrap modal for Table Selector ===
    // Update the selected count badge when the modal opens.
    $('#table-selector-modal').on('show.bs.modal', function() {
        updateTsCount();
    });

    function updateTsCount() {
        var total   = $('.ts-table-checkbox').length;
        var checked = $('.ts-table-checkbox:checked').length;
        $('#ts-selected-count-badge').html('Selected: <strong>' + checked + '</strong> of ' + total + ' tables');
    }

    // Table search filter inside modal.
    $('#ts-modal-search').on('input', function() {
        var q = $(this).val().toLowerCase().trim();
        $('.ts-table-item').each(function() {
            var name = ($(this).data('table-name') || '').toLowerCase();
            $(this).toggle(q === '' || name.indexOf(q) !== -1);
        });
    });

    // Quick selection buttons.
    $('#ts-select-custom-only').on('click', function() {
        $('.ts-table-item').each(function() {
            var chk = $(this).find('.ts-table-checkbox');
            chk.prop('checked', $(this).data('is-custom') == '1');
        });
        updateTsCount();
    });

    $('#ts-select-all').on('click', function() {
        $('.ts-table-checkbox').prop('checked', true);
        updateTsCount();
    });

    $('#ts-deselect-all').on('click', function() {
        $('.ts-table-checkbox').prop('checked', false);
        updateTsCount();
    });

    $(document).on('change', '.ts-table-checkbox', function() {
        updateTsCount();
    });

    // Save selected tables via AJAX then close modal and reload.
    $('#ts-modal-btn-save').on('click', function() {
        var selected = [];
        $('.ts-table-checkbox:checked').each(function() {
            selected.push($(this).val());
        });

        var \$btn = $(this);
        \$btn.prop('disabled', true).html('<i class=\"fa fa-spinner fa-spin mr-1\"></i> Saving...');

        var formData = new FormData();
        formData.append('action', 'save_custom_tables');
        formData.append('tables', JSON.stringify(selected));

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(res) { return res.json(); })
            .then(function(res) {
                \$btn.prop('disabled', false).html('<i class=\"fa fa-save mr-1\"></i> Save Selected Tables');
                if (res.success) {
                    \$('#table-selector-modal').modal('hide');
                    window.location.reload();
                } else {
                    alert(res.error || 'Failed to save table selection.');
                }
            })
            .catch(function(err) {
                \$btn.prop('disabled', false).html('<i class=\"fa fa-save mr-1\"></i> Save Selected Tables');
                console.error('Save tables error:', err);
                alert('An error occurred while saving table selection.');
            });
    });

    // === 2. Bootstrap modal for Debug Mode Toggle ===
    var targetDebugState = false;

    $('#btn-toggle-admin-debug').on('click', function(e) {
        e.preventDefault();
        var willEnable = !this.checked;
        this.checked = !willEnable;
        targetDebugState = willEnable;

        if (willEnable) {
            $('#debug-modal-message').html('Are you sure you want to <strong>ENABLE Developer Debug Mode</strong> and show all PHP error messages, notices, and stack traces?');
            $('#modal-btn-confirm').removeClass('btn-primary').addClass('btn-danger').html('<i class=\"fa fa-check mr-1\"></i> Enable All Errors');
        } else {
            $('#debug-modal-message').html('Are you sure you want to <strong>DISABLE Debug Mode</strong> and suppress error displays?');
            $('#modal-btn-confirm').removeClass('btn-danger').addClass('btn-primary').html('<i class=\"fa fa-check mr-1\"></i> Disable Debug');
        }
        \$('#debug-confirm-modal').modal('show');
    });

    $('#modal-btn-cancel').on('click', function() {
        \$('#debug-confirm-modal').modal('hide');
    });

    \$('#debug-confirm-modal').on('hide.bs.modal', function() {
        \$('#btn-toggle-admin-debug').prop('checked', !targetDebugState);
    });

    $('#modal-btn-confirm').on('click', function() {
        var \$btn = $(this);
        \$btn.prop('disabled', true);

        fetch(ajaxUrl + '?action=toggle_debug')
            .then(function(res) { return res.json(); })
            .then(function(res) {
                \$btn.prop('disabled', false);
                if (res.success) {
                    \$('#btn-toggle-admin-debug').prop('checked', !!res.debug);
                    targetDebugState = !!res.debug;
                    \$('#debug-confirm-modal').modal('hide');
                    \$('#debug-confirm-modal').off('hide.bs.modal');
                    window.location.reload();
                } else {
                    alert(res.error || 'Failed to update Debug Mode.');
                    \$('#debug-confirm-modal').modal('hide');
                }
            })
            .catch(function(err) {
                \$btn.prop('disabled', false);
                console.error('Debug toggle error:', err);
                \$('#debug-confirm-modal').modal('hide');
            });
    });

    // === 3. Tables List AJAX (search + filter + pagination) ===
    var tbody1      = document.querySelector('#db-tables-list tbody');
    var summary1    = document.getElementById('tables-summary-container');
    var pagination1 = document.getElementById('tables-pagination-container');
    var filterForm1 = document.querySelector('.filter-bar-single');
    var searchTimeout = null;

    function fetchTablesList(page) {
        if (!tbody1) return;
        var search        = \$('#table-search-input').val().trim();
        var status_filter = \$('select[name=\"status_filter\"]').val() || '';
        var scope         = \$('#table-scope-select').val() || 'custom';

        \$(tbody1).css('opacity', '0.4');

        var params = new URLSearchParams({
            action: 'fetch_tables',
            search: search,
            status_filter: status_filter,
            scope: scope,
            page: page || 1
        });

        fetch(ajaxUrl + '?' + params.toString())
            .then(function(res) { return res.json(); })
            .then(function(res) {
                \$(tbody1).css('opacity', '1');
                if (res.success) {
                    tbody1.innerHTML = res.html;
                    if (summary1) summary1.textContent = res.summary;
                    if (pagination1) pagination1.innerHTML = res.pagination;
                }
            })
            .catch(function(err) {
                \$(tbody1).css('opacity', '1');
                console.error('AJAX Fetch Tables Error:', err);
            });
    }

    \$('#table-search-input').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { fetchTablesList(1); }, 300);
    });

    if (filterForm1) {
        \$(filterForm1).on('submit', function(e) {
            e.preventDefault();
            fetchTablesList(1);
        });
        \$(filterForm1).find('select').on('change', function() {
            if (this.name !== 'table_select') fetchTablesList(1);
        });
    }

    \$(document).on('click', '#tables-pagination-container .paging a', function(e) {
        e.preventDefault();
        var href = \$(this).attr('href');
        if (href) {
            var match = href.match(/page=(\\d+)/);
            fetchTablesList(match ? match[1] : 1);
        }
    });

    // === 4. SQL Query Runner AJAX ===
    var sqlForm = document.getElementById('sql-runner-form');
    var sqlResultsContainer = document.getElementById('sql-results-container');

    if (sqlForm && sqlResultsContainer) {
        \$(sqlForm).on('submit', function(e) {
            e.preventDefault();
            var sqlVal = \$('#sql-input-textarea').val().trim();
            if (!sqlVal) return;

            \$(sqlResultsContainer).css('opacity', '0.4');
            var formData = new FormData();
            formData.append('action', 'run_sql');
            formData.append('sql', sqlVal);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    \$(sqlResultsContainer).css('opacity', '1');
                    if (res.success) {
                        sqlResultsContainer.innerHTML = res.html;
                    } else if (res.error_html) {
                        sqlResultsContainer.innerHTML = res.error_html;
                    } else {
                        sqlResultsContainer.innerHTML = '<div class=\"alert alert-danger m-3\">' + (res.error || 'SQL Execution Failed.') + '</div>';
                    }
                })
                .catch(function(err) {
                    \$(sqlResultsContainer).css('opacity', '1');
                    console.error('SQL Runner Error:', err);
                });
        });
    }

    // === 5. Log Explorer AJAX Engine ===
    var logSearchTimeout = null;
    var currentLogViewMode = 'list';
    var currentLogPage = 1;
    var currentEventFilter = '';

    function fetchLogs(page, scroll) {
        var tbodyLogs = document.querySelector('#db-logs-list tbody');
        if (!tbodyLogs) return;

        currentLogPage = page || 1;

        var search   = \$('#log-search-input').val().trim();
        var crud     = \$('#log-crud-select').val() || '';
        var comp     = \$('#log-component-select').val() || '';
        var fromDate = \$('#log-from-date').val() || '';
        var toDate   = \$('#log-to-date').val() || '';

        \$(tbodyLogs).css('opacity', '0.4');

        var params = new URLSearchParams({
            action: 'fetch_logs',
            search: search,
            crud: crud,
            component: comp,
            fromdate: fromDate,
            todate: toDate,
            eventfilter: currentEventFilter,
            viewmode: currentLogViewMode,
            page: currentLogPage
        });

        fetch(ajaxUrl + '?' + params.toString())
            .then(function(res) { return res.json(); })
            .then(function(res) {
                \$(tbodyLogs).css('opacity', '1');
                if (res.success) {
                    tbodyLogs.innerHTML = res.html;
                    var summaryLogs = document.getElementById('logs-summary-container');
                    var paginationLogs = document.getElementById('logs-pagination-container');
                    if (summaryLogs) summaryLogs.textContent = res.summary;
                    if (paginationLogs) paginationLogs.innerHTML = res.pagination;

                    if (scroll) {
                        var \$wrapper = \$('#logs-table-wrapper');
                        if (\$wrapper.length) {
                            \$('html, body').animate({ scrollTop: \$wrapper.offset().top - 80 }, 200);
                        }
                    }
                }
            })
            .catch(function(err) {
                \$(tbodyLogs).css('opacity', '1');
                console.error('AJAX Fetch Logs Error:', err);
            });
    }

    if (\$('#logs-explorer-pane').is(':visible') || window.location.search.indexOf('tab=logs') !== -1) {
        fetchLogs(1);
    }

    \$('#log-search-input').on('input', function() {
        currentEventFilter = '';
        clearTimeout(logSearchTimeout);
        logSearchTimeout = setTimeout(function() { fetchLogs(1); }, 350);
    });

    \$('#log-crud-select, #log-component-select, #log-from-date, #log-to-date').on('change', function() {
        fetchLogs(1);
    });

    \$('#btn-apply-log-filters').on('click', function() {
        fetchLogs(1);
    });

    \$('#btn-reset-log-filters').on('click', function() {
        currentEventFilter = '';
        \$('#log-search-input').val('');
        \$('#log-crud-select').val('');
        \$('#log-component-select').val('');
        \$('#log-from-date').val('');
        \$('#log-to-date').val('');
        fetchLogs(1);
    });

    \$('#btn-view-list').on('click', function() {
        \$(this).addClass('active');
        \$('#btn-view-group').removeClass('active');
        currentLogViewMode = 'list';
        \$('#logs-header-tr').html('<th style=\"width: 5%\">#</th><th style=\"width: 16%\">Time Created</th><th style=\"width: 25%\">Event Description</th><th style=\"width: 13%\">Component</th><th style=\"width: 8%\" class=\"text-center\">CRUD</th><th style=\"width: 18%\">Performed By</th><th style=\"width: 11%\">IP Address</th><th style=\"width: 4%\" class=\"text-center\">View</th>');
        fetchLogs(1);
    });

    \$('#btn-view-group').on('click', function() {
        \$(this).addClass('active');
        \$('#btn-view-list').removeClass('active');
        currentLogViewMode = 'group';
        currentEventFilter = '';
        \$('#logs-header-tr').html('<th style=\"width: 5%\">#</th><th style=\"width: 35%\">Event Description</th><th style=\"width: 18%\">Component</th><th style=\"width: 8%\">CRUD</th><th style=\"width: 14%\">Total Count</th><th style=\"width: 15%\">Latest Activity</th><th style=\"width: 5%\" class=\"text-center\">View</th>');
        fetchLogs(1);
    });

    \$(document).on('click', '.btn-view-group-events', function(e) {
        e.preventDefault();
        var eventName = \$(this).data('event-name') || '';
        var component = \$(this).data('component') || '';

        currentEventFilter = eventName;
        \$('#log-search-input').val('');
        if (component) {
            \$('#log-component-select').val(component);
        }

        \$('#btn-view-list').addClass('active');
        \$('#btn-view-group').removeClass('active');
        currentLogViewMode = 'list';
        \$('#logs-header-tr').html('<th style=\"width: 5%\">#</th><th style=\"width: 16%\">Time Created</th><th style=\"width: 25%\">Event Description</th><th style=\"width: 13%\">Component</th><th style=\"width: 8%\" class=\"text-center\">CRUD</th><th style=\"width: 18%\">Performed By</th><th style=\"width: 11%\">IP Address</th><th style=\"width: 4%\" class=\"text-center\">View</th>');

        fetchLogs(1, true);
    });

    \$(document).on('click', '#logs-pagination-container a', function(e) {
        e.preventDefault();
        var href = \$(this).attr('href');
        if (!href) return;
        var page = 1;
        try {
            var match = href.match(/[?&;]page=(\\d+)/i);
            if (match && match[1]) {
                page = parseInt(match[1], 10);
            }
        } catch (err) {
            page = 1;
        }
        fetchLogs(page, true);
    });

    \$(document).on('click', '.btn-view-log-detail', function(e) {
        e.preventDefault();
        var logId = \$(this).data('log-id');
        if (!logId) return;

        var \$body = \$('#log-detail-modal-body');
        \$body.html('<div class=\"text-center py-5 text-muted\"><i class=\"fa fa-spinner fa-spin fa-2x mr-2\"></i> Loading log details...</div>');
        \$('#log-detail-modal').modal('show');

        fetch(ajaxUrl + '?action=fetch_log_detail&logid=' + logId)
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (res.success) {
                    \$body.html(res.html);
                } else {
                    \$body.html('<div class=\"alert alert-danger m-3\">' + (res.error || 'Failed to load log details.') + '</div>');
                }
            })
            .catch(function(err) {
                console.error('Fetch Log Detail Error:', err);
                \$body.html('<div class=\"alert alert-danger m-3\">An error occurred while loading log details.</div>');
            });
    });

    \$('#btn-export-logs-csv').on('click', function(e) {
        e.preventDefault();
        var search   = \$('#log-search-input').val().trim();
        var crud     = \$('#log-crud-select').val() || '';
        var comp     = \$('#log-component-select').val() || '';
        var fromDate = \$('#log-from-date').val() || '';
        var toDate   = \$('#log-to-date').val() || '';

        var params = new URLSearchParams({
            action: 'export_logs',
            search: search,
            crud: crud,
            component: comp,
            fromdate: fromDate,
            todate: toDate
        });

        window.location.href = ajaxUrl + '?' + params.toString();
    });

    // === 6. Scheduled Tasks & Cron Dashboard AJAX Engine ===
    var taskSearchTimeout = null;
    var currentTaskPage = 1;

    function fetchTasks(page, scroll) {
        var tbodyTasks = document.querySelector('#db-tasks-list tbody');
        if (!tbodyTasks) return;

        currentTaskPage = page || 1;

        var search = \$('#task-search-input').val().trim();
        var comp   = \$('#task-component-select').val() || '';
        var status = \$('#task-status-select').val() || '';

        \$(tbodyTasks).css('opacity', '0.4');

        var params = new URLSearchParams({
            action: 'fetch_tasks',
            search: search,
            component: comp,
            status: status,
            page: currentTaskPage
        });

        fetch(ajaxUrl + '?' + params.toString())
            .then(function(res) { return res.json(); })
            .then(function(res) {
                \$(tbodyTasks).css('opacity', '1');
                if (res.success) {
                    tbodyTasks.innerHTML = res.html;
                    var summaryTasks = document.getElementById('tasks-summary-container');
                    var paginationTasks = document.getElementById('tasks-pagination-container');
                    if (summaryTasks) summaryTasks.textContent = res.summary;
                    if (paginationTasks) paginationTasks.innerHTML = res.pagination;

                    \$('#task-stat-total').text(res.total_count || 0);
                    \$('#task-stat-active').text(res.active_count || 0);
                    \$('#task-stat-disabled').text(res.disabled_count || 0);
                    \$('#task-stat-failed').text(res.failed_count || 0);

                    \$('#cron-last-time-str').text(res.last_cron_str || 'Unknown');
                    if (res.cron_healthy) {
                        \$('#cron-status-badge').html('<span class=\"badge badge-success px-2 py-1\"><i class=\"fa fa-check-circle mr-1\"></i> Cron Active</span>');
                    } else {
                        \$('#cron-status-badge').html('<span class=\"badge badge-warning px-2 py-1 text-dark\"><i class=\"fa fa-exclamation-triangle mr-1\"></i> Cron Delayed / Inactive</span>');
                    }

                    if (scroll) {
                        var \$wrapper = \$('#tasks-table-wrapper');
                        if (\$wrapper.length) {
                            \$('html, body').animate({ scrollTop: \$wrapper.offset().top - 80 }, 200);
                        }
                    }
                }
            })
            .catch(function(err) {
                \$(tbodyTasks).css('opacity', '1');
                console.error('AJAX Fetch Tasks Error:', err);
            });
    }

    if (\$('#tasks-dashboard-pane').is(':visible') || window.location.search.indexOf('tab=tasks') !== -1) {
        fetchTasks(1);
    }

    \$('#task-search-input').on('input', function() {
        clearTimeout(taskSearchTimeout);
        taskSearchTimeout = setTimeout(function() { fetchTasks(1); }, 350);
    });

    \$('#task-component-select, #task-status-select').on('change', function() {
        fetchTasks(1);
    });

    \$('#btn-apply-task-filters').on('click', function() {
        fetchTasks(1);
    });

    \$('#btn-reset-task-filters').on('click', function() {
        \$('#task-search-input').val('');
        \$('#task-component-select').val('');
        \$('#task-status-select').val('');
        fetchTasks(1);
    });

    \$(document).on('click', '#tasks-pagination-container a', function(e) {
        e.preventDefault();
        var href = \$(this).attr('href');
        if (!href) return;
        var page = 1;
        try {
            var match = href.match(/[?&;]page=(\\d+)/i);
            if (match && match[1]) {
                page = parseInt(match[1], 10);
            }
        } catch (err) {
            page = 1;
        }
        fetchTasks(page, true);
    });

    \$(document).on('click', '#btn-copy-crontab', function(e) {
        e.preventDefault();
        var cmdText = \$('#crontab-cmd-text').text();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(cmdText).then(function() {
                var \$btn = \$('#btn-copy-crontab');
                var orig = \$btn.html();
                \$btn.html('<i class=\"fa fa-check mr-1\"></i> Copied!').addClass('bg-success');
                setTimeout(function() { \$btn.html(orig).removeClass('bg-success'); }, 2000);
            });
        }
    });

    \$(document).on('click', '.btn-run-task-now', function(e) {
        e.preventDefault();
        var \$btn = \$(this);
        var taskClass = \$btn.data('task-class');
        var taskName = \$btn.data('task-name') || taskClass;
        if (!taskClass) return;

        var origHtml = \$btn.html();
        \$btn.prop('disabled', true).html('<i class=\"fa fa-spinner fa-spin\"></i> Running...');

        var formData = new FormData();
        formData.append('action', 'run_task_now');
        formData.append('task_class', taskClass);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(res) { return res.json(); })
            .then(function(res) {
                \$btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    alert('⚡ Task Executed Successfully!\\nDuration: ' + res.duration + '\\n\\nOutput:\\n' + res.output);
                    fetchTasks(currentTaskPage);
                } else {
                    alert('❌ Task Execution Failed: ' + (res.error || 'Unknown error'));
                    fetchTasks(currentTaskPage);
                }
            })
            .catch(function(err) {
                \$btn.prop('disabled', false).html(origHtml);
                alert('An error occurred while attempting to run task.');
            });
    });

    \$(document).on('click', '.btn-view-task-log', function(e) {
        e.preventDefault();
        var taskClass = \$(this).data('task-class');
        var taskName = \$(this).data('task-name');
        if (!taskClass) return;

        \$('#task-modal-name-span').text(taskName);
        \$('#task-modal-btn-run-now').data('task-class', taskClass).data('task-name', taskName);

        var \$body = \$('#task-log-modal-body');
        \$body.html('<div class=\"text-center py-5 text-muted\"><i class=\"fa fa-spinner fa-spin fa-2x mr-2\"></i> Loading task logs...</div>');
        \$('#task-log-modal').modal('show');

        fetch(ajaxUrl + '?action=fetch_task_log&task_class=' + encodeURIComponent(taskClass))
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (res.success) {
                    \$body.html(res.html);
                } else {
                    \$body.html('<div class=\"alert alert-danger m-3\">' + (res.error || 'Failed to load task logs.') + '</div>');
                }
            })
            .catch(function(err) {
                \$body.html('<div class=\"alert alert-danger m-3\">An error occurred while loading task logs.</div>');
            });
    });

    \$(document).on('click', '#task-modal-btn-run-now', function(e) {
        e.preventDefault();
        var taskClass = \$(this).data('task-class');
        if (!taskClass) return;
        \$('#task-log-modal').modal('hide');
        setTimeout(function() {
            var \$targetBtn = \$('.btn-run-task-now[data-task-class=\"' + taskClass + '\"]');
            if (\$targetBtn.length) {
                \$targetBtn.trigger('click');
            } else {
                var formData = new FormData();
                formData.append('action', 'run_task_now');
                formData.append('task_class', taskClass);
                fetch(ajaxUrl, { method: 'POST', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(r) {
                        alert(r.success ? '⚡ Task Executed!\\nDuration: ' + r.duration : '❌ Task Failed: ' + r.error);
                        fetchTasks(currentTaskPage);
                    });
            }
        }, 300);
    });

    \$(document).on('click', '.btn-edit-task-schedule', function(e) {
        e.preventDefault();
        var \$btn = \$(this);
        \$('#task-edit-class').val(\$btn.data('task-class'));
        \$('#task-edit-name-display').text(\$btn.data('task-name'));
        \$('#task-edit-class-display').text('\\\\' + \$btn.data('task-class'));
        \$('#task-edit-min').val(\$btn.data('minute'));
        \$('#task-edit-hour').val(\$btn.data('hour'));
        \$('#task-edit-day').val(\$btn.data('day'));
        \$('#task-edit-month').val(\$btn.data('month'));
        \$('#task-edit-dow').val(\$btn.data('dayofweek'));
        \$('#task-edit-disabled').prop('checked', \$btn.data('disabled') === '1' || \$btn.data('disabled') === 1);
        \$('#task-schedule-modal').modal('show');
    });

    \$('#task-schedule-form').on('submit', function(e) {
        e.preventDefault();
        var \$saveBtn = \$('#btn-save-task-schedule');
        var origHtml = \$saveBtn.html();
        \$saveBtn.prop('disabled', true).html('<i class=\"fa fa-spinner fa-spin\"></i> Saving...');

        var formData = new FormData();
        formData.append('action', 'save_task_schedule');
        formData.append('task_class', \$('#task-edit-class').val());
        formData.append('minute', \$('#task-edit-min').val());
        formData.append('hour', \$('#task-edit-hour').val());
        formData.append('day', \$('#task-edit-day').val());
        formData.append('month', \$('#task-edit-month').val());
        formData.append('dayofweek', \$('#task-edit-dow').val());
        formData.append('disabled', \$('#task-edit-disabled').is(':checked') ? 1 : 0);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(function(res) { return res.json(); })
            .then(function(res) {
                \$saveBtn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    \$('#task-schedule-modal').modal('hide');
                    fetchTasks(currentTaskPage);
                } else {
                    alert('Failed to save schedule: ' + (res.error || 'Unknown error'));
                }
            })
            .catch(function(err) {
                \$saveBtn.prop('disabled', false).html(origHtml);
                alert('An error occurred while saving schedule.');
            });
    });

});
", true);

echo $OUTPUT->header();

// Fetch all database tables and custom configured tables.
$db_all_tables = $DB->get_tables();
sort($db_all_tables);

$custom_tables_list = local_admin_functions_get_custom_tables();

if ($scope === 'all') {
    $all_tables = $db_all_tables;
} else {
    $all_tables = $custom_tables_list;
}

// Helper function to return friendly description for Totara database tables.
function get_table_description_index($tablename) {
    $descriptions = array(
        'user' => 'User accounts and profile information',
        'course' => 'Course details and settings',
        'enrol' => 'Course enrolment methods and data',
        'user_enrolments' => 'User course enrolment records',
        'grade_grades' => 'Grades and assessment results',
        'logstore_standard_log' => 'System logs and audit events',
        'role' => 'System roles and definitions',
        'role_assignments' => 'User role assignment records',
        'context' => 'Moodle context hierarchy records',
        'modules' => 'Installed activity modules',
        'course_categories' => 'Course category structure',
        'question' => 'Question bank items',
        'quiz' => 'Quiz activity settings and instances',
        'badge' => 'Badges and accreditation data',
        'cohort' => 'System cohorts and audience groups',
        'config' => 'Core system configuration settings',
        'files' => 'Moodle file storage metadata',
    );
    if (isset($descriptions[$tablename])) {
        return $descriptions[$tablename];
    }
    
    $clean_name = str_replace('_', ' ', $tablename);
    return ucwords($clean_name) . ' database records';
}

$debug_active = local_admin_functions_is_debug_active();
$is_admin_user = is_siteadmin();
$log_components = local_admin_functions_get_log_components();
?>

<!-- 1. Page Header Row with Admin Controls -->
<div class="page-header-row">
    <div>
        <h2 class="page-header-title">Tables</h2>
        <p class="page-header-subtitle">Manage and view your database table records</p>
    </div>
    <div class="d-flex align-items-center gap-3">
        <?php if ($is_admin_user): ?>
            <!-- Bootstrap modal trigger button -->
            <button type="button"
                    class="btn btn-primary font-weight-bold shadow-sm"
                    id="btn-open-table-selector"
                    data-toggle="modal"
                    data-target="#table-selector-modal"
                    style="font-size: 14px; border-radius: 8px; padding: 0.6rem 1.25rem;">
                <i class="fa fa-th-list mr-1"></i> Select Custom Tables
            </button>

            <div class="admin-debug-toggle-container">
                <span class="admin-debug-toggle-label"><i class="fa fa-bug mr-1"></i> Debug Mode:</span>
                <label class="admin-debug-switch">
                    <input type="checkbox" id="btn-toggle-admin-debug" <?php echo $debug_active ? 'checked' : ''; ?>>
                    <span class="admin-debug-slider"></span>
                </label>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Main Container -->
<div class="admin-functions-container card" id="admin-functions-tables-app" data-ajax-url="<?php echo (new moodle_url('/local/admin_functions/ajax.php'))->out(false); ?>">
    <div class="card-body p-4">

        <!-- Navigation Tabs (3 Tabs: Tables List, Log Explorer & SQL Query Runner) -->
        <ul class="nav nav-tabs admin-nav-tabs mb-4" id="admin-functions-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?php echo ($tab === 'tables') ? 'active' : ''; ?>" href="index.php?tab=tables">
                    <i class="fa fa-list mr-1"></i> Tables List
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($tab === 'logs') ? 'active' : ''; ?>" href="index.php?tab=logs">
                    <i class="fa fa-history mr-1"></i> Log Explorer
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($tab === 'tasks') ? 'active' : ''; ?>" href="index.php?tab=tasks">
                    <i class="fa fa-tasks mr-1"></i> Tasks &amp; Cron
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($tab === 'sql') ? 'active' : ''; ?>" href="index.php?tab=sql">
                    <i class="fa fa-code mr-1"></i> SQL Query Runner
                </a>
            </li>
        </ul>

        <div class="tab-content" id="admin-functions-tabs-content">
            
            <!-- Pane 1: Tables List -->
            <div class="tab-pane" id="tables-list-pane" role="tabpanel" style="display: <?php echo ($tab === 'tables') ? 'block' : 'none'; ?>;">
                
                <!-- Single-Line Filter Toolbar -->
                <form action="index.php" method="GET" class="filter-bar-single">
                    <input type="hidden" name="tab" value="tables">
                    
                    <div class="search-box">
                        <i class="fa fa-search"></i>
                        <input type="text" 
                               name="search" 
                               id="table-search-input" 
                               class="form-control" 
                               placeholder="Search tables..." 
                               value="<?php echo s($search); ?>"
                               autocomplete="off">
                    </div>

                    <select name="scope" id="table-scope-select" class="custom-select filter-select font-weight-bold" style="min-width: 200px; color: #2563eb;">
                        <option value="custom" <?php echo ($scope === 'custom') ? 'selected' : ''; ?>>Custom Tables (Default)</option>
                        <option value="all" <?php echo ($scope === 'all') ? 'selected' : ''; ?>>All Database Tables</option>
                    </select>

                    <select name="table_select" class="custom-select filter-select" onchange="if(this.value) window.location.href='table_data.php?table='+this.value;">
                        <option value="">Select Table</option>
                        <?php foreach ($all_tables as $t): ?>
                            <option value="<?php echo s($t); ?>"><?php echo s($t); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status_filter" class="custom-select filter-select">
                        <option value="">Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>

                    <button type="submit" class="btn btn-filter-action">
                        <i class="fa fa-filter"></i> Filter
                    </button>

                    <a href="index.php?tab=tables" class="btn btn-reset-action">Reset</a>
                </form>

                <?php
                $filtered_tables = $all_tables;
                if ($search !== '') {
                    $filtered_tables = array_filter($all_tables, function($t) use ($search) {
                        return (stripos($t, $search) !== false);
                    });
                }
                $total_tables = count($filtered_tables);
                $totalpages_tables = max(1, (int) ceil($total_tables / $perpage));
                $page_tables = ($tab === 'tables') ? $page : 1;
                if ($page_tables < 1) $page_tables = 1;
                else if ($page_tables > $totalpages_tables) $page_tables = $totalpages_tables;
                
                $offset_tables = ($page_tables - 1) * $perpage;
                $tables_slice = array_slice($filtered_tables, $offset_tables, $perpage);
                $start_index = ($total_tables > 0) ? ($offset_tables + 1) : 0;
                $end_index = min($offset_tables + count($tables_slice), $total_tables);
                ?>

                <!-- Clean Table Container -->
                <div class="clean-table-container">
                    <table class="clean-table" id="db-tables-list">
                        <thead>
                            <tr>
                                <th style="width: 5%">ID <i class="fa fa-caret-down text-muted small ml-1"></i></th>
                                <th style="width: 20%">Table Name</th>
                                <th style="width: 32%">Description</th>
                                <th style="width: 12%">Records <i class="fa fa-sort text-muted small ml-1"></i></th>
                                <th style="width: 10%">Status</th>
                                <th style="width: 10%">Created By</th>
                                <th style="width: 13%">Created At</th>
                                <th style="width: 8%" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($total_tables === 0) {
                                echo '<tr><td colspan="8" class="text-center py-5 text-muted font-italic">No database tables match your filter criteria. ';
                                if ($is_admin_user) {
                                    echo '<button type="button" class="btn btn-link p-0 font-weight-bold" data-toggle="modal" data-target="#table-selector-modal">Select Custom Tables</button>';
                                }
                                echo '</td></tr>';
                            } else {
                                foreach ($tables_slice as $index => $tbl) {
                                    try {
                                        $rows = $DB->count_records($tbl);
                                        $row_count_str = number_format($rows);
                                        $status_badge = ($rows > 0) ? '<span class="badge-pill-active">Active</span>' : '<span class="badge-pill-inactive">Inactive</span>';
                                    } catch (\dml_exception $e) {
                                        $row_count_str = '0';
                                        $status_badge = '<span class="badge-pill-inactive">Inactive</span>';
                                    }
                                    
                                    $desc = get_table_description_index($tbl);
                                    $table_data_url = (new moodle_url('/local/admin_functions/table_data.php', array('table' => $tbl)))->out(false);
                                    
                                    echo '<tr>';
                                    echo '<td class="font-weight-semibold text-muted">' . ($start_index + $index) . '</td>';
                                    echo '<td><a class="font-weight-bold text-dark" href="' . $table_data_url . '">' . s($tbl) . '</a></td>';
                                    echo '<td class="text-secondary">' . s($desc) . '</td>';
                                    echo '<td class="font-weight-semibold">' . $row_count_str . '</td>';
                                    echo '<td>' . $status_badge . '</td>';
                                    echo '<td class="text-secondary">Admin</td>';
                                    echo '<td class="text-secondary">15 Jul 2026 10:30 AM</td>';
                                    echo '<td class="text-center">';
                                    echo '<a href="' . $table_data_url . '" class="btn-action-icon" title="View Table Records"><i class="fa fa-eye"></i></a>';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Summary & AJAX Pagination Container -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 pt-2">
                    <div class="text-secondary small font-weight-medium mb-2 mb-md-0" id="tables-summary-container">
                        <?php if ($total_tables > 0): ?>
                            Showing <?php echo $start_index; ?> to <?php echo $end_index; ?> of <?php echo number_format($total_tables); ?> entries
                        <?php endif; ?>
                    </div>
                    <div id="tables-pagination-container">
                        <?php
                        if ($total_tables > 0) {
                            $baseurl_tables = new moodle_url('/local/admin_functions/index.php', array('tab' => 'tables', 'search' => $search, 'scope' => $scope));
                            echo $OUTPUT->paging_bar($total_tables, ($tab === 'tables') ? $page : 1, $perpage, $baseurl_tables);
                        }
                        ?>
                    </div>
                </div>

            </div>

            <!-- Pane 2: Log Explorer (logstore_standard_log) -->
            <div class="tab-pane" id="logs-explorer-pane" role="tabpanel" style="display: <?php echo ($tab === 'logs') ? 'block' : 'none'; ?>;">
                
                <!-- Filter Bar & Export Toolbar -->
                <div class="filter-bar-single mb-4">
                    <div class="search-box">
                        <i class="fa fa-search"></i>
                        <input type="text" id="log-search-input" class="form-control" placeholder="Search event, user, IP..." autocomplete="off">
                    </div>

                    <select id="log-crud-select" class="custom-select filter-select font-weight-bold" style="min-width: 140px; color: #2563eb;">
                        <option value="">All Actions (CRUD)</option>
                        <option value="c">Create (C)</option>
                        <option value="r">Read (R)</option>
                        <option value="u">Update (U)</option>
                        <option value="d">Delete (D)</option>
                    </select>

                    <select id="log-component-select" class="custom-select filter-select" style="min-width: 150px;">
                        <option value="">All Components</option>
                        <?php foreach ($log_components as $comp): ?>
                            <option value="<?php echo s($comp); ?>"><?php echo s($comp); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input type="date" id="log-from-date" class="form-control filter-select" style="min-width: 135px;" title="From Date">
                    <input type="date" id="log-to-date" class="form-control filter-select" style="min-width: 135px;" title="To Date">

                    <button type="button" class="btn btn-filter-action" id="btn-apply-log-filters">
                        <i class="fa fa-filter"></i> Filter
                    </button>
                    <button type="button" class="btn btn-reset-action" id="btn-reset-log-filters">Reset</button>

                    <div class="d-flex align-items-center gap-2 ml-auto">
                        <!-- View Mode Toggle Buttons -->
                        <div class="btn-group view-mode-btn-group" role="group" aria-label="View Mode">
                            <button type="button" class="btn btn-outline-primary active" id="btn-view-list" title="Flat List View">
                                <i class="fa fa-list"></i> List
                            </button>
                            <button type="button" class="btn btn-outline-primary" id="btn-view-group" title="Group by Event Type">
                                <i class="fa fa-cubes"></i> Grouped
                            </button>
                        </div>

                        <!-- Export CSV -->
                        <button type="button" class="btn btn-outline-success font-weight-bold" id="btn-export-logs-csv" style="border-radius: 8px; font-size: 13px; height: 42px; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa fa-download mr-1"></i> Export CSV
                        </button>
                    </div>
                </div>

                <!-- Logs Table Container -->
                <div class="clean-table-container mb-3" id="logs-table-wrapper">
                    <table class="clean-table" id="db-logs-list">
                        <thead>
                            <tr id="logs-header-tr">
                                <th style="width: 5%">#</th>
                                <th style="width: 16%">Time Created</th>
                                <th style="width: 25%">Event Description</th>
                                <th style="width: 13%">Component</th>
                                <th style="width: 8%" class="text-center">CRUD</th>
                                <th style="width: 18%">Performed By</th>
                                <th style="width: 10%">IP Address</th>
                                <th style="width: 4%" class="text-center">View</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Footer Summary & AJAX Pagination Container -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 pt-2">
                    <div class="text-secondary small font-weight-medium mb-2 mb-md-0" id="logs-summary-container">
                        Loading system logs...
                    </div>
                    <div id="logs-pagination-container"></div>
                </div>

            </div>

            <!-- Pane 3: Scheduled Tasks & Cron Dashboard -->
            <div class="tab-pane" id="tasks-dashboard-pane" role="tabpanel" style="display: <?php echo ($tab === 'tasks') ? 'block' : 'none'; ?>;">
                
                <!-- Crontab Setup Banner & System Health Card -->
                <div class="cron-setup-card mb-4">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h5 class="font-weight-bold text-white mb-0" style="font-size: 16px;">
                                    <i class="fa fa-terminal text-info mr-2"></i> Crontab Setup &amp; Cron Manager
                                </h5>
                                <div id="cron-status-badge">
                                    <span class="badge badge-success px-2 py-1"><i class="fa fa-check-circle mr-1"></i> Cron Active</span>
                                </div>
                            </div>
                            <p class="text-slate-300 small mb-0" style="color: #94a3b8;">
                                Configure system crontab (`crontab -e`) to execute Totara background jobs every minute.
                            </p>
                        </div>
                        <div>
                            <span class="text-muted small mr-2">Last Cron Execution:</span>
                            <strong id="cron-last-time-str" class="text-info small">Checking...</strong>
                        </div>
                    </div>

                    <div class="cron-code-box">
                        <code id="crontab-cmd-text">* * * * * /usr/bin/php <?php echo s(realpath($CFG->dirroot . '/admin/cli/cron.php')); ?> &gt;/dev/null 2&gt;&amp;1</code>
                        <button type="button" class="btn-copy-cron" id="btn-copy-crontab">
                            <i class="fa fa-copy mr-1"></i> Copy Crontab Command
                        </button>
                    </div>
                </div>

                <!-- Task Stats Overview Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <div class="task-stat-card">
                            <div class="task-stat-icon primary"><i class="fa fa-tasks"></i></div>
                            <div>
                                <div class="text-muted small font-weight-medium">Total Tasks</div>
                                <div class="h4 font-weight-bold text-dark mb-0" id="task-stat-total">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <div class="task-stat-card">
                            <div class="task-stat-icon success"><i class="fa fa-play-circle"></i></div>
                            <div>
                                <div class="text-muted small font-weight-medium">Active Tasks</div>
                                <div class="h4 font-weight-bold text-success mb-0" id="task-stat-active">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <div class="task-stat-card">
                            <div class="task-stat-icon warning"><i class="fa fa-pause-circle"></i></div>
                            <div>
                                <div class="text-muted small font-weight-medium">Disabled Tasks</div>
                                <div class="h4 font-weight-bold text-warning mb-0" id="task-stat-disabled">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="task-stat-card">
                            <div class="task-stat-icon danger"><i class="fa fa-exclamation-triangle"></i></div>
                            <div>
                                <div class="text-muted small font-weight-medium">Failed Tasks</div>
                                <div class="h4 font-weight-bold text-danger mb-0" id="task-stat-failed">0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tasks Filter Bar Toolbar -->
                <div class="filter-bar-single mb-4">
                    <div class="search-box">
                        <i class="fa fa-search"></i>
                        <input type="text" id="task-search-input" class="form-control" placeholder="Search task name or class..." autocomplete="off">
                    </div>

                    <select id="task-component-select" class="custom-select filter-select" style="min-width: 160px;">
                        <option value="">All Components</option>
                        <?php foreach ($log_components as $comp): ?>
                            <option value="<?php echo s($comp); ?>"><?php echo s($comp); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="task-status-select" class="custom-select filter-select font-weight-bold" style="min-width: 140px; color: #2563eb;">
                        <option value="">All Statuses</option>
                        <option value="active">Active Only</option>
                        <option value="disabled">Disabled Only</option>
                        <option value="failed">Failed Only</option>
                    </select>

                    <button type="button" class="btn btn-filter-action" id="btn-apply-task-filters">
                        <i class="fa fa-filter"></i> Filter
                    </button>
                    <button type="button" class="btn btn-reset-action" id="btn-reset-task-filters">Reset</button>
                </div>

                <!-- Scheduled Tasks Table Container -->
                <div class="clean-table-container mb-3" id="tasks-table-wrapper">
                    <table class="clean-table" id="db-tasks-list">
                        <thead>
                            <tr>
                                <th style="width: 4%">#</th>
                                <th style="width: 26%">Task Name &amp; Namespace</th>
                                <th style="width: 12%">Component</th>
                                <th style="width: 15%">Schedule</th>
                                <th style="width: 14%">Last Run</th>
                                <th style="width: 8%">Duration</th>
                                <th style="width: 7%">Failed</th>
                                <th style="width: 8%">Retry</th>
                                <th style="width: 11%" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Footer Summary & AJAX Pagination Container -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 pt-2">
                    <div class="text-secondary small font-weight-medium mb-2 mb-md-0" id="tasks-summary-container">
                        Loading scheduled tasks...
                    </div>
                    <div id="tasks-pagination-container"></div>
                </div>

            </div>

            <!-- Pane 4: SQL Query Runner with Detailed Errors -->
            <div class="tab-pane" id="sql-runner-pane" role="tabpanel" style="display: <?php echo ($tab === 'sql') ? 'block' : 'none'; ?>;">
                <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px; background: #f8fafc;">
                    <div class="card-body p-4">
                        <h6 class="font-weight-bold text-dark mb-2" style="font-size: 1.1rem;">Run Custom Database Query</h6>
                        <p class="text-muted small mb-3">
                            Enter any <code>SELECT</code>, <code>SHOW</code>, <code>EXPLAIN</code> or <code>DESCRIBE</code> SQL command. Use curly braces <code>{table}</code> to automatically append the database prefix. E.g. <code>SELECT * FROM {user} WHERE deleted = 0</code>.
                        </p>
                        
                        <form action="index.php" method="POST" id="sql-runner-form">
                            <input type="hidden" name="tab" value="sql">
                            <div class="form-group mb-3">
                                <textarea name="sql" 
                                          id="sql-input-textarea"
                                          class="form-control sql-textarea" 
                                          rows="5" 
                                          placeholder="SELECT * FROM {user}"><?php echo s($sql); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-filter-action">
                                <i class="fa fa-play mr-1"></i> Run Query
                            </button>
                        </form>
                    </div>
                </div>

                <div class="clean-table-container mb-4" id="sql-results-container">
                    <?php
                    if ($sql !== ''):
                        if (!preg_match('/^(select|show|explain|describe)\b/i', $sql)) {
                            echo '<div class="alert alert-danger m-3 font-size-14">Only SELECT, SHOW, EXPLAIN or DESCRIBE queries are allowed.</div>';
                        } else {
                            try {
                                $clean_sql = rtrim($sql, ';');
                                
                                $total_sql = 0;
                                try {
                                    $countsql = "SELECT COUNT(*) FROM ($clean_sql) temp_count_table";
                                    $total_sql = $DB->count_records_sql($countsql);
                                } catch (\Exception $e) {
                                    $total_sql = 0;
                                }

                                $totalpages_sql = max(1, (int) ceil($total_sql / $perpage));
                                $page_sql = ($tab === 'sql') ? $page : 1;
                                if ($page_sql < 1) $page_sql = 1;
                                else if ($page_sql > $totalpages_sql) $page_sql = $totalpages_sql;
                                
                                $offset_sql = ($page_sql - 1) * $perpage;
                                
                                $recordset = $DB->get_recordset_sql($clean_sql, null, $offset_sql, $perpage);
                                
                                $columns = array();
                                $data_rows = array();
                                foreach ($recordset as $record) {
                                    $record_arr = (array)$record;
                                    if (empty($columns)) {
                                        $columns = array_keys($record_arr);
                                    }
                                    $data_rows[] = $record_arr;
                                }
                                $recordset->close();
                                ?>
                                
                                <div class="p-3 bg-dark text-white d-flex justify-content-between align-items-center" style="border-radius: 8px 8px 0 0;">
                                    <span class="font-weight-bold text-success">
                                        <i class="fa fa-check-circle mr-1"></i> Query Executed Successfully
                                    </span>
                                    <span class="badge badge-info p-2">
                                        <?php echo get_string('rows_returned', 'local_admin_functions', $total_sql > 0 ? $total_sql : count($data_rows)); ?>
                                    </span>
                                </div>

                                <table class="clean-table" id="sql-results-table">
                                    <thead>
                                        <tr>
                                            <?php foreach ($columns as $col): ?>
                                                <th><?php echo s($col); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($data_rows)): ?>
                                            <tr>
                                                <td colspan="<?php echo max(1, count($columns)); ?>" class="text-center py-5 text-muted font-italic">Query executed successfully, but returned no rows.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($data_rows as $row): ?>
                                                <tr>
                                                    <?php foreach ($columns as $col): ?>
                                                        <td class="<?php echo ($col === 'id' || is_numeric($row[$col])) ? 'mono-cell' : ''; ?>">
                                                            <?php
                                                            if ($row[$col] === null) {
                                                                echo '<span class="text-muted font-italic">NULL</span>';
                                                            } else {
                                                                $val_str = (string)$row[$col];
                                                                $clean_val = strip_tags($val_str);
                                                                if (strlen($clean_val) > 80) {
                                                                    $clean_val = substr($clean_val, 0, 77) . '...';
                                                                }
                                                                echo s($clean_val);
                                                            }
                                                            ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            <?php
                            } catch (\Exception $e) {
                                $debug_info = isset($e->debuginfo) ? $e->debuginfo : '';
                                $trace = $debug_active ? $e->getTraceAsString() : '';
                                ?>
                                <div class="card border-danger m-3 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                                    <div class="card-header bg-danger text-white font-weight-bold d-flex align-items-center">
                                        <i class="fa fa-exclamation-triangle mr-2"></i> SQL Execution Error
                                    </div>
                                    <div class="card-body bg-light text-dark p-4 font-size-14">
                                        <div class="alert alert-danger font-weight-bold mb-3" style="font-size: 14px;">
                                            <?php echo s($e->getMessage()); ?>
                                        </div>

                                        <?php if (!empty($debug_info)): ?>
                                            <div class="mb-3">
                                                <label class="font-weight-bold text-secondary text-uppercase small mb-1">Database Driver Debug Info:</label>
                                                <pre class="bg-dark text-warning p-3 rounded font-size-13 m-0"><code><?php echo s($debug_info); ?></code></pre>
                                            </div>
                                        <?php endif; ?>

                                        <div class="mb-3">
                                            <label class="font-weight-bold text-secondary text-uppercase small mb-1">Executed SQL Query:</label>
                                            <pre class="bg-dark text-white p-3 rounded font-size-13 m-0"><code><?php echo s($clean_sql); ?></code></pre>
                                        </div>

                                        <?php if (!empty($trace)): ?>
                                            <div>
                                                <label class="font-weight-bold text-secondary text-uppercase small mb-1">Debug Stack Trace (Admin Only):</label>
                                                <pre class="bg-secondary text-white p-3 rounded font-size-12 m-0" style="max-height: 200px; overflow-y: auto;"><code><?php echo s($trace); ?></code></pre>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                    endif;
                    ?>
                </div>

                <?php
                if ($sql !== '' && isset($total_sql) && $total_sql > 0) {
                    $baseurl_sql = new moodle_url('/local/admin_functions/index.php', array('tab' => 'sql', 'sql' => $sql));
                    echo $OUTPUT->paging_bar($total_sql, ($tab === 'sql') ? $page : 1, $perpage, $baseurl_sql);
                }
                ?>
            </div>

        </div>
    </div>
</div>

<?php if ($is_admin_user): ?>

<!-- ============================================================
     Bootstrap 4 Modal: Admin Debug Mode Toggle Confirmation
     ============================================================ -->
<div class="modal fade" id="debug-confirm-modal"
     tabindex="-1" role="dialog"
     aria-labelledby="debugConfirmModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: #0b1528; padding: 12px 20px;">
                <h5 class="modal-title font-weight-bold d-flex align-items-center mb-0" id="debugConfirmModalLabel" style="font-size: 15px; color: #ffffff;">
                    <i class="fa fa-file-text-o mr-2 text-white"></i> Confirm Debug Mode Toggle
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="color: #ffffff; opacity: 0.9; font-size: 18px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-dark" style="font-size: 14px; line-height: 1.6;">
                <p id="debug-modal-message" class="mb-3 font-weight-medium">
                    Are you sure you want to toggle Developer Debug Mode and show all error messages?
                </p>
                <div class="p-3 bg-light border-left border-danger rounded text-secondary small mb-0">
                    <i class="fa fa-shield text-danger mr-1"></i> <strong>Admin Security Note:</strong> Error messages, warnings, notices, and PHP stack traces will be displayed <strong>only for site administrators</strong>. Non-admin users will continue to have zero error exposure.
                </div>
            </div>
            <div class="modal-footer bg-light py-3 px-4">
                <button type="button" class="btn btn-outline-secondary px-4" id="modal-btn-cancel" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4 font-weight-bold" id="modal-btn-confirm">
                    <i class="fa fa-check mr-1"></i> Confirm &amp; Switch
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Bootstrap 4 Modal: Super Admin Table Selector
     ============================================================ -->
<div class="modal fade" id="table-selector-modal"
     tabindex="-1" role="dialog"
     aria-labelledby="tableSelectorModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <div class="modal-header" style="background: #0b1528; padding: 12px 20px;">
                <h5 class="modal-title font-weight-bold d-flex align-items-center mb-0" id="tableSelectorModalLabel" style="font-size: 15px; color: #ffffff;">
                    <i class="fa fa-file-text-o mr-2 text-white"></i> Select Custom Tables to List
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="color: #ffffff; opacity: 0.9; font-size: 18px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-dark" style="font-size: 14px;">
                <p class="text-muted small mb-3">
                    Check the database tables you want to feature in the <strong>Custom Tables</strong> view. Site administrators can select any combination of tables.
                </p>

                <!-- Search & Quick Selection Controls -->
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="flex-grow-1" style="min-width: 240px; max-width: 320px;">
                        <input type="text" id="ts-modal-search" class="form-control" placeholder="Search tables..." style="padding-left: 12px; height: 38px; border-radius: 6px; font-size: 13px; border: 1px solid #cbd5e1;">
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold" id="ts-select-custom-only" style="border-radius: 6px; font-size: 12px; padding: 6px 12px;">Select Custom/Local Only</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ts-select-all" style="border-radius: 6px; font-size: 12px; padding: 6px 12px;">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="ts-deselect-all" style="border-radius: 6px; font-size: 12px; padding: 6px 12px;">Clear All</button>
                    </div>
                </div>

                <!-- Scrollable Checkbox List -->
                <div class="border rounded bg-light" style="max-height: 380px; overflow-y: auto; overflow-x: hidden; padding: 15px; margin-top: 15px; margin-bottom: 0;" id="ts-checkbox-container">
                    <div class="row">
                        <?php foreach ($db_all_tables as $tbl):
                            $is_checked = in_array($tbl, $custom_tables_list);
                            $is_custom_prefix = (strpos($tbl, 'local_') === 0 || strpos($tbl, 'custom_') === 0 || strpos($tbl, 'activemq_') === 0);
                        ?>
                            <div class="col-md-4 col-sm-6 mb-2 ts-table-item"
                                 data-table-name="<?php echo s($tbl); ?>"
                                 data-is-custom="<?php echo $is_custom_prefix ? '1' : '0'; ?>">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox"
                                           class="custom-control-input ts-table-checkbox"
                                           id="ts-chk-<?php echo s($tbl); ?>"
                                           value="<?php echo s($tbl); ?>"
                                           <?php echo $is_checked ? 'checked' : ''; ?>>
                                    <label class="custom-control-label font-size-13 text-truncate w-100 <?php echo $is_custom_prefix ? 'font-weight-bold text-primary' : 'text-dark'; ?>"
                                           for="ts-chk-<?php echo s($tbl); ?>"
                                           title="<?php echo s($tbl); ?>">
                                        <?php echo s($tbl); ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-3 px-4 d-flex justify-content-between">
                <span class="text-muted small" id="ts-selected-count-badge">
                    Selected: <strong><?php echo count($custom_tables_list); ?></strong> of <?php echo count($db_all_tables); ?> tables
                </span>
                <div>
                    <button type="button" class="btn btn-outline-secondary px-4" id="ts-modal-btn-cancel" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary px-4 font-weight-bold shadow-sm" id="ts-modal-btn-save">
                        <i class="fa fa-save mr-1"></i> Save Selected Tables
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Bootstrap 4 Modal: Log Record Inspector
     ============================================================ -->
<div class="modal fade" id="log-detail-modal"
     tabindex="-1" role="dialog"
     aria-labelledby="logDetailModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <div class="modal-header" style="background: #0b1528; padding: 12px 20px;">
                <h5 class="modal-title font-weight-bold d-flex align-items-center mb-0" id="logDetailModalLabel" style="font-size: 15px; color: #ffffff;">
                    <i class="fa fa-file-text-o mr-2 text-white"></i> Log Record Inspector
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="color: #ffffff; opacity: 0.9; font-size: 18px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-dark" id="log-detail-modal-body" style="font-size: 14px;">
                <div class="text-center py-5 text-muted"><i class="fa fa-spinner fa-spin fa-2x mr-2"></i> Loading log details...</div>
            </div>
            <div class="modal-footer bg-light py-3 px-4">
                <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
<!-- ============================================================
     Bootstrap 4 Modal: Task Log & History Inspector
     ============================================================ -->
<div class="modal fade" id="task-log-modal"
     tabindex="-1" role="dialog"
     aria-labelledby="taskLogModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <div class="modal-header" style="background: #0b1528; padding: 12px 20px;">
                <h5 class="modal-title font-weight-bold d-flex align-items-center mb-0" id="taskLogModalLabel" style="font-size: 15px; color: #ffffff;">
                    <i class="fa fa-terminal mr-2 text-white"></i> Task Logs &amp; Execution Details: <span id="task-modal-name-span" class="ml-1 text-info"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="color: #ffffff; opacity: 0.9; font-size: 18px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4 text-dark" id="task-log-modal-body" style="font-size: 14px;">
                <div class="text-center py-5 text-muted"><i class="fa fa-spinner fa-spin fa-2x mr-2"></i> Loading task log details...</div>
            </div>
            <div class="modal-footer bg-light py-3 px-4 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-primary font-weight-bold" id="task-modal-btn-run-now">
                    <i class="fa fa-bolt mr-1"></i> Run Task Now
                </button>
                <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Bootstrap 4 Modal: Edit Scheduled Task Schedule
     ============================================================ -->
<div class="modal fade" id="task-schedule-modal"
     tabindex="-1" role="dialog"
     aria-labelledby="taskScheduleModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
            <div class="modal-header" style="background: #0b1528; padding: 12px 20px;">
                <h5 class="modal-title font-weight-bold d-flex align-items-center mb-0" id="taskScheduleModalLabel" style="font-size: 15px; color: #ffffff;">
                    <i class="fa fa-pencil mr-2 text-white"></i> Edit Task Schedule
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="color: #ffffff; opacity: 0.9; font-size: 18px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="task-schedule-form">
                <input type="hidden" id="task-edit-class" name="task_class">
                <div class="modal-body p-4 text-dark" style="font-size: 14px;">
                    <div class="alert alert-light border mb-3">
                        <strong class="text-dark d-block mb-1" id="task-edit-name-display">Scheduled Task Name</strong>
                        <code class="small text-muted font-monospace d-block" id="task-edit-class-display">\class\name</code>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="font-weight-bold small text-secondary">Minute (0-59 or *)</label>
                            <input type="text" class="form-control font-monospace" id="task-edit-min" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="font-weight-bold small text-secondary">Hour (0-23 or *)</label>
                            <input type="text" class="form-control font-monospace" id="task-edit-hour" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="font-weight-bold small text-secondary">Day (1-31 or *)</label>
                            <input type="text" class="form-control font-monospace" id="task-edit-day" required>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="font-weight-bold small text-secondary">Month (1-12 or *)</label>
                            <input type="text" class="form-control font-monospace" id="task-edit-month" required>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="font-weight-bold small text-secondary">Day of Week (0-6)</label>
                            <input type="text" class="form-control font-monospace" id="task-edit-dow" required>
                        </div>
                    </div>

                    <div class="custom-control custom-checkbox mt-2">
                        <input type="checkbox" class="custom-control-input" id="task-edit-disabled">
                        <label class="custom-control-label font-weight-bold text-danger" for="task-edit-disabled">
                            Disable Task (Do not run from Cron)
                        </label>
                    </div>
                </div>
                <div class="modal-footer bg-light py-3 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary px-4" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 font-weight-bold" id="btn-save-task-schedule">
                        <i class="fa fa-save mr-1"></i> Save Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
echo $OUTPUT->footer();
