<?php
/*
	FusionPBX
	Version: MPL 1.1

	Voice Secretary - Secretária Virtual com IA
	Criar ou editar uma secretária virtual.
	⚠️ MULTI-TENANT: Usa domain_uuid da sessão.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('voice_secretary_add') || permission_exists('voice_secretary_edit')) {
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

//initialize
	$secretary_obj = new voice_secretary;
	$action = 'add';
	$data = [];

//check if editing existing
	if (isset($_GET['id']) && is_uuid($_GET['id'])) {
		$action = 'edit';
		$secretary_uuid = $_GET['id'];
		$data = $secretary_obj->get($secretary_uuid, $domain_uuid);
		
		if (!$data) {
			message::add($text['message-secretary_not_found'] ?? 'Secretary not found', 'negative');
			header('Location: secretary.php');
			exit;
		}
	}

//process form submission
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && count($_POST) > 0) {
		//collect form data
		$form_data = [
			'secretary_name' => $_POST['secretary_name'] ?? '',
			'company_name' => $_POST['company_name'] ?? '',
			'system_prompt' => $_POST['system_prompt'] ?? '',
			'greeting_message' => $_POST['greeting_message'] ?? '',
			'farewell_message' => $_POST['farewell_message'] ?? '',
			'processing_mode' => $_POST['processing_mode'] ?? 'turn_based',
			'realtime_provider_uuid' => $_POST['realtime_provider_uuid'] ?? null,
			'extension' => $_POST['extension'] ?? '',
			'stt_provider_uuid' => $_POST['stt_provider_uuid'] ?? null,
			'tts_provider_uuid' => $_POST['tts_provider_uuid'] ?? null,
			'llm_provider_uuid' => $_POST['llm_provider_uuid'] ?? null,
			'embeddings_provider_uuid' => $_POST['embeddings_provider_uuid'] ?? null,
			'tts_voice' => $_POST['tts_voice'] ?? '',
			'language' => $_POST['language'] ?? 'pt-BR',
			'max_turns' => intval($_POST['max_turns'] ?? 20),
			'transfer_extension' => $_POST['transfer_extension'] ?? '200',
			'is_active' => isset($_POST['is_active']) ? true : false,
			'webhook_url' => $_POST['webhook_url'] ?? '',
		];
		
		//validate
		if (empty($form_data['secretary_name'])) {
			message::add($text['message-name_required'] ?? 'Name is required', 'negative');
		} 
		else {
			try {
				if ($action === 'add') {
					$secretary_obj->create($form_data, $domain_uuid);
					message::add($text['message-add'] ?? 'Secretary created successfully');
				} 
				else {
					$secretary_obj->update($secretary_uuid, $form_data, $domain_uuid);
					message::add($text['message-update'] ?? 'Secretary updated successfully');
				}
				header('Location: secretary.php');
				exit;
			} 
			catch (Exception $e) {
				message::add(($text['message-error'] ?? 'Error') . ': ' . $e->getMessage(), 'negative');
			}
		}
	}

//get providers for dropdowns
	$stt_providers = $secretary_obj->get_providers('stt', $domain_uuid) ?: [];
	$tts_providers = $secretary_obj->get_providers('tts', $domain_uuid) ?: [];
	$llm_providers = $secretary_obj->get_providers('llm', $domain_uuid) ?: [];
	$embeddings_providers = $secretary_obj->get_providers('embeddings', $domain_uuid) ?: [];
	$realtime_providers = $secretary_obj->get_providers('realtime', $domain_uuid) ?: [];

//set the title
	$document['title'] = ($action === 'add') 
		? ($text['title-voice_secretary_add'] ?? 'Add Secretary') 
		: ($text['title-voice_secretary_edit'] ?? 'Edit Secretary');

//include the header
	require_once "resources/header.php";

?>

<form method="post" name="frm" id="frm">

<div class="action_bar" id="action_bar">
	<div class="heading">
		<b><?php echo ($action === 'add') ? ($text['title-voice_secretary_add'] ?? 'Add Secretary') : ($text['title-voice_secretary_edit'] ?? 'Edit Secretary'); ?></b>
	</div>
	<div class="actions">
		<button type="submit" class="btn btn-primary">
			<span class="fas fa-save fa-fw"></span>
			<span class="button-label hide-sm-dn"><?php echo $text['button-save'] ?? 'Save'; ?></span>
		</button>
		<button type="button" onclick="window.location='secretary.php'" class="btn btn-default">
			<span class="fas fa-times fa-fw"></span>
			<span class="button-label hide-sm-dn"><?php echo $text['button-back'] ?? 'Back'; ?></span>
		</button>
	</div>
	<div style="clear: both;"></div>
</div>

<?php echo $text['description-voice_secretary'] ?? ''; ?>
<br><br>

<table width="100%" border="0" cellpadding="0" cellspacing="0">

	<tr>
		<td width="30%" class="vncellreq" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-secretary_name'] ?? 'Name'; ?>
		</td>
		<td width="70%" class="vtable" align="left">
			<input class="formfld" type="text" name="secretary_name" maxlength="255" value="<?php echo escape($data['secretary_name'] ?? ''); ?>" required>
			<br><?php echo $text['description-secretary_name'] ?? 'Enter a name for this secretary.'; ?>
		</td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-company_name'] ?? 'Company'; ?>
		</td>
		<td class="vtable" align="left">
			<input class="formfld" type="text" name="company_name" maxlength="255" value="<?php echo escape($data['company_name'] ?? ''); ?>">
		</td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-extension'] ?? 'Extension'; ?>
		</td>
		<td class="vtable" align="left">
			<input class="formfld" type="text" name="extension" maxlength="20" value="<?php echo escape($data['extension'] ?? ''); ?>" placeholder="8000">
			<br><?php echo $text['description-extension'] ?? 'Extension number for this secretary.'; ?>
		</td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-language'] ?? 'Language'; ?>
		</td>
		<td class="vtable" align="left">
			<select class="formfld" name="language">
				<option value="pt-BR" <?php echo (($data['language'] ?? 'pt-BR') === 'pt-BR') ? 'selected' : ''; ?>>Português (Brasil)</option>
				<option value="en-US" <?php echo (($data['language'] ?? '') === 'en-US') ? 'selected' : ''; ?>>English (US)</option>
				<option value="es-ES" <?php echo (($data['language'] ?? '') === 'es-ES') ? 'selected' : ''; ?>>Español</option>
			</select>
		</td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-enabled'] ?? 'Enabled'; ?>
		</td>
		<td class="vtable" align="left">
			<select class="formfld" name="is_active">
				<option value="1" <?php echo (($data['is_enabled'] ?? true) == true) ? 'selected' : ''; ?>><?php echo $text['label-true'] ?? 'True'; ?></option>
				<option value="0" <?php echo (($data['is_enabled'] ?? true) == false) ? 'selected' : ''; ?>><?php echo $text['label-false'] ?? 'False'; ?></option>
			</select>
		</td>
	</tr>

	<tr>
		<td colspan="2"><br><b><?php echo $text['header-processing_mode'] ?? 'Processing Mode'; ?></b><br><br></td>
	</tr>

	<tr>
		<td class="vncellreq" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-mode'] ?? 'Mode'; ?>
		</td>
		<td class="vtable" align="left">
			<select class="formfld" name="processing_mode" id="processing_mode" onchange="toggleRealtimeProvider()">
				<option value="turn_based" <?php echo (($data['processing_mode'] ?? 'turn_based') === 'turn_based') ? 'selected' : ''; ?>>Turn-based (v1)</option>
				<option value="realtime" <?php echo (($data['processing_mode'] ?? '') === 'realtime') ? 'selected' : ''; ?>>Realtime (v2)</option>
				<option value="auto" <?php echo (($data['processing_mode'] ?? '') === 'auto') ? 'selected' : ''; ?>>Auto</option>
			</select>
			<br><?php echo $text['description-processing_mode'] ?? 'Turn-based: traditional IVR. Realtime: natural conversation. Auto: tries realtime first.'; ?>
		</td>
	</tr>

	<tr id="realtime_provider_row" style="display: none;">
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-realtime_provider'] ?? 'Realtime Provider'; ?>
		</td>
		<td class="vtable" align="left">
			<select class="formfld" name="realtime_provider_uuid">
				<option value=""><?php echo $text['option-select'] ?? 'Select...'; ?></option>
				<?php foreach ($realtime_providers as $p) { ?>
					<option value="<?php echo $p['voice_ai_provider_uuid']; ?>" <?php echo (($data['realtime_provider_uuid'] ?? '') === $p['voice_ai_provider_uuid']) ? 'selected' : ''; ?>>
						<?php echo escape($p['provider_name']); ?>
					</option>
				<?php } ?>
			</select>
		</td>
	</tr>

	<tr>
		<td colspan="2"><br><b><?php echo $text['header-prompts'] ?? 'AI Prompts'; ?></b><br><br></td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-personality_prompt'] ?? 'Personality Prompt'; ?>
		</td>
		<td class="vtable" align="left">
			<textarea class="formfld" name="system_prompt" rows="6"><?php echo escape($data['personality_prompt'] ?? ''); ?></textarea>
			<br><?php echo $text['description-personality_prompt'] ?? 'Instructions for the AI personality.'; ?>
		</td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-greeting'] ?? 'Greeting'; ?>
		</td>
		<td class="vtable" align="left">
			<textarea class="formfld" name="greeting_message" rows="2"><?php echo escape($data['greeting_message'] ?? 'Olá! Como posso ajudar?'); ?></textarea>
		</td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-farewell'] ?? 'Farewell'; ?>
		</td>
		<td class="vtable" align="left">
			<textarea class="formfld" name="farewell_message" rows="2"><?php echo escape($data['farewell_message'] ?? 'Foi um prazer ajudar! Até logo!'); ?></textarea>
		</td>
	</tr>

	<tr>
		<td colspan="2"><br><b><?php echo $text['header-providers'] ?? 'AI Providers'; ?></b><br><br></td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-stt_provider'] ?? 'STT Provider'; ?>
		</td>
		<td class="vtable" align="left">
			<select class="formfld" name="stt_provider_uuid">
				<option value=""><?php echo $text['option-default'] ?? 'Default'; ?></option>
				<?php foreach ($stt_providers as $p) { ?>
					<option value="<?php echo $p['voice_ai_provider_uuid']; ?>" <?php echo (($data['stt_provider_uuid'] ?? '') === $p['voice_ai_provider_uuid']) ? 'selected' : ''; ?>>
						<?php echo escape($p['provider_name']); ?>
					</option>
				<?php } ?>
			</select>
		</td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-tts_provider'] ?? 'TTS Provider'; ?>
		</td>
		<td class="vtable" align="left">
			<select class="formfld" name="tts_provider_uuid">
				<option value=""><?php echo $text['option-default'] ?? 'Default'; ?></option>
				<?php foreach ($tts_providers as $p) { ?>
					<option value="<?php echo $p['voice_ai_provider_uuid']; ?>" <?php echo (($data['tts_provider_uuid'] ?? '') === $p['voice_ai_provider_uuid']) ? 'selected' : ''; ?>>
						<?php echo escape($p['provider_name']); ?>
					</option>
				<?php } ?>
			</select>
		</td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-tts_voice'] ?? 'TTS Voice'; ?>
		</td>
		<td class="vtable" align="left">
			<input class="formfld" type="text" name="tts_voice" maxlength="100" value="<?php echo escape($data['tts_voice_id'] ?? ''); ?>">
		</td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-llm_provider'] ?? 'LLM Provider'; ?>
		</td>
		<td class="vtable" align="left">
			<select class="formfld" name="llm_provider_uuid">
				<option value=""><?php echo $text['option-default'] ?? 'Default'; ?></option>
				<?php foreach ($llm_providers as $p) { ?>
					<option value="<?php echo $p['voice_ai_provider_uuid']; ?>" <?php echo (($data['llm_provider_uuid'] ?? '') === $p['voice_ai_provider_uuid']) ? 'selected' : ''; ?>>
						<?php echo escape($p['provider_name']); ?>
					</option>
				<?php } ?>
			</select>
		</td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-embeddings_provider'] ?? 'Embeddings Provider'; ?>
		</td>
		<td class="vtable" align="left">
			<select class="formfld" name="embeddings_provider_uuid">
				<option value=""><?php echo $text['option-default'] ?? 'Default'; ?></option>
				<?php foreach ($embeddings_providers as $p) { ?>
					<option value="<?php echo $p['voice_ai_provider_uuid']; ?>" <?php echo (($data['embeddings_provider_uuid'] ?? '') === $p['voice_ai_provider_uuid']) ? 'selected' : ''; ?>>
						<?php echo escape($p['provider_name']); ?>
					</option>
				<?php } ?>
			</select>
		</td>
	</tr>

	<tr>
		<td colspan="2"><br><b><?php echo $text['header-transfer'] ?? 'Transfer Settings'; ?></b><br><br></td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-transfer_extension'] ?? 'Transfer Extension'; ?>
		</td>
		<td class="vtable" align="left">
			<input class="formfld" type="text" name="transfer_extension" maxlength="20" value="<?php echo escape($data['transfer_extension'] ?? '200'); ?>">
		</td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-max_turns'] ?? 'Max Turns'; ?>
		</td>
		<td class="vtable" align="left">
			<input class="formfld" type="number" name="max_turns" min="1" max="100" value="<?php echo escape($data['max_turns'] ?? 20); ?>">
		</td>
	</tr>

	<tr>
		<td colspan="2"><br><b><?php echo $text['header-integration'] ?? 'Integration'; ?></b><br><br></td>
	</tr>

	<tr>
		<td class="vncell" valign="top" align="left" nowrap="nowrap">
			<?php echo $text['label-webhook_url'] ?? 'Webhook URL'; ?>
		</td>
		<td class="vtable" align="left">
			<input class="formfld" type="url" name="webhook_url" maxlength="500" value="<?php echo escape($data['omniplay_webhook_url'] ?? ''); ?>" placeholder="https://...">
			<br><?php echo $text['description-webhook_url'] ?? 'OmniPlay webhook URL for creating tickets.'; ?>
		</td>
	</tr>

</table>

</form>

<script>
function toggleRealtimeProvider() {
	var mode = document.getElementById('processing_mode').value;
	var row = document.getElementById('realtime_provider_row');
	if (mode === 'realtime' || mode === 'auto') {
		row.style.display = '';
	} else {
		row.style.display = 'none';
	}
}
// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
	toggleRealtimeProvider();
});
</script>

<?php

//include the footer
	require_once "resources/footer.php";

?>
