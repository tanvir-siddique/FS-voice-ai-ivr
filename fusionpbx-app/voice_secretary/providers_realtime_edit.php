<?php
/*
	FusionPBX
	Version: MPL 1.1

	Voice Secretary - Realtime Provider Edit
	Configure realtime AI providers (OpenAI, ElevenLabs, Gemini, Custom).
	⚠️ MULTI-TENANT: Uses domain_uuid from session.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";

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

//get domain_uuid from session
	$domain_uuid = $_SESSION['domain_uuid'] ?? null;
	if (!$domain_uuid) {
		echo "access denied";
		exit;
	}

//include classes
	require_once "resources/classes/voice_ai_provider.php";

//initialize
	$provider_obj = new voice_ai_provider();
	$action = 'add';
	$data = [];

//check if editing existing
	if (isset($_GET['id']) && is_uuid($_GET['id'])) {
		$action = 'edit';
		$provider_uuid = $_GET['id'];
		$data = $provider_obj->get($provider_uuid, $domain_uuid);
		
		if (!$data) {
			message::add($text['message-invalid_id'] ?? 'Invalid ID', 'negative');
			header('Location: providers.php');
			exit;
		}
	}

//realtime provider options
	$realtime_providers = [
		'openai' => [
			'name' => 'OpenAI Realtime API',
			'description' => 'GPT Realtime with voice (GA)',
			'fields' => ['api_key', 'model', 'voice'],
			'voices' => ['alloy', 'ash', 'ballad', 'coral', 'echo', 'sage', 'shimmer', 'verse', 'marin', 'cedar'],
			'models' => [
				// GA (General Availability) - Recommended
				'gpt-realtime',
				'gpt-realtime-mini',
				// Preview with Snapshots
				'gpt-4o-realtime-preview',
				'gpt-4o-realtime-preview-2025-06-03',
				'gpt-4o-realtime-preview-2024-12-17',
				'gpt-4o-realtime-preview-2024-10-01',
				// Mini Preview
				'gpt-4o-mini-realtime-preview',
				'gpt-4o-mini-realtime-preview-2024-12-17',
			],
		],
		'elevenlabs' => [
			'name' => 'ElevenLabs Conversational AI',
			'description' => 'Premium voices with conversational AI',
			'fields' => ['api_key', 'agent_id', 'voice_id'],
		],
		'gemini' => [
			'name' => 'Google Gemini 2.0 Flash',
			'description' => 'Multimodal AI with audio',
			'fields' => ['api_key', 'model', 'voice'],
			'voices' => ['Aoede', 'Charon', 'Fenrir', 'Kore', 'Puck'],
			'models' => ['gemini-2.0-flash-exp'],
		],
		'custom' => [
			'name' => 'Custom Pipeline',
			'description' => 'Deepgram + Groq + Piper (low-cost)',
			'fields' => ['deepgram_key', 'groq_key', 'stt_provider', 'llm_provider', 'tts_provider'],
		],
	];

//process form submission
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && count($_POST) > 0) {
		//validate token
		$token = new token;
		if (!$token->validate($_SERVER['PHP_SELF'])) {
			message::add($text['message-invalid_token'],'negative');
			header('Location: providers.php');
			exit;
		}

		$provider_name = $_POST['provider_name'] ?? '';
		
		//build config based on provider
		$config = [];
		if (isset($realtime_providers[$provider_name])) {
			foreach ($realtime_providers[$provider_name]['fields'] as $field) {
				if (isset($_POST[$field])) {
					$config[$field] = $_POST[$field];
				}
			}
		}
		
		$form_data = [
			'provider_type' => 'realtime',
			'provider_name' => $provider_name,
			'config' => $config,
			'is_default' => isset($_POST['is_default']),
			'is_enabled' => isset($_POST['is_enabled']),
		];
		
		//validate
		if (empty($provider_name)) {
			message::add($text['message-required'].' '.($text['label-provider'] ?? 'Provider'), 'negative');
		} else {
			try {
				if ($action === 'add') {
					$provider_obj->create($form_data, $domain_uuid);
					message::add($text['message-add']);
				} else {
					$provider_obj->update($provider_uuid, $form_data, $domain_uuid);
					message::add($text['message-update']);
				}
				header('Location: providers.php');
				exit;
			} catch (Exception $e) {
				message::add($e->getMessage(), 'negative');
			}
		}
	}

//parse existing config
	$config = [];
	if (!empty($data['config'])) {
		$config = is_array($data['config']) ? $data['config'] : (json_decode($data['config'], true) ?: []);
	}

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	$document['title'] = ($action === 'add') 
		? ($text['title-add_realtime_provider'] ?? 'Add Realtime Provider')
		: ($text['title-edit_realtime_provider'] ?? 'Edit Realtime Provider');
	require_once "resources/header.php";

//show the content
	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'><b>".$document['title']."</b></div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'providers.php']);
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','style'=>'margin-left: 15px;']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	echo ($text['description-realtime_provider'] ?? 'Configure a realtime AI provider for natural voice conversations.')."\n";
	echo "<br /><br />\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

	//Provider Selection
	echo "<tr>\n";
	echo "	<td colspan='2' style='padding: 12px 10px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;'>\n";
	echo "		<b>".($text['header-realtime_provider'] ?? 'Realtime Provider')."</b>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>".($text['label-provider'] ?? 'Provider')."</td>\n";
	echo "	<td width='70%' class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='provider_name' id='provider_name' onchange='showProviderFields(this.value)' required>\n";
	echo "			<option value=''>".($text['option-select'] ?? '-- Select --')."</option>\n";
	foreach ($realtime_providers as $key => $provider) {
		$selected = (($data['provider_name'] ?? '') === $key) ? 'selected' : '';
		echo "			<option value='".escape($key)."' ".$selected.">".escape($provider['name'])." - ".escape($provider['description'])."</option>\n";
	}
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".($text['label-enabled'] ?? 'Enabled')."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	$is_enabled = (!isset($data['is_enabled']) || $data['is_enabled']);
	echo "		<input type='checkbox' name='is_enabled' id='is_enabled' ".($is_enabled ? 'checked' : '').">\n";
	echo "		<label for='is_enabled'>".($text['description-enabled'] ?? 'Enable this provider.')."</label>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>".($text['label-default'] ?? 'Default')."</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	$is_default = !empty($data['is_default']);
	echo "		<input type='checkbox' name='is_default' id='is_default' ".($is_default ? 'checked' : '').">\n";
	echo "		<label for='is_default'>".($text['description-default'] ?? 'Set as default realtime provider.')."</label>\n";
	echo "	</td>\n";
	echo "</tr>\n";

	echo "</table>\n";

	//OpenAI Fields
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0' id='fields_openai' class='provider_fields' style='display: none;'>\n";
	echo "<tr>\n";
	echo "	<td colspan='2' style='padding: 12px 10px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;'>\n";
	echo "		<b>OpenAI Configuration</b>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>API Key</td>\n";
	echo "	<td width='70%' class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='password' name='api_key' value='".escape($config['api_key'] ?? '')."' placeholder='sk-...' autocomplete='new-password'>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>Model</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	$current_model = $config['model'] ?? 'gpt-realtime';
	echo "		<select class='formfld' name='model' id='openai_model' onchange='updateModelInfo()' style='width: 350px;'>\n";
	// GA Models (General Availability) - Recommended
	echo "			<optgroup label='🚀 GA (Recomendados)'>\n";
	echo "				<option value='gpt-realtime' ".(($current_model === 'gpt-realtime') ? 'selected' : '').">gpt-realtime (32k ctx, melhor qualidade)</option>\n";
	echo "				<option value='gpt-realtime-mini' ".(($current_model === 'gpt-realtime-mini') ? 'selected' : '').">gpt-realtime-mini (32k ctx, menor custo)</option>\n";
	echo "			</optgroup>\n";
	// Preview Models with Snapshots
	echo "			<optgroup label='🧪 Preview (com snapshots)'>\n";
	echo "				<option value='gpt-4o-realtime-preview' ".(($current_model === 'gpt-4o-realtime-preview') ? 'selected' : '').">gpt-4o-realtime-preview (alias latest)</option>\n";
	echo "				<option value='gpt-4o-realtime-preview-2025-06-03' ".(($current_model === 'gpt-4o-realtime-preview-2025-06-03') ? 'selected' : '').">gpt-4o-realtime-preview-2025-06-03</option>\n";
	echo "				<option value='gpt-4o-realtime-preview-2024-12-17' ".(($current_model === 'gpt-4o-realtime-preview-2024-12-17') ? 'selected' : '').">gpt-4o-realtime-preview-2024-12-17</option>\n";
	echo "				<option value='gpt-4o-realtime-preview-2024-10-01' ".(($current_model === 'gpt-4o-realtime-preview-2024-10-01') ? 'selected' : '').">gpt-4o-realtime-preview-2024-10-01</option>\n";
	echo "			</optgroup>\n";
	// Mini Preview Models
	echo "			<optgroup label='🧪 Mini Preview (menor custo)'>\n";
	echo "				<option value='gpt-4o-mini-realtime-preview' ".(($current_model === 'gpt-4o-mini-realtime-preview') ? 'selected' : '').">gpt-4o-mini-realtime-preview (16k ctx)</option>\n";
	echo "				<option value='gpt-4o-mini-realtime-preview-2024-12-17' ".(($current_model === 'gpt-4o-mini-realtime-preview-2024-12-17') ? 'selected' : '').">gpt-4o-mini-realtime-preview-2024-12-17</option>\n";
	echo "			</optgroup>\n";
	echo "		</select>\n";
	echo "		<div id='model_info' style='margin-top: 8px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 0.9em;'></div>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>Voice</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='voice'>\n";
	foreach (['alloy', 'ash', 'ballad', 'coral', 'echo', 'sage', 'shimmer', 'verse', 'marin', 'cedar'] as $v) {
		$selected = (($config['voice'] ?? '') === $v) ? 'selected' : '';
		echo "			<option value='".escape($v)."' ".$selected.">".ucfirst($v)."</option>\n";
	}
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	//ElevenLabs Fields
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0' id='fields_elevenlabs' class='provider_fields' style='display: none;'>\n";
	echo "<tr>\n";
	echo "	<td colspan='2' style='padding: 12px 10px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;'>\n";
	echo "		<b>ElevenLabs Configuration</b>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>API Key</td>\n";
	echo "	<td width='70%' class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='password' name='api_key' value='".escape($config['api_key'] ?? '')."' autocomplete='new-password'>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>Agent ID</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='agent_id' value='".escape($config['agent_id'] ?? '')."' placeholder='agent_...'>\n";
	echo "		<br />Create an agent at <a href='https://elevenlabs.io/app/conversational-ai' target='_blank'>ElevenLabs Console</a>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>Voice ID</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='text' name='voice_id' value='".escape($config['voice_id'] ?? '')."' placeholder='Optional - uses agent default'>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	//Gemini Fields
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0' id='fields_gemini' class='provider_fields' style='display: none;'>\n";
	echo "<tr>\n";
	echo "	<td colspan='2' style='padding: 12px 10px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;'>\n";
	echo "		<b>Google Gemini Configuration</b>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td width='30%' class='vncellreq' valign='top' align='left' nowrap='nowrap'>API Key</td>\n";
	echo "	<td width='70%' class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='password' name='api_key' value='".escape($config['api_key'] ?? '')."' autocomplete='new-password'>\n";
	echo "		<br />Get key from <a href='https://aistudio.google.com/apikey' target='_blank'>Google AI Studio</a>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>Model</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='model'>\n";
	echo "			<option value='gemini-2.0-flash-exp'>gemini-2.0-flash-exp</option>\n";
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>Voice</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='voice'>\n";
	foreach (['Aoede', 'Charon', 'Fenrir', 'Kore', 'Puck'] as $v) {
		$selected = (($config['voice'] ?? '') === $v) ? 'selected' : '';
		echo "			<option value='".escape($v)."' ".$selected.">".$v."</option>\n";
	}
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	//Custom Pipeline Fields
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0' id='fields_custom' class='provider_fields' style='display: none;'>\n";
	echo "<tr>\n";
	echo "	<td colspan='2' style='padding: 12px 10px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;'>\n";
	echo "		<b>Custom Pipeline Configuration</b>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td width='30%' class='vncell' valign='top' align='left' nowrap='nowrap'>STT Provider</td>\n";
	echo "	<td width='70%' class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='stt_provider'>\n";
	echo "			<option value='deepgram' ".((($config['stt_provider'] ?? '') === 'deepgram') ? 'selected' : '').">Deepgram Nova</option>\n";
	echo "			<option value='whisper' ".((($config['stt_provider'] ?? '') === 'whisper') ? 'selected' : '').">Whisper Local</option>\n";
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>Deepgram API Key</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='password' name='deepgram_key' value='".escape($config['deepgram_key'] ?? '')."' autocomplete='new-password'>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>LLM Provider</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='llm_provider'>\n";
	echo "			<option value='groq' ".((($config['llm_provider'] ?? '') === 'groq') ? 'selected' : '').">Groq (Llama)</option>\n";
	echo "			<option value='ollama' ".((($config['llm_provider'] ?? '') === 'ollama') ? 'selected' : '').">Ollama Local</option>\n";
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>Groq API Key</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<input class='formfld' type='password' name='groq_key' value='".escape($config['groq_key'] ?? '')."' autocomplete='new-password'>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "	<td class='vncell' valign='top' align='left' nowrap='nowrap'>TTS Provider</td>\n";
	echo "	<td class='vtable' align='left'>\n";
	echo "		<select class='formfld' name='tts_provider'>\n";
	echo "			<option value='piper' ".((($config['tts_provider'] ?? '') === 'piper') ? 'selected' : '').">Piper Local</option>\n";
	echo "			<option value='coqui' ".((($config['tts_provider'] ?? '') === 'coqui') ? 'selected' : '').">Coqui Local</option>\n";
	echo "		</select>\n";
	echo "	</td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	echo "<br />\n";

	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>\n";

?>

<script>
function showProviderFields(provider) {
	//hide all provider fields
	document.querySelectorAll('.provider_fields').forEach(function(el) {
		el.style.display = 'none';
	});
	
	//show selected provider fields
	if (provider) {
		var fields = document.getElementById('fields_' + provider);
		if (fields) {
			fields.style.display = '';
		}
		
		// Update model info if OpenAI
		if (provider === 'openai') {
			updateModelInfo();
		}
	}
}

function updateModelInfo() {
	var model = document.getElementById('openai_model')?.value || '';
	var infoDiv = document.getElementById('model_info');
	if (!infoDiv) return;
	
	// Model information from OpenAI docs (Context7)
	var models = {
		// GA Models
		'gpt-realtime': {
			type: 'GA',
			context: '32,000 tokens',
			maxOutput: '4,096 tokens',
			textInput: '$5.00/1M',
			textOutput: '$20.00/1M',
			audioInput: '$40.00/1M',
			audioOutput: '$80.00/1M',
			badge: '🚀',
			note: 'Modelo GA principal - Recomendado para produção. Suporta WebRTC, WebSocket e SIP.',
		},
		'gpt-realtime-mini': {
			type: 'GA',
			context: '32,000 tokens',
			maxOutput: '4,096 tokens',
			textInput: '$0.60/1M',
			textOutput: '$2.40/1M',
			audioInput: '$10.00/1M',
			audioOutput: '$20.00/1M',
			badge: '💰',
			note: 'Versão econômica do GA. Ideal para alto volume com custo reduzido.',
		},
		// Preview Models
		'gpt-4o-realtime-preview': {
			type: 'Preview',
			context: '32,000 tokens',
			maxOutput: '4,096 tokens',
			textInput: '$5.00/1M',
			textOutput: '$20.00/1M',
			audioInput: '$40.00/1M',
			audioOutput: '$80.00/1M',
			badge: '🧪',
			note: 'Alias para o snapshot mais recente. Considere migrar para gpt-realtime.',
		},
		'gpt-4o-realtime-preview-2025-06-03': {
			type: 'Snapshot',
			context: '32,000 tokens',
			maxOutput: '4,096 tokens',
			textInput: '$5.00/1M',
			textOutput: '$20.00/1M',
			audioInput: '$40.00/1M',
			audioOutput: '$80.00/1M',
			badge: '📌',
			note: 'Snapshot fixo de junho/2025. Use para consistência entre deploys.',
		},
		'gpt-4o-realtime-preview-2024-12-17': {
			type: 'Snapshot',
			context: '32,000 tokens',
			maxOutput: '4,096 tokens',
			textInput: '$5.00/1M',
			textOutput: '$20.00/1M',
			audioInput: '$40.00/1M',
			audioOutput: '$80.00/1M',
			badge: '📌',
			note: 'Snapshot fixo de dezembro/2024.',
		},
		'gpt-4o-realtime-preview-2024-10-01': {
			type: 'Snapshot',
			context: '32,000 tokens',
			maxOutput: '4,096 tokens',
			textInput: '$5.00/1M',
			textOutput: '$20.00/1M',
			audioInput: '$40.00/1M',
			audioOutput: '$80.00/1M',
			badge: '📌',
			note: 'Snapshot original de outubro/2024. Versão mais antiga.',
		},
		// Mini Preview
		'gpt-4o-mini-realtime-preview': {
			type: 'Mini Preview',
			context: '16,000 tokens',
			maxOutput: '4,096 tokens',
			textInput: '$0.60/1M',
			textOutput: '$2.40/1M',
			audioInput: '$10.00/1M',
			audioOutput: '$20.00/1M',
			badge: '🧪💰',
			note: 'Versão mini preview. Menor contexto mas custo reduzido.',
		},
		'gpt-4o-mini-realtime-preview-2024-12-17': {
			type: 'Mini Snapshot',
			context: '16,000 tokens',
			maxOutput: '4,096 tokens',
			textInput: '$0.60/1M',
			textOutput: '$2.40/1M',
			audioInput: '$10.00/1M',
			audioOutput: '$20.00/1M',
			badge: '📌💰',
			note: 'Snapshot fixo mini de dezembro/2024.',
		},
	};
	
	var info = models[model];
	if (!info) {
		infoDiv.innerHTML = '<span style="color: #666;">Selecione um modelo para ver detalhes.</span>';
		return;
	}
	
	var html = '<div style="display: flex; gap: 20px; flex-wrap: wrap;">';
	html += '<div><b>' + info.badge + ' ' + info.type + '</b></div>';
	html += '<div>📊 Context: <b>' + info.context + '</b></div>';
	html += '<div>📝 Max Output: <b>' + info.maxOutput + '</b></div>';
	html += '</div>';
	html += '<div style="margin-top: 8px; display: flex; gap: 15px; flex-wrap: wrap; font-size: 0.85em;">';
	html += '<div>💬 Text: <span style="color: #2e7d32;">' + info.textInput + '</span> → <span style="color: #c62828;">' + info.textOutput + '</span></div>';
	html += '<div>🎤 Audio: <span style="color: #2e7d32;">' + info.audioInput + '</span> → <span style="color: #c62828;">' + info.audioOutput + '</span></div>';
	html += '</div>';
	html += '<div style="margin-top: 8px; color: #555; font-size: 0.85em;">' + info.note + '</div>';
	
	infoDiv.innerHTML = html;
}

//initialize on page load
document.addEventListener('DOMContentLoaded', function() {
	var selected = document.getElementById('provider_name').value;
	if (selected) {
		showProviderFields(selected);
	}
});
</script>

<?php

//include the footer
	require_once "resources/footer.php";

?>
