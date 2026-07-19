<?php
/**
 * Standalone page to view detailed information of a single database row.
 * Renders HTML content properly with Moodle clean_text sanitizer.
 * Level 3 of 3-level database navigation.
 *
 * @package    local_admin_functions
 * @copyright  2026 Rosmin Babu
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Apply admin-only debug mode rules.
local_admin_functions_apply_debug_settings();

// Force user login and verify capability.
require_login();
$context = context_system::instance();
if (!is_siteadmin() && !has_capability('moodle/site:config', $context) && !has_capability('local/admin_functions:view', $context)) {
    require_capability('moodle/site:config', $context);
}

$tablename = optional_param('table', '', PARAM_ALPHANUMEXT);
$id        = optional_param('id', 0, PARAM_INT);

// Security check: Verify if table exists in DB.
$all_tables = $DB->get_tables();
if ($tablename !== '' && !in_array($tablename, $all_tables)) {
    print_error('invalidtablename', 'local_admin_functions');
}

// Fetch record if ID is provided.
$record = null;
if ($tablename !== '' && $id > 0) {
    $record = $DB->get_record($tablename, array('id' => $id));
}

// Set up page URL and properties.
$PAGE->set_url(new moodle_url('/local/admin_functions/view_record.php', array('table' => $tablename, 'id' => $id)));
$PAGE->set_context($context);
$PAGE->set_title("Record Details - {$tablename} (#{$id})");
$PAGE->set_heading(get_string('pluginname', 'local_admin_functions'));

// Add CSS and JS requirements.
$PAGE->requires->css(new moodle_url('/local/admin_functions/styles.css'));
$PAGE->requires->js(new moodle_url('/local/admin_functions/assets/js/admin_functions.js'));

echo $OUTPUT->header();

// Helper function to check if string contains valid JSON payload.
function is_valid_json($string, &$parsed) {
    if (!is_string($string)) {
        return false;
    }
    $trimmed = trim($string);
    if (strpos($trimmed, '{') !== 0 && strpos($trimmed, '[') !== 0) {
        return false;
    }
    $decoded = json_decode($string, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $parsed = $decoded;
        return true;
    }
    return false;
}

// Helper function to format timestamp values safely.
function format_time_val($ts) {
    if (!$ts || !is_numeric($ts) || (int)$ts === 0) {
        return 'Never';
    }
    return date('d M Y h:i A', (int)$ts);
}

// Helper function to get clean, friendly key labels.
function get_friendly_key_label($key) {
    $mappings = array(
        'id' => 'ID',
        'table_name' => 'Table Name',
        'description' => 'Description',
        'status' => 'Status',
        'records' => 'Records',
        'timecreated' => 'Created At',
        'timemodified' => 'Updated At',
        'created_by' => 'Created By',
        'user' => 'User',
        'email' => 'Email',
    );
    $lower = strtolower($key);
    if (isset($mappings[$lower])) {
        return $mappings[$lower];
    }
    
    $formatted = str_replace('_', ' ', $key);
    return ucwords($formatted);
}

// Prepare display fields if record exists.
$display_fields = array();
if ($record) {
    foreach ((array)$record as $col => $val) {
        if ($val === null) {
            $display_fields[get_friendly_key_label($col)] = '<span class="text-muted font-italic">NULL</span>';
            continue;
        }
        $friendly_key = get_friendly_key_label($col);
        $parsed_json = null;
        if (is_valid_json($val, $parsed_json)) {
            $display_fields[$friendly_key] = array('json' => json_encode($parsed_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else if (strtolower($col) === 'status' || strtolower($col) === 'active') {
            $display_fields[$friendly_key] = array('status' => (int)$val);
        } else if (strpos(strtolower($col), 'time') !== false || strpos(strtolower($col), 'created') !== false || strpos(strtolower($col), 'modified') !== false) {
            if (is_numeric($val) && (int)$val > 100000000) {
                $display_fields[$friendly_key] = format_time_val($val);
            } else {
                $display_fields[$friendly_key] = $val;
            }
        } else {
            $display_fields[$friendly_key] = $val;
        }
    }
}

$back_url = ($tablename !== '') 
    ? (new moodle_url('/local/admin_functions/table_data.php', array('table' => $tablename)))->out(false) 
    : (new moodle_url('/local/admin_functions/index.php'))->out(false);
?>

<!-- Level 3 Record Details Container -->
<div class="container-fluid px-0 py-2">
    
    <!-- 1. Back to Table Records Link -->
    <div class="mb-3">
        <a href="<?php echo $back_url; ?>" class="back-link-clean">
            <i class="fa fa-arrow-left"></i> <?php echo ($tablename !== '') ? 'Back to ' . s($tablename) . ' Records' : 'Back to Tables List'; ?>
        </a>
    </div>

    <?php if ($record): ?>
        <!-- 2. Header Row -->
        <div class="view-header-row">
            <div>
                <h2 class="admin-page-title">Record Details</h2>
                <p class="admin-page-subtitle">Detailed record inspector for <code><?php echo s($tablename); ?></code> (#<?php echo s($id); ?>)</p>
            </div>
            <div>
                <button class="btn btn-filter-reset" id="btn-download-record-json" 
                        data-filename="<?php echo s($tablename . '_record_' . $id . '.json'); ?>" 
                        data-record="<?php echo s(json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?>">
                    <i class="fa fa-download mr-1"></i> Download JSON
                </button>
            </div>
        </div>

        <!-- 3. Option 1 Clean Key-Value Card -->
        <div class="card view-card-clean mb-4">
            <table class="inspector-details-table-clean">
                <tbody>
                    <?php foreach ($display_fields as $label => $data): ?>
                        <tr>
                            <td class="inspector-key"><?php echo s($label); ?></td>
                            <td class="inspector-val">
                                <?php if (is_array($data) && isset($data['json'])): ?>
                                    <pre class="sql-textarea m-0 p-3" style="max-height: 250px; overflow-y: auto;"><code><?php echo s($data['json']); ?></code></pre>
                                <?php elseif (is_array($data) && isset($data['status'])): ?>
                                    <?php if ($data['status'] === 1 || $data['status'] === 0): ?>
                                        <span class="badge-pill-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge-pill-inactive">Inactive</span>
                                    <?php endif; ?>
                                <?php elseif (is_string($data) && strpos($data, '<') !== false && strpos($data, '>') !== false && $data !== strip_tags($data)): ?>
                                    <!-- Rendered Proper Rich HTML Content -->
                                    <div class="rendered-html-box p-3 border rounded bg-white" style="font-size: 14px; color: #0f172a; line-height: 1.6; max-height: 350px; overflow-y: auto;">
                                        <?php echo clean_text($data, FORMAT_HTML); ?>
                                    </div>
                                <?php else: ?>
                                    <?php echo s($data); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="card view-card-clean p-5 text-center my-4">
            <div class="py-4">
                <i class="fa fa-info-circle text-info mb-3" style="font-size: 3rem;"></i>
                <h4 class="font-weight-bold text-dark mb-2">No Record Selected</h4>
                <p class="text-secondary mb-4">Please select a valid table and record ID to view its full details.</p>
                <a href="<?php echo $back_url; ?>" class="btn btn-filter-primary">
                    <i class="fa fa-arrow-left"></i> Return to Table Records
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bottom Back Button -->
    <div class="mb-4">
        <a href="<?php echo $back_url; ?>" class="btn btn-filter-reset">
            <i class="fa fa-arrow-left mr-1"></i> <?php echo ($tablename !== '') ? 'Back to ' . s($tablename) . ' Records' : 'Back to Tables List'; ?>
        </a>
    </div>

</div>

<!-- JSON Download script hook -->
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    const downloadBtn = document.getElementById('btn-download-record-json');
    if (downloadBtn) {
        downloadBtn.onclick = function(e) {
            e.preventDefault();
            const text = this.getAttribute('data-record');
            const filename = this.getAttribute('data-filename') || 'record.json';
            if (text) {
                const blob = new Blob([text], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
        };
    }
});
</script>

<?php
echo $OUTPUT->footer();
