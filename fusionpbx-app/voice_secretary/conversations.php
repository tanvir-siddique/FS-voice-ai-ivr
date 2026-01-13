<?php
/*
	FusionPBX
	Version: MPL 1.1

	Voice Secretary - Conversations History
	Histórico de conversas/atendimentos.
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

//filters
	$filter_secretary = $_GET['secretary'] ?? '';
	$filter_action = $_GET['action_filter'] ?? '';
	$filter_date_from = $_GET['date_from'] ?? '';
	$filter_date_to = $_GET['date_to'] ?? '';

//get secretaries for filter
	$database = new database;
	$sql = "SELECT voice_secretary_uuid, secretary_name FROM v_voice_secretaries WHERE domain_uuid = :domain_uuid ORDER BY secretary_name";
	$parameters['domain_uuid'] = $domain_uuid;
	$secretaries = $database->select($sql, $parameters, 'all') ?: [];
	unset($parameters);

//build query
	$sql = "SELECT c.*, s.secretary_name
			FROM v_voice_conversations c
			LEFT JOIN v_voice_secretaries s ON s.voice_secretary_uuid = c.voice_secretary_uuid
			WHERE c.domain_uuid = :domain_uuid";
	$parameters['domain_uuid'] = $domain_uuid;

	if (!empty($filter_secretary)) {
		$sql .= " AND c.voice_secretary_uuid = :secretary";
		$parameters['secretary'] = $filter_secretary;
	}
	if (!empty($filter_action)) {
		$sql .= " AND c.final_action = :action";
		$parameters['action'] = $filter_action;
	}
	if (!empty($filter_date_from)) {
		$sql .= " AND c.insert_date >= :date_from";
		$parameters['date_from'] = $filter_date_from . ' 00:00:00';
	}
	if (!empty($filter_date_to)) {
		$sql .= " AND c.insert_date <= :date_to";
		$parameters['date_to'] = $filter_date_to . ' 23:59:59';
	}
	$sql .= " ORDER BY c.insert_date DESC LIMIT 100";

	$conversations = $database->select($sql, $parameters, 'all') ?: [];
	unset($parameters);

//set the title
	$document['title'] = $text['title-conversations'] ?? 'Conversations';

//include the header
	require_once "resources/header.php";

//include tab navigation
	$current_page = 'conversations';
	require_once "resources/nav_tabs.php";

?>

<div class="action_bar" id="action_bar">
	<div class="heading">
		<b><?php echo $text['title-conversations'] ?? 'Conversations'; ?></b>
	</div>
	<div style="clear: both;"></div>
</div>

<!-- Filters -->
<form method="get" style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 5px;">
	<div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
		<div>
			<label><?php echo $text['label-secretary'] ?? 'Secretary'; ?></label><br>
			<select name="secretary" class="formfld">
				<option value=""><?php echo $text['option-all'] ?? 'All'; ?></option>
				<?php foreach ($secretaries as $s) { ?>
					<option value="<?php echo escape($s['voice_secretary_uuid']); ?>" 
						<?php echo ($filter_secretary === $s['voice_secretary_uuid']) ? 'selected' : ''; ?>>
						<?php echo escape($s['secretary_name']); ?>
					</option>
				<?php } ?>
			</select>
		</div>
		<div>
			<label><?php echo $text['label-action'] ?? 'Action'; ?></label><br>
			<select name="action_filter" class="formfld">
				<option value=""><?php echo $text['option-all'] ?? 'All'; ?></option>
				<option value="hangup" <?php echo ($filter_action === 'hangup') ? 'selected' : ''; ?>><?php echo $text['action-hangup'] ?? 'Hangup'; ?></option>
				<option value="transfer" <?php echo ($filter_action === 'transfer') ? 'selected' : ''; ?>><?php echo $text['action-transfer'] ?? 'Transfer'; ?></option>
				<option value="max_turns" <?php echo ($filter_action === 'max_turns') ? 'selected' : ''; ?>><?php echo $text['action-max_turns'] ?? 'Max Turns'; ?></option>
			</select>
		</div>
		<div>
			<label><?php echo $text['label-date_from'] ?? 'From'; ?></label><br>
			<input type="date" name="date_from" class="formfld" value="<?php echo escape($filter_date_from); ?>">
		</div>
		<div>
			<label><?php echo $text['label-date_to'] ?? 'To'; ?></label><br>
			<input type="date" name="date_to" class="formfld" value="<?php echo escape($filter_date_to); ?>">
		</div>
		<div>
			<button type="submit" class="btn btn-primary">
				<span class="fas fa-filter fa-fw"></span>
				<?php echo $text['button-filter'] ?? 'Filter'; ?>
			</button>
			<a href="conversations.php" class="btn btn-default">
				<span class="fas fa-times fa-fw"></span>
				<?php echo $text['button-clear'] ?? 'Clear'; ?>
			</a>
		</div>
	</div>
</form>

<table class="list">
	<tr class="list-header">
		<th><?php echo $text['label-date'] ?? 'Date'; ?></th>
		<th><?php echo $text['label-caller_id'] ?? 'Caller ID'; ?></th>
		<th><?php echo $text['label-secretary'] ?? 'Secretary'; ?></th>
		<th><?php echo $text['label-turns'] ?? 'Turns'; ?></th>
		<th><?php echo $text['label-action'] ?? 'Action'; ?></th>
		<th></th>
	</tr>
	<?php if (is_array($conversations) && count($conversations) > 0) { ?>
		<?php foreach ($conversations as $row) { ?>
			<tr class="list-row">
				<td><?php echo $row['insert_date'] ? date('d/m/Y H:i', strtotime($row['insert_date'])) : '—'; ?></td>
				<td><?php echo escape($row['caller_id_number'] ?? '—'); ?></td>
				<td><?php echo escape($row['secretary_name'] ?? '—'); ?></td>
				<td><?php echo intval($row['total_turns'] ?? 0); ?></td>
				<td>
					<?php if ($row['final_action'] === 'transfer') { ?>
						<span class="badge badge-info">
							<i class="fas fa-exchange-alt"></i> 
							<?php echo escape($row['transfer_extension'] ?? ''); ?>
						</span>
					<?php } elseif ($row['final_action'] === 'hangup') { ?>
						<span class="badge badge-success">
							<i class="fas fa-check"></i> 
							<?php echo $text['action-resolved'] ?? 'Resolved'; ?>
						</span>
					<?php } elseif ($row['final_action'] === 'max_turns') { ?>
						<span class="badge badge-warning">
							<i class="fas fa-exclamation-triangle"></i> 
							<?php echo $text['action-max_turns'] ?? 'Max Turns'; ?>
						</span>
					<?php } else { ?>
						<span class="badge badge-secondary"><?php echo escape($row['final_action'] ?? '—'); ?></span>
					<?php } ?>
				</td>
				<td class="right">
					<a href="conversation_detail.php?id=<?php echo urlencode($row['voice_conversation_uuid']); ?>" class="btn btn-default btn-xs">
						<span class="fas fa-eye fa-fw"></span>
						<?php echo $text['button-view'] ?? 'View'; ?>
					</a>
				</td>
			</tr>
		<?php } ?>
	<?php } else { ?>
		<tr>
			<td colspan="6" class="no-data-found">
				<?php echo $text['message-no_conversations'] ?? 'No conversations found.'; ?>
			</td>
		</tr>
	<?php } ?>
</table>

<?php
//include the footer
	require_once "resources/footer.php";
?>
