<?php
/**
 * Voice Secretary - Conversations History Page
 * 
 * Lists all conversation history.
 * ⚠️ MULTI-TENANT: Uses domain_uuid from session.
 *
 * @package voice_secretary
 */

// Include required files
require_once "root.php";
require_once "resources/require.php";
require_once "resources/check_auth.php";

// Check permission
if (permission_exists('voice_secretary_view')) {
    // Access allowed
} else {
    echo "access denied";
    exit;
}

// Validate multi-tenant
require_once "resources/classes/domain_validator.php";
domain_validator::init();

$domain_uuid = domain_validator::require_domain_uuid();
$database = database::new();

// Filters
$filter_secretary = $_GET['secretary'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build query
$sql = "SELECT c.*, s.secretary_name,
        (SELECT COUNT(*) FROM v_voice_messages m WHERE m.conversation_uuid = c.conversation_uuid) as message_count
        FROM v_voice_conversations c
        LEFT JOIN v_voice_secretaries s ON s.voice_secretary_uuid = c.voice_secretary_uuid
        WHERE c.domain_uuid = :domain_uuid";
$parameters = ['domain_uuid' => $domain_uuid];

if (!empty($filter_secretary)) {
    $sql .= " AND c.voice_secretary_uuid = :secretary";
    $parameters['secretary'] = $filter_secretary;
}

if (!empty($filter_action)) {
    $sql .= " AND c.final_action = :action";
    $parameters['action'] = $filter_action;
}

if (!empty($filter_date_from)) {
    $sql .= " AND c.created_at >= :date_from";
    $parameters['date_from'] = $filter_date_from . ' 00:00:00';
}

if (!empty($filter_date_to)) {
    $sql .= " AND c.created_at <= :date_to";
    $parameters['date_to'] = $filter_date_to . ' 23:59:59';
}

$sql .= " ORDER BY c.created_at DESC LIMIT 100";
$conversations = $database->select($sql, $parameters);

// Get secretaries for filter dropdown
$sql_sec = "SELECT voice_secretary_uuid, secretary_name FROM v_voice_secretaries WHERE domain_uuid = :domain_uuid ORDER BY secretary_name";
$secretaries = $database->select($sql_sec, ['domain_uuid' => $domain_uuid]);

// Include header
$document['title'] = $text['title-conversations'];
require_once "resources/header.php";
?>

<div class="action_bar" id="action_bar">
    <div class="heading">
        <b><?php echo $text['title-conversations']; ?></b>
    </div>
    <div style="clear: both;"></div>
</div>

<!-- Filters -->
<form method="get" class="filter-form" style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 5px;">
    <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
        <div>
            <label><?php echo $text['label-secretary']; ?></label><br>
            <select name="secretary" class="formfld">
                <option value=""><?php echo $text['option-all']; ?></option>
                <?php foreach ($secretaries as $s) { ?>
                    <option value="<?php echo $s['voice_secretary_uuid']; ?>" 
                        <?php echo ($filter_secretary === $s['voice_secretary_uuid']) ? 'selected' : ''; ?>>
                        <?php echo escape($s['secretary_name']); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div>
            <label><?php echo $text['label-action']; ?></label><br>
            <select name="action" class="formfld">
                <option value=""><?php echo $text['option-all']; ?></option>
                <option value="hangup" <?php echo ($filter_action === 'hangup') ? 'selected' : ''; ?>><?php echo $text['action-hangup']; ?></option>
                <option value="transfer" <?php echo ($filter_action === 'transfer') ? 'selected' : ''; ?>><?php echo $text['action-transfer']; ?></option>
                <option value="max_turns" <?php echo ($filter_action === 'max_turns') ? 'selected' : ''; ?>><?php echo $text['action-max_turns']; ?></option>
            </select>
        </div>
        <div>
            <label><?php echo $text['label-date_from']; ?></label><br>
            <input type="date" name="date_from" class="formfld" value="<?php echo escape($filter_date_from); ?>">
        </div>
        <div>
            <label><?php echo $text['label-date_to']; ?></label><br>
            <input type="date" name="date_to" class="formfld" value="<?php echo escape($filter_date_to); ?>">
        </div>
        <div>
            <button type="submit" class="btn btn-primary btn-sm">
                <span class="fas fa-filter fa-fw"></span>
                <?php echo $text['button-filter']; ?>
            </button>
            <a href="conversations.php" class="btn btn-default btn-sm">
                <span class="fas fa-times fa-fw"></span>
                <?php echo $text['button-clear']; ?>
            </a>
        </div>
    </div>
</form>

<table class="list">
    <tr class="list-header">
        <th><?php echo $text['label-date']; ?></th>
        <th><?php echo $text['label-caller_id']; ?></th>
        <th><?php echo $text['label-secretary']; ?></th>
        <th><?php echo $text['label-messages']; ?></th>
        <th><?php echo $text['label-duration']; ?></th>
        <th><?php echo $text['label-action']; ?></th>
        <th></th>
    </tr>
    <?php if (is_array($conversations) && count($conversations) > 0) { ?>
        <?php foreach ($conversations as $row) { ?>
            <tr class="list-row">
                <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                <td><?php echo escape($row['caller_id']); ?></td>
                <td><?php echo escape($row['secretary_name'] ?? '—'); ?></td>
                <td><?php echo intval($row['message_count']); ?></td>
                <td><?php echo format_duration($row['duration_seconds']); ?></td>
                <td>
                    <?php if ($row['final_action'] === 'transfer') { ?>
                        <span class="badge badge-info">
                            <i class="fas fa-exchange-alt"></i> 
                            <?php echo escape($row['transfer_target']); ?>
                        </span>
                    <?php } elseif ($row['final_action'] === 'hangup') { ?>
                        <span class="badge badge-success">
                            <i class="fas fa-phone-slash"></i> 
                            <?php echo $text['action-resolved']; ?>
                        </span>
                    <?php } else { ?>
                        <span class="badge badge-warning"><?php echo escape($row['final_action']); ?></span>
                    <?php } ?>
                </td>
                <td>
                    <a href="conversation_detail.php?id=<?php echo urlencode($row['conversation_uuid']); ?>" class="btn btn-default btn-xs">
                        <span class="fas fa-eye fa-fw"></span>
                        <?php echo $text['button-view']; ?>
                    </a>
                </td>
            </tr>
        <?php } ?>
    <?php } else { ?>
        <tr>
            <td colspan="7" class="no_data_found">
                <?php echo $text['message-no_conversations']; ?>
            </td>
        </tr>
    <?php } ?>
</table>

<?php
function format_duration($seconds) {
    if (!$seconds) return '—';
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    return sprintf('%d:%02d', $minutes, $secs);
}

// Include footer
require_once "resources/footer.php";
?>
