<?php
/**
 * Voice Secretary - Documents List Page
 * 
 * Lists all documents in the knowledge base.
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

// Get documents
$database = database::new();
$sql = "SELECT d.*, 
        (SELECT COUNT(*) FROM v_voice_document_chunks c WHERE c.document_uuid = d.document_uuid) as chunk_count
        FROM v_voice_documents d 
        WHERE d.domain_uuid = :domain_uuid 
        ORDER BY d.created_at DESC";
$parameters = [];
domain_validator::add_to_parameters($parameters);
$documents = $database->select($sql, $parameters);

// Include header
$document['title'] = $text['title-voice_documents'];
require_once "resources/header.php";
?>

<div class="action_bar" id="action_bar">
    <div class="heading">
        <b><?php echo $text['title-voice_documents']; ?></b>
    </div>
    <div class="actions">
        <?php if (permission_exists('voice_secretary_add')) { ?>
            <button type="button" onclick="window.location='documents_edit.php'" class="btn btn-default btn-sm">
                <span class="fas fa-upload fa-fw"></span>
                <?php echo $text['button-upload']; ?>
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
        <th><?php echo $text['label-document_name']; ?></th>
        <th><?php echo $text['label-file_type']; ?></th>
        <th><?php echo $text['label-file_size']; ?></th>
        <th><?php echo $text['label-chunks']; ?></th>
        <th><?php echo $text['label-status']; ?></th>
        <th class="hide-sm-dn"><?php echo $text['label-created']; ?></th>
    </tr>
    <?php if (is_array($documents) && count($documents) > 0) { ?>
        <?php foreach ($documents as $row) { ?>
            <tr class="list-row">
                <?php if (permission_exists('voice_secretary_delete')) { ?>
                    <td class="checkbox">
                        <input type="checkbox" name="documents[]" value="<?php echo $row['document_uuid']; ?>">
                    </td>
                <?php } ?>
                <td>
                    <?php echo escape($row['document_name']); ?>
                </td>
                <td>
                    <span class="badge badge-info"><?php echo strtoupper(escape($row['file_type'])); ?></span>
                </td>
                <td><?php echo format_file_size($row['file_size']); ?></td>
                <td><?php echo intval($row['chunk_count']); ?></td>
                <td>
                    <?php if ($row['processing_status'] === 'completed') { ?>
                        <span class="badge badge-success"><?php echo $text['status-completed']; ?></span>
                    <?php } elseif ($row['processing_status'] === 'processing') { ?>
                        <span class="badge badge-warning"><?php echo $text['status-processing']; ?></span>
                    <?php } elseif ($row['processing_status'] === 'failed') { ?>
                        <span class="badge badge-danger"><?php echo $text['status-failed']; ?></span>
                    <?php } else { ?>
                        <span class="badge badge-secondary"><?php echo $text['status-pending']; ?></span>
                    <?php } ?>
                </td>
                <td class="hide-sm-dn"><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
            </tr>
        <?php } ?>
    <?php } else { ?>
        <tr>
            <td colspan="7" class="no_data_found">
                <?php echo $text['message-no_documents']; ?>
            </td>
        </tr>
    <?php } ?>
</table>

<?php
function format_file_size($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

// Include footer
require_once "resources/footer.php";
?>
