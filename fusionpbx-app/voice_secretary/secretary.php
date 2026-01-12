<?php
/**
 * Voice Secretary - List Page
 * 
 * Lists all configured secretaries for the current domain.
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

// Include classes
require_once "resources/classes/voice_secretary.php";

// Validate multi-tenant
require_once "resources/classes/domain_validator.php";
domain_validator::init();

// Get data
$secretary = new voice_secretary();
$secretaries = $secretary->list();

// Include header
$document['title'] = $text['title-voice_secretaries'];
require_once "resources/header.php";
?>

<div class="action_bar" id="action_bar">
    <div class="heading">
        <b><?php echo $text['title-voice_secretaries']; ?></b>
    </div>
    <div class="actions">
        <?php if (permission_exists('voice_secretary_add')) { ?>
            <button type="button" onclick="window.location='secretary_edit.php'" class="btn btn-default btn-sm">
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
        <th><?php echo $text['label-secretary_name']; ?></th>
        <th><?php echo $text['label-company_name']; ?></th>
        <th><?php echo $text['label-language']; ?></th>
        <th><?php echo $text['label-status']; ?></th>
        <th><?php echo $text['label-transfer_extension']; ?></th>
        <th class="hide-sm-dn"><?php echo $text['label-created']; ?></th>
    </tr>
    <?php if (is_array($secretaries) && count($secretaries) > 0) { ?>
        <?php foreach ($secretaries as $row) { ?>
            <tr class="list-row">
                <?php if (permission_exists('voice_secretary_delete')) { ?>
                    <td class="checkbox">
                        <input type="checkbox" name="secretaries[]" value="<?php echo $row['voice_secretary_uuid']; ?>">
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
                <td><?php echo escape($row['company_name']); ?></td>
                <td><?php echo escape($row['language']); ?></td>
                <td>
                    <?php if ($row['is_active']) { ?>
                        <span class="badge badge-success"><?php echo $text['label-active']; ?></span>
                    <?php } else { ?>
                        <span class="badge badge-secondary"><?php echo $text['label-inactive']; ?></span>
                    <?php } ?>
                </td>
                <td><?php echo escape($row['transfer_extension']); ?></td>
                <td class="hide-sm-dn"><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
            </tr>
        <?php } ?>
    <?php } else { ?>
        <tr>
            <td colspan="7" class="no_data_found">
                <?php echo $text['message-no_secretaries']; ?>
            </td>
        </tr>
    <?php } ?>
</table>

<?php if (permission_exists('voice_secretary_delete') && is_array($secretaries) && count($secretaries) > 0) { ?>
<div style="margin-top: 15px;">
    <button type="button" id="btn_delete" class="btn btn-default btn-sm" onclick="modal_open('modal-delete','btn_delete');">
        <span class="fas fa-trash fa-fw"></span>
        <?php echo $text['button-delete']; ?>
    </button>
</div>
<?php } ?>

<?php
// Include footer
require_once "resources/footer.php";
?>
