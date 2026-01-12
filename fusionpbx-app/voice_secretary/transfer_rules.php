<?php
/**
 * Voice Secretary - Transfer Rules List Page
 * 
 * Lists transfer rules for voice AI.
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

// Get transfer rules
$database = database::new();
$sql = "SELECT r.*, s.secretary_name 
        FROM v_voice_transfer_rules r
        LEFT JOIN v_voice_secretaries s ON s.voice_secretary_uuid = r.voice_secretary_uuid
        WHERE r.domain_uuid = :domain_uuid 
        ORDER BY r.priority ASC, r.department_name ASC";
$parameters = [];
domain_validator::add_to_parameters($parameters);
$rules = $database->select($sql, $parameters);

// Include header
$document['title'] = $text['title-transfer_rules'];
require_once "resources/header.php";
?>

<div class="action_bar" id="action_bar">
    <div class="heading">
        <b><?php echo $text['title-transfer_rules']; ?></b>
    </div>
    <div class="actions">
        <?php if (permission_exists('voice_secretary_add')) { ?>
            <button type="button" onclick="window.location='transfer_rules_edit.php'" class="btn btn-default btn-sm">
                <span class="fas fa-plus-square fa-fw"></span>
                <?php echo $text['button-add']; ?>
            </button>
        <?php } ?>
    </div>
    <div style="clear: both;"></div>
</div>

<table class="list">
    <tr class="list-header">
        <?php if (permission_exists('voice_secretary_delete')) { ?>
            <th class="checkbox"><input type="checkbox" id="checkbox_all" onclick="checkbox_toggle(this);"></th>
        <?php } ?>
        <th><?php echo $text['label-department']; ?></th>
        <th><?php echo $text['label-keywords']; ?></th>
        <th><?php echo $text['label-extension']; ?></th>
        <th><?php echo $text['label-secretary']; ?></th>
        <th><?php echo $text['label-priority']; ?></th>
        <th><?php echo $text['label-status']; ?></th>
    </tr>
    <?php if (is_array($rules) && count($rules) > 0) { ?>
        <?php foreach ($rules as $row) { ?>
            <tr class="list-row">
                <?php if (permission_exists('voice_secretary_delete')) { ?>
                    <td class="checkbox">
                        <input type="checkbox" name="rules[]" value="<?php echo $row['transfer_rule_uuid']; ?>">
                    </td>
                <?php } ?>
                <td>
                    <?php if (permission_exists('voice_secretary_edit')) { ?>
                        <a href="transfer_rules_edit.php?id=<?php echo urlencode($row['transfer_rule_uuid']); ?>">
                            <?php echo escape($row['department_name']); ?>
                        </a>
                    <?php } else { ?>
                        <?php echo escape($row['department_name']); ?>
                    <?php } ?>
                </td>
                <td>
                    <?php 
                    $keywords = json_decode($row['keywords'], true) ?: [];
                    echo escape(implode(', ', array_slice($keywords, 0, 5)));
                    if (count($keywords) > 5) echo '...';
                    ?>
                </td>
                <td><?php echo escape($row['transfer_extension']); ?></td>
                <td><?php echo escape($row['secretary_name'] ?? '—'); ?></td>
                <td><?php echo intval($row['priority']); ?></td>
                <td>
                    <?php if ($row['is_active']) { ?>
                        <span class="badge badge-success"><?php echo $text['label-active']; ?></span>
                    <?php } else { ?>
                        <span class="badge badge-secondary"><?php echo $text['label-inactive']; ?></span>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
    <?php } else { ?>
        <tr>
            <td colspan="7" class="no_data_found">
                <?php echo $text['message-no_rules']; ?>
            </td>
        </tr>
    <?php } ?>
</table>

<?php
// Include footer
require_once "resources/footer.php";
?>
