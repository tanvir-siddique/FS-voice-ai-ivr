<?php
/**
 * Voice Secretary - List Page
 * 
 * Lists all configured secretaries for the current domain.
 * ⚠️ MULTI-TENANT: Uses domain_uuid from session.
 *
 * @package voice_secretary
 */

// FusionPBX includes
$includes_root = dirname(__DIR__, 2);
require_once $includes_root . "/resources/require.php";
require_once $includes_root . "/resources/check_auth.php";

// Check permission
if (permission_exists('voice_secretary_view')) {
    // Access allowed
} else {
    echo "access denied";
    exit;
}

// Add multi-select js
$document['title'] = $text['title-voice_secretaries'] ?? 'Voice Secretaries';
require_once $includes_root . "/resources/header.php";

// Include class
require_once __DIR__ . "/resources/classes/voice_secretary.php";

// Validate domain_uuid from session
$domain_uuid = $_SESSION['domain_uuid'] ?? null;
if (!$domain_uuid) {
    echo "Error: domain_uuid not found in session.";
    exit;
}

// Get data
$secretary = new voice_secretary();
$secretaries = $secretary->get_list($domain_uuid);
?>

<div class="action_bar" id="action_bar">
    <div class="heading">
        <b><?php echo $text['title-voice_secretaries'] ?? 'Voice Secretaries'; ?></b>
    </div>
    <div class="actions">
        <?php if (permission_exists('voice_secretary_add')) { ?>
            <button type="button" onclick="window.location='secretary_edit.php'" class="btn btn-default btn-sm">
                <span class="fas fa-plus fa-fw"></span>
                <?php echo $text['button-add'] ?? 'Add'; ?>
            </button>
        <?php } ?>
    </div>
    <div style="clear: both;"></div>
</div>

<table class="list">
    <tr class="list-header">
        <?php if (permission_exists('voice_secretary_delete')) { ?>
            <th class="checkbox"><input type="checkbox" id="checkbox_all" onclick="list_all_toggle();"></th>
        <?php } ?>
        <th><?php echo $text['label-secretary_name'] ?? 'Name'; ?></th>
        <th><?php echo $text['label-company_name'] ?? 'Company'; ?></th>
        <th><?php echo $text['label-extension'] ?? 'Extension'; ?></th>
        <th><?php echo $text['label-processing_mode'] ?? 'Mode'; ?></th>
        <th><?php echo $text['label-status'] ?? 'Status'; ?></th>
        <th class="hide-sm-dn"><?php echo $text['label-created'] ?? 'Created'; ?></th>
    </tr>
    <?php if (is_array($secretaries) && count($secretaries) > 0) { ?>
        <?php foreach ($secretaries as $row) { ?>
            <tr class="list-row">
                <?php if (permission_exists('voice_secretary_delete')) { ?>
                    <td class="checkbox">
                        <input type="checkbox" name="secretaries[]" id="checkbox_<?php echo $row['voice_secretary_uuid']; ?>" value="<?php echo $row['voice_secretary_uuid']; ?>" onclick="list_row_toggle('<?php echo $row['voice_secretary_uuid']; ?>');">
                    </td>
                <?php } ?>
                <td>
                    <?php if (permission_exists('voice_secretary_edit')) { ?>
                        <a href="secretary_edit.php?id=<?php echo urlencode($row['voice_secretary_uuid']); ?>">
                            <?php echo escape($row['secretary_name']); ?>
                        </a>
                    <?php } else { ?>
                        <?php echo escape($row['secretary_name']); ?>
                    <?php } ?>
                </td>
                <td><?php echo escape($row['company_name'] ?? ''); ?></td>
                <td><?php echo escape($row['extension'] ?? ''); ?></td>
                <td>
                    <?php 
                    $mode = $row['processing_mode'] ?? 'turn_based';
                    $mode_label = [
                        'turn_based' => 'Turn-based (v1)',
                        'realtime' => 'Realtime (v2)',
                        'auto' => 'Auto'
                    ];
                    echo $mode_label[$mode] ?? $mode;
                    ?>
                </td>
                <td>
                    <?php if ($row['is_enabled'] ?? true) { ?>
                        <span class="badge bg-success"><?php echo $text['label-enabled'] ?? 'Enabled'; ?></span>
                    <?php } else { ?>
                        <span class="badge bg-secondary"><?php echo $text['label-disabled'] ?? 'Disabled'; ?></span>
                    <?php } ?>
                </td>
                <td class="hide-sm-dn">
                    <?php 
                    if (!empty($row['insert_date'])) {
                        echo date('d/m/Y H:i', strtotime($row['insert_date'])); 
                    }
                    ?>
                </td>
            </tr>
        <?php } ?>
    <?php } else { ?>
        <tr>
            <td colspan="7" class="no-results-found">
                <?php echo $text['message-no_secretaries'] ?? 'No secretaries found. Click Add to create one.'; ?>
            </td>
        </tr>
    <?php } ?>
</table>

<?php if (permission_exists('voice_secretary_delete') && is_array($secretaries) && count($secretaries) > 0) { ?>
<div style="margin-top: 15px;">
    <button type="button" id="btn_delete" class="btn btn-default btn-sm" onclick="modal_open('modal-delete','btn_delete');">
        <span class="fas fa-trash fa-fw"></span>
        <?php echo $text['button-delete'] ?? 'Delete'; ?>
    </button>
</div>

<?php 
// Delete modal
echo modal::create([
    'id' => 'modal-delete',
    'type' => 'delete',
    'actions' => "<button type='button' class='btn btn-primary' id='btn_delete_confirm' onclick=\"list_action_set('delete'); list_form_submit('form_list');\">" . ($text['button-continue'] ?? 'Continue') . "</button>"
]);
?>
<?php } ?>

<?php
// Include footer
require_once $includes_root . "/resources/footer.php";
?>
