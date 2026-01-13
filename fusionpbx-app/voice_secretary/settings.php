<?php
/*
	FusionPBX
	Version: MPL 1.1

	Voice Secretary - Settings Page
	Configurações globais do Voice AI.
	⚠️ MULTI-TENANT: Usa domain_uuid da sessão.
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('voice_secretary_edit')) {
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

//get current settings
	$database = new database;
	$sql = "SELECT default_setting_name, default_setting_value FROM v_default_settings 
			WHERE domain_uuid = :domain_uuid 
			AND default_setting_category = 'voice_secretary'
			AND default_setting_enabled = true";
	$parameters['domain_uuid'] = $domain_uuid;
	$rows = $database->select($sql, $parameters, 'all') ?: [];
	unset($parameters);

	$settings = [];
	if (is_array($rows)) {
		foreach ($rows as $row) {
			$settings[$row['default_setting_name']] = $row['default_setting_value'];
		}
	}

//process form submission
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
		$new_settings = [
			'service_url' => $_POST['service_url'] ?? 'http://127.0.0.1:8100/api/v1',
			'data_retention_days' => strval(intval($_POST['data_retention_days'] ?? 90)),
			'omniplay_webhook_url' => $_POST['omniplay_webhook_url'] ?? '',
			'omniplay_api_key' => $_POST['omniplay_api_key'] ?? '',
			'max_concurrent_calls' => strval(intval($_POST['max_concurrent_calls'] ?? 10)),
			'default_max_turns' => strval(intval($_POST['default_max_turns'] ?? 20)),
			'recording_enabled' => isset($_POST['recording_enabled']) ? 'true' : 'false',
		];
		
		foreach ($new_settings as $name => $value) {
			//check if exists
			$sql_check = "SELECT count(*) as cnt FROM v_default_settings 
						  WHERE domain_uuid = :domain_uuid 
						  AND default_setting_category = 'voice_secretary' 
						  AND default_setting_name = :name";
			$parameters['domain_uuid'] = $domain_uuid;
			$parameters['name'] = $name;
			$check = $database->select($sql_check, $parameters, 'all');
			unset($parameters);
			
			if ($check && $check[0]['cnt'] > 0) {
				//update
				$sql_upd = "UPDATE v_default_settings SET default_setting_value = :value, default_setting_enabled = true
							WHERE domain_uuid = :domain_uuid 
							AND default_setting_category = 'voice_secretary' 
							AND default_setting_name = :name";
			} else {
				//insert
				$sql_upd = "INSERT INTO v_default_settings 
							(default_setting_uuid, domain_uuid, default_setting_category, default_setting_subcategory, default_setting_name, default_setting_value, default_setting_enabled)
							VALUES (gen_random_uuid(), :domain_uuid, 'voice_secretary', '', :name, :value, true)";
			}
			
			$parameters['domain_uuid'] = $domain_uuid;
			$parameters['name'] = $name;
			$parameters['value'] = $value;
			$database->execute($sql_upd, $parameters);
			unset($parameters);
		}
		
		$_SESSION['message'] = $text['message-settings_saved'] ?? 'Settings saved.';
		header('Location: settings.php');
		exit;
	}

//set the title
	$document['title'] = $text['title-settings'] ?? 'Settings';

//include the header
	require_once "resources/header.php";

//include tab navigation
	$current_page = 'settings';
	require_once "resources/nav_tabs.php";

?>

<form method="post">
	<div class="action_bar" id="action_bar">
		<div class="heading">
			<b><?php echo $text['title-settings'] ?? 'Settings'; ?></b>
		</div>
		<div class="actions">
			<button type="submit" name="submit" class="btn btn-primary">
				<span class="fas fa-save fa-fw"></span>
				<span class="button-label hide-sm-dn"><?php echo $text['button-save'] ?? 'Save'; ?></span>
			</button>
		</div>
		<div style="clear: both;"></div>
	</div>

	<table class="list">
		<!-- Service Configuration -->
		<tr>
			<th colspan="2"><b><?php echo $text['header-service'] ?? 'Voice AI Service'; ?></b></th>
		</tr>
		<tr>
			<td class="vncell" style="width: 200px;"><?php echo $text['label-service_url'] ?? 'Service URL'; ?></td>
			<td class="vtable">
				<input type="url" name="service_url" class="formfld" style="width: 400px;"
					value="<?php echo escape($settings['service_url'] ?? 'http://127.0.0.1:8100/api/v1'); ?>">
				<br><span class="description"><?php echo $text['description-service_url'] ?? 'URL of the Voice AI Service API'; ?></span>
			</td>
		</tr>
		<tr>
			<td class="vncell"><?php echo $text['label-max_concurrent'] ?? 'Max Concurrent Calls'; ?></td>
			<td class="vtable">
				<input type="number" name="max_concurrent_calls" class="formfld" min="1" max="100" style="width: 100px;"
					value="<?php echo intval($settings['max_concurrent_calls'] ?? 10); ?>">
			</td>
		</tr>
		<tr>
			<td class="vncell"><?php echo $text['label-default_max_turns'] ?? 'Default Max Turns'; ?></td>
			<td class="vtable">
				<input type="number" name="default_max_turns" class="formfld" min="1" max="100" style="width: 100px;"
					value="<?php echo intval($settings['default_max_turns'] ?? 20); ?>">
			</td>
		</tr>
		
		<!-- Data Management -->
		<tr>
			<th colspan="2"><b><?php echo $text['header-data'] ?? 'Data Management'; ?></b></th>
		</tr>
		<tr>
			<td class="vncell"><?php echo $text['label-retention_days'] ?? 'Data Retention (days)'; ?></td>
			<td class="vtable">
				<input type="number" name="data_retention_days" class="formfld" min="1" max="365" style="width: 100px;"
					value="<?php echo intval($settings['data_retention_days'] ?? 90); ?>">
				<br><span class="description"><?php echo $text['description-retention'] ?? 'How long to keep conversation history'; ?></span>
			</td>
		</tr>
		<tr>
			<td class="vncell"><?php echo $text['label-recording'] ?? 'Recording'; ?></td>
			<td class="vtable">
				<input type="checkbox" name="recording_enabled" 
					<?php echo (($settings['recording_enabled'] ?? 'false') === 'true') ? 'checked' : ''; ?>>
				<?php echo $text['description-recording'] ?? 'Enable call recording'; ?>
			</td>
		</tr>
		
		<!-- OmniPlay Integration -->
		<tr>
			<th colspan="2"><b><?php echo $text['header-omniplay'] ?? 'OmniPlay Integration'; ?></b></th>
		</tr>
		<tr>
			<td class="vncell"><?php echo $text['label-webhook_url'] ?? 'Webhook URL'; ?></td>
			<td class="vtable">
				<input type="url" name="omniplay_webhook_url" class="formfld" style="width: 400px;"
					value="<?php echo escape($settings['omniplay_webhook_url'] ?? ''); ?>" 
					placeholder="https://omniplay.example.com/webhook/voice-ai">
				<br><span class="description"><?php echo $text['description-omniplay_webhook'] ?? 'OmniPlay webhook URL for creating tickets'; ?></span>
			</td>
		</tr>
		<tr>
			<td class="vncell"><?php echo $text['label-api_key'] ?? 'API Key'; ?></td>
			<td class="vtable">
				<input type="password" name="omniplay_api_key" class="formfld" style="width: 300px;"
					value="<?php echo escape($settings['omniplay_api_key'] ?? ''); ?>">
			</td>
		</tr>
	</table>
</form>

<?php
//include the footer
	require_once "resources/footer.php";
?>
