<?php
/*
	FusionPBX
	Version: MPL 1.1

	Voice Secretary - Secretária Virtual com IA
	Listagem de provedores de IA configurados.
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

//include class
	require_once "resources/classes/voice_ai_provider.php";

//get providers
	$provider_obj = new voice_ai_provider;
	$providers = $provider_obj->get_list($domain_uuid) ?: [];

//group by type
	$grouped = [
		'stt' => [],
		'tts' => [],
		'llm' => [],
		'embeddings' => [],
		'realtime' => [],
	];

	foreach ($providers as $p) {
		$type = $p['provider_type'] ?? 'unknown';
		if (isset($grouped[$type])) {
			$grouped[$type][] = $p;
		}
	}

//set the title
	$document['title'] = $text['title-voice_ai_providers'] ?? 'AI Providers';

//include the header
	require_once "resources/header.php";

//include tab navigation
	$current_page = 'providers';
	require_once "resources/nav_tabs.php";

?>

<div class="action_bar" id="action_bar">
	<div class="heading">
		<b><?php echo $text['title-voice_ai_providers'] ?? 'AI Providers'; ?></b>
	</div>
	<div class="actions">
		<?php if (permission_exists('voice_secretary_add')) { ?>
			<button type="button" alt="<?php echo $text['button-add'] ?? 'Add'; ?>" onclick="window.location='providers_edit.php'" class="btn btn-default">
				<span class="fas fa-plus fa-fw"></span>
				<span class="button-label hide-sm-dn"><?php echo $text['button-add'] ?? 'Add'; ?></span>
			</button>
		<?php } ?>
	</div>
	<div style="clear: both;"></div>
</div>

<?php
$type_labels = [
	'stt' => ['Speech-to-Text (STT)', 'fas fa-microphone'],
	'tts' => ['Text-to-Speech (TTS)', 'fas fa-volume-up'],
	'llm' => ['Large Language Models (LLM)', 'fas fa-brain'],
	'embeddings' => ['Embeddings', 'fas fa-vector-square'],
	'realtime' => ['Realtime Providers', 'fas fa-bolt'],
];

foreach ($grouped as $type => $type_providers) {
	$label = $type_labels[$type][0] ?? strtoupper($type);
	$icon = $type_labels[$type][1] ?? 'fas fa-cog';
?>

<div style="margin-bottom: 30px;">
	<h4 style="margin-bottom: 15px; border-bottom: 2px solid #ddd; padding-bottom: 10px;">
		<i class="<?php echo $icon; ?>"></i> <?php echo $label; ?>
	</h4>
	
	<table class="list">
		<tr class="list-header">
			<th><?php echo $text['label-provider_name'] ?? 'Provider'; ?></th>
			<th class="center"><?php echo $text['label-priority'] ?? 'Priority'; ?></th>
			<th class="center"><?php echo $text['label-default'] ?? 'Default'; ?></th>
			<th class="center"><?php echo $text['label-status'] ?? 'Status'; ?></th>
			<th class="right"><?php echo $text['label-actions'] ?? 'Actions'; ?></th>
		</tr>
		<?php if (!empty($type_providers)) { ?>
			<?php foreach ($type_providers as $p) { ?>
				<tr class="list-row">
					<td>
						<?php if (permission_exists('voice_secretary_edit')) { ?>
							<a href="providers_edit.php?id=<?php echo urlencode($p['voice_ai_provider_uuid']); ?>">
								<?php echo escape($p['provider_name']); ?>
							</a>
						<?php } else { ?>
							<?php echo escape($p['provider_name']); ?>
						<?php } ?>
					</td>
					<td class="center"><?php echo intval($p['priority'] ?? 0); ?></td>
					<td class="center">
						<?php if ($p['is_default'] ?? false) { ?>
							<span class="badge badge-primary"><?php echo $text['label-yes'] ?? 'Yes'; ?></span>
						<?php } else { ?>
							<span class="badge badge-secondary">-</span>
						<?php } ?>
					</td>
					<td class="center">
						<?php if ($p['is_enabled'] ?? true) { ?>
							<span class="badge badge-success"><?php echo $text['label-enabled'] ?? 'Enabled'; ?></span>
						<?php } else { ?>
							<span class="badge badge-secondary"><?php echo $text['label-disabled'] ?? 'Disabled'; ?></span>
						<?php } ?>
					</td>
					<td class="right">
						<button type="button" class="btn btn-default btn-xs" onclick="testProvider('<?php echo $p['voice_ai_provider_uuid']; ?>')">
							<span class="fas fa-plug fa-fw"></span>
							<?php echo $text['button-test'] ?? 'Test'; ?>
						</button>
					</td>
				</tr>
			<?php } ?>
		<?php } else { ?>
			<tr>
				<td colspan="5" class="no-results-found">
					<?php echo $text['message-no_providers'] ?? 'No providers configured for this type.'; ?>
				</td>
			</tr>
		<?php } ?>
	</table>
</div>

<?php } ?>

<script>
function testProvider(uuid) {
	fetch('providers_test.php?id=' + uuid)
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				alert('<?php echo $text['message-provider_ok'] ?? 'Provider is working!'; ?>');
			} else {
				alert('<?php echo $text['message-provider_failed'] ?? 'Provider test failed'; ?>: ' + (data.message || 'Unknown error'));
			}
		})
		.catch(error => {
			alert('<?php echo $text['message-test_error'] ?? 'Test error'; ?>');
		});
}
</script>

<?php

//include the footer
	require_once "resources/footer.php";

?>
