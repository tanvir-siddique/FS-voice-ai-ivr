<?php
/*
	FusionPBX
	Version: MPL 1.1

	Voice Secretary - Documents List
	Listagem de documentos na base de conhecimento.
	⚠️ MULTI-TENANT: Usa domain_uuid da sessão.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('voice_secretary_view')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get domain_uuid from session
	$domain_uuid = $_SESSION['domain_uuid'] ?? null;
	if (!$domain_uuid) {
		echo "Error: domain_uuid not found in session.";
		exit;
	}

//get documents
	$database = new database;
	$sql = "SELECT * FROM v_voice_documents WHERE domain_uuid = :domain_uuid ORDER BY insert_date DESC";
	$parameters['domain_uuid'] = $domain_uuid;
	$documents = $database->select($sql, $parameters, 'all');
	unset($parameters);

//set the title
	$document['title'] = $text['title-voice_documents'] ?? 'Documents';

//include the header
	require_once "resources/header.php";

//include tab navigation
	$current_page = 'documents';
	require_once "resources/nav_tabs.php";

?>

<div class="action_bar" id="action_bar">
	<div class="heading">
		<b><?php echo $text['title-voice_documents'] ?? 'Documents'; ?></b>
	</div>
	<div class="actions">
		<?php if (permission_exists('voice_secretary_add')) { ?>
			<button type="button" onclick="window.location='documents_edit.php'" class="btn btn-default">
				<span class="fas fa-upload fa-fw"></span>
				<span class="button-label hide-sm-dn"><?php echo $text['button-upload'] ?? 'Upload'; ?></span>
			</button>
		<?php } ?>
	</div>
	<div style="clear: both;"></div>
</div>

<table class="list">
	<tr class="list-header">
		<th><?php echo $text['label-document_name'] ?? 'Name'; ?></th>
		<th><?php echo $text['label-document_type'] ?? 'Type'; ?></th>
		<th><?php echo $text['label-chunks'] ?? 'Chunks'; ?></th>
		<th class="center"><?php echo $text['label-status'] ?? 'Status'; ?></th>
		<th class="hide-sm-dn"><?php echo $text['label-created'] ?? 'Created'; ?></th>
		<th class="right"><?php echo $text['label-actions'] ?? 'Actions'; ?></th>
	</tr>
	<?php if (is_array($documents) && count($documents) > 0) { ?>
		<?php foreach ($documents as $row) { ?>
			<tr class="list-row">
				<td>
					<?php if (permission_exists('voice_secretary_edit')) { ?>
						<a href="documents_edit.php?id=<?php echo urlencode($row['voice_document_uuid']); ?>">
							<?php echo escape($row['document_name']); ?>
						</a>
					<?php } else { ?>
						<?php echo escape($row['document_name']); ?>
					<?php } ?>
				</td>
				<td>
					<span class="badge badge-info"><?php echo strtoupper(escape($row['document_type'] ?? 'txt')); ?></span>
				</td>
				<td><?php echo intval($row['chunk_count'] ?? 0); ?></td>
				<td class="center">
					<?php if ($row['enabled'] == 't' || $row['enabled'] == true || $row['enabled'] == '1') { ?>
						<span class="badge badge-success"><?php echo $text['label-enabled'] ?? 'Enabled'; ?></span>
					<?php } else { ?>
						<span class="badge badge-secondary"><?php echo $text['label-disabled'] ?? 'Disabled'; ?></span>
					<?php } ?>
				</td>
				<td class="hide-sm-dn">
					<?php echo $row['insert_date'] ? date('d/m/Y H:i', strtotime($row['insert_date'])) : '—'; ?>
				</td>
				<td class="right">
					<?php if (permission_exists('voice_secretary_edit')) { ?>
						<a href="documents_edit.php?id=<?php echo urlencode($row['voice_document_uuid']); ?>" class="btn btn-default btn-xs">
							<span class="fas fa-edit fa-fw"></span>
						</a>
					<?php } ?>
					<?php if (permission_exists('voice_secretary_delete')) { ?>
						<button type="button" class="btn btn-default btn-xs" onclick="if(confirm('<?php echo $text['confirm-delete'] ?? 'Delete?'; ?>')){window.location='documents.php?action=delete&id=<?php echo urlencode($row['voice_document_uuid']); ?>';}">
							<span class="fas fa-trash fa-fw"></span>
						</button>
					<?php } ?>
				</td>
			</tr>
		<?php } ?>
	<?php } else { ?>
		<tr>
			<td colspan="6" class="no-data-found">
				<?php echo $text['message-no_documents'] ?? 'No documents found.'; ?>
			</td>
		</tr>
	<?php } ?>
</table>

<?php
//include the footer
	require_once "resources/footer.php";
?>
