<?php
/*
	FusionPBX
	Version: MPL 1.1

	Voice Secretary - Secretária Virtual com IA
	Listagem de secretárias configuradas para o domínio.
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

//include class
	require_once "resources/classes/voice_secretary.php";

//get domain_uuid from session
	$domain_uuid = $_SESSION['domain_uuid'] ?? null;
	if (!$domain_uuid) {
		echo "Error: domain_uuid not found in session.";
		exit;
	}

//process delete action
	if ($_POST['action'] == 'delete' && permission_exists('voice_secretary_delete')) {
		$array = $_POST['secretaries'] ?? [];
		if (is_array($array) && count($array) > 0) {
			$secretary = new voice_secretary;
			$deleted = 0;
			foreach ($array as $uuid) {
				if (is_uuid($uuid)) {
					$result = $secretary->delete($uuid, $domain_uuid);
					if ($result) $deleted++;
				}
			}
			$_SESSION['message'] = "Deleted $deleted secretary(ies).";
			header('Location: secretary.php');
			exit;
		}
	}

//get data
	$secretary = new voice_secretary;
	$secretaries = $secretary->get_list($domain_uuid);

//set the title
	$document['title'] = $text['title-voice_secretaries'] ?? 'Voice Secretaries';

//include the header
	require_once "resources/header.php";

//include tab navigation
	$current_page = 'secretaries';
	require_once "resources/nav_tabs.php";

?>

<div class="action_bar" id="action_bar">
	<div class="heading">
		<b><?php echo $text['title-voice_secretaries'] ?? 'Voice Secretaries'; ?></b>
	</div>
	<div class="actions">
		<?php if (permission_exists('voice_secretary_add')) { ?>
			<button type="button" alt="<?php echo $text['button-add'] ?? 'Add'; ?>" onclick="window.location='secretary_edit.php'" class="btn btn-default">
				<span class="fas fa-plus fa-fw"></span>
				<span class="button-label hide-sm-dn"><?php echo $text['button-add'] ?? 'Add'; ?></span>
			</button>
		<?php } ?>
	</div>
	<div style="clear: both;"></div>
</div>

<form id="form_list" method="post">
<input type="hidden" id="action" name="action" value="">

<table class="list">
	<tr class="list-header">
		<?php if (permission_exists('voice_secretary_delete')) { ?>
			<th class="checkbox"><input type="checkbox" id="checkbox_all" name="checkbox_all" onclick="list_all_toggle();"></th>
		<?php } ?>
		<th><?php echo $text['label-secretary_name'] ?? 'Name'; ?></th>
		<th><?php echo $text['label-company_name'] ?? 'Company'; ?></th>
		<th><?php echo $text['label-extension'] ?? 'Extension'; ?></th>
		<th><?php echo $text['label-processing_mode'] ?? 'Mode'; ?></th>
		<th class="center"><?php echo $text['label-status'] ?? 'Status'; ?></th>
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
				<td class="center">
					<?php if (($row['enabled'] ?? ($row['is_enabled'] ?? true)) == true) { ?>
						<span class="badge badge-success"><?php echo $text['label-enabled'] ?? 'Enabled'; ?></span>
					<?php } else { ?>
						<span class="badge badge-secondary"><?php echo $text['label-disabled'] ?? 'Disabled'; ?></span>
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
	<button type="button" id="btn_delete" class="btn btn-default" onclick="modal_open('modal-delete','btn_delete');">
		<span class="fas fa-trash fa-fw"></span>
		<span class="button-label hide-sm-dn"><?php echo $text['button-delete'] ?? 'Delete'; ?></span>
	</button>
</div>

<?php 
//delete modal
echo modal::create([
	'id' => 'modal-delete',
	'type' => 'delete',
	'actions' => button::create(['type'=>'button','label'=>$text['button-continue'] ?? 'Continue','icon'=>'check','id'=>'btn_delete_confirm','style'=>'float: right; margin-left: 15px;','collapse'=>'never','onclick'=>"list_action_set('delete'); list_form_submit('form_list');"])
]);
?>
<?php } ?>

</form>

<?php

//include the footer
	require_once "resources/footer.php";

?>
