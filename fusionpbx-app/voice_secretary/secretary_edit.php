<?php
/**
 * Voice Secretary - Edit/Create Page
 * 
 * Create or edit a voice AI secretary.
 * ⚠️ MULTI-TENANT: Uses domain_uuid from session.
 *
 * @package voice_secretary
 */

// FusionPBX includes
$includes_root = dirname(__DIR__, 2);
require_once $includes_root . "/resources/require.php";
require_once $includes_root . "/resources/check_auth.php";

// Check permission
if (permission_exists('voice_secretary_add') || permission_exists('voice_secretary_edit')) {
    // Access allowed
} else {
    echo "access denied";
    exit;
}

// Include class
require_once __DIR__ . "/resources/classes/voice_secretary.php";

// Get domain_uuid from session
$domain_uuid = $_SESSION['domain_uuid'] ?? null;
if (!$domain_uuid) {
    echo "Error: domain_uuid not found in session.";
    exit;
}

// Initialize
$secretary_obj = new voice_secretary();
$action = 'add';
$data = [];

// Check if editing existing
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $action = 'edit';
    $secretary_uuid = $_GET['id'];
    $data = $secretary_obj->get($secretary_uuid, $domain_uuid);
    
    if (!$data) {
        $_SESSION['message'] = $text['message-secretary_not_found'] ?? 'Secretary not found';
        header('Location: secretary.php');
        exit;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Collect form data
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
        'is_active' => isset($_POST['is_active']),
        'webhook_url' => $_POST['webhook_url'] ?? '',
    ];
    
    // Validate
    if (empty($form_data['secretary_name'])) {
        $_SESSION['message'] = $text['message-name_required'] ?? 'Name is required';
    } else {
        try {
            if ($action === 'add') {
                $secretary_obj->create($form_data, $domain_uuid);
                $_SESSION['message'] = $text['message-secretary_created'] ?? 'Secretary created successfully';
            } else {
                $secretary_obj->update($secretary_uuid, $form_data, $domain_uuid);
                $_SESSION['message'] = $text['message-secretary_updated'] ?? 'Secretary updated successfully';
            }
            header('Location: secretary.php');
            exit;
        } catch (Exception $e) {
            $_SESSION['message'] = ($text['message-error'] ?? 'Error') . ': ' . $e->getMessage();
        }
    }
}

// Get providers for dropdowns
$stt_providers = $secretary_obj->get_providers('stt', $domain_uuid);
$tts_providers = $secretary_obj->get_providers('tts', $domain_uuid);
$llm_providers = $secretary_obj->get_providers('llm', $domain_uuid);
$embeddings_providers = $secretary_obj->get_providers('embeddings', $domain_uuid);
$realtime_providers = $secretary_obj->get_providers('realtime', $domain_uuid);

// Include header
$document['title'] = ($action === 'add') 
    ? ($text['title-add_secretary'] ?? 'Add Secretary') 
    : ($text['title-edit_secretary'] ?? 'Edit Secretary');
require_once $includes_root . "/resources/header.php";
?>

<form method="post" enctype="multipart/form-data">
    <div class="action_bar" id="action_bar">
        <div class="heading">
            <b><?php echo ($action === 'add') ? ($text['title-add_secretary'] ?? 'Add Secretary') : ($text['title-edit_secretary'] ?? 'Edit Secretary'); ?></b>
        </div>
        <div class="actions">
            <button type="submit" name="submit" class="btn btn-primary btn-sm">
                <span class="fas fa-save fa-fw"></span>
                <?php echo $text['button-save'] ?? 'Save'; ?>
            </button>
            <button type="button" onclick="window.location='secretary.php'" class="btn btn-default btn-sm">
                <span class="fas fa-times fa-fw"></span>
                <?php echo $text['button-back'] ?? 'Back'; ?>
            </button>
        </div>
        <div style="clear: both;"></div>
    </div>

    <table class="form-table">
        <!-- Basic Info -->
        <tr>
            <th colspan="2"><b><?php echo $text['header-basic_info'] ?? 'Basic Information'; ?></b></th>
        </tr>
        <tr>
            <td class="vncellreq"><?php echo $text['label-secretary_name'] ?? 'Name'; ?></td>
            <td class="vtable">
                <input type="text" name="secretary_name" class="formfld" 
                    value="<?php echo escape($data['secretary_name'] ?? ''); ?>" required>
            </td>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-company_name'] ?? 'Company'; ?></td>
            <td class="vtable">
                <input type="text" name="company_name" class="formfld" 
                    value="<?php echo escape($data['company_name'] ?? ''); ?>">
            </td>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-extension'] ?? 'Extension'; ?></td>
            <td class="vtable">
                <input type="text" name="extension" class="formfld" 
                    value="<?php echo escape($data['extension'] ?? ''); ?>" placeholder="8000">
            </td>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-language'] ?? 'Language'; ?></td>
            <td class="vtable">
                <select name="language" class="formfld">
                    <option value="pt-BR" <?php echo (($data['language'] ?? 'pt-BR') === 'pt-BR') ? 'selected' : ''; ?>>Português (Brasil)</option>
                    <option value="en-US" <?php echo (($data['language'] ?? '') === 'en-US') ? 'selected' : ''; ?>>English (US)</option>
                    <option value="es-ES" <?php echo (($data['language'] ?? '') === 'es-ES') ? 'selected' : ''; ?>>Español</option>
                </select>
            </td>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-status'] ?? 'Status'; ?></td>
            <td class="vtable">
                <input type="checkbox" name="is_active" <?php echo (!isset($data['is_enabled']) || $data['is_enabled']) ? 'checked' : ''; ?>>
                <?php echo $text['label-active'] ?? 'Active'; ?>
            </td>
        </tr>

        <!-- Processing Mode -->
        <tr>
            <th colspan="2"><b><?php echo $text['header-processing_mode'] ?? 'Processing Mode'; ?></b></th>
        </tr>
        <tr>
            <td class="vncellreq"><?php echo $text['label-mode'] ?? 'Mode'; ?></td>
            <td class="vtable">
                <label style="margin-right: 20px;">
                    <input type="radio" name="processing_mode" value="turn_based" 
                        <?php echo (($data['processing_mode'] ?? 'turn_based') === 'turn_based') ? 'checked' : ''; ?>
                        onchange="toggleModeFields()">
                    Turn-based (v1)
                </label>
                <label style="margin-right: 20px;">
                    <input type="radio" name="processing_mode" value="realtime" 
                        <?php echo (($data['processing_mode'] ?? '') === 'realtime') ? 'checked' : ''; ?>
                        onchange="toggleModeFields()">
                    Realtime (v2)
                </label>
                <label>
                    <input type="radio" name="processing_mode" value="auto" 
                        <?php echo (($data['processing_mode'] ?? '') === 'auto') ? 'checked' : ''; ?>
                        onchange="toggleModeFields()">
                    Auto
                </label>
            </td>
        </tr>
        <tr id="realtime_provider_row" style="display: none;">
            <td class="vncell"><?php echo $text['label-realtime_provider'] ?? 'Realtime Provider'; ?></td>
            <td class="vtable">
                <select name="realtime_provider_uuid" class="formfld">
                    <option value=""><?php echo $text['option-select'] ?? 'Select...'; ?></option>
                    <?php if (is_array($realtime_providers)) foreach ($realtime_providers as $p) { ?>
                        <option value="<?php echo $p['voice_ai_provider_uuid']; ?>" 
                            <?php echo (($data['realtime_provider_uuid'] ?? '') === $p['voice_ai_provider_uuid']) ? 'selected' : ''; ?>>
                            <?php echo escape($p['provider_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </td>
        </tr>

        <!-- Prompts -->
        <tr>
            <th colspan="2"><b><?php echo $text['header-prompts'] ?? 'AI Prompts'; ?></b></th>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-system_prompt'] ?? 'Personality Prompt'; ?></td>
            <td class="vtable">
                <textarea name="system_prompt" class="formfld" rows="6"><?php echo escape($data['personality_prompt'] ?? ''); ?></textarea>
            </td>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-greeting'] ?? 'Greeting'; ?></td>
            <td class="vtable">
                <textarea name="greeting_message" class="formfld" rows="2"><?php echo escape($data['greeting_message'] ?? 'Olá! Como posso ajudar?'); ?></textarea>
            </td>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-farewell'] ?? 'Farewell'; ?></td>
            <td class="vtable">
                <textarea name="farewell_message" class="formfld" rows="2"><?php echo escape($data['farewell_message'] ?? 'Foi um prazer ajudar! Até logo!'); ?></textarea>
            </td>
        </tr>

        <!-- AI Providers -->
        <tr>
            <th colspan="2"><b><?php echo $text['header-providers'] ?? 'AI Providers'; ?></b></th>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-stt_provider'] ?? 'STT Provider'; ?></td>
            <td class="vtable">
                <select name="stt_provider_uuid" class="formfld">
                    <option value=""><?php echo $text['option-default'] ?? 'Default'; ?></option>
                    <?php if (is_array($stt_providers)) foreach ($stt_providers as $p) { ?>
                        <option value="<?php echo $p['voice_ai_provider_uuid']; ?>" 
                            <?php echo (($data['stt_provider_uuid'] ?? '') === $p['voice_ai_provider_uuid']) ? 'selected' : ''; ?>>
                            <?php echo escape($p['provider_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </td>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-tts_provider'] ?? 'TTS Provider'; ?></td>
            <td class="vtable">
                <select name="tts_provider_uuid" class="formfld">
                    <option value=""><?php echo $text['option-default'] ?? 'Default'; ?></option>
                    <?php if (is_array($tts_providers)) foreach ($tts_providers as $p) { ?>
                        <option value="<?php echo $p['voice_ai_provider_uuid']; ?>" 
                            <?php echo (($data['tts_provider_uuid'] ?? '') === $p['voice_ai_provider_uuid']) ? 'selected' : ''; ?>>
                            <?php echo escape($p['provider_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </td>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-tts_voice'] ?? 'TTS Voice'; ?></td>
            <td class="vtable">
                <input type="text" name="tts_voice" class="formfld" 
                    value="<?php echo escape($data['tts_voice_id'] ?? ''); ?>">
            </td>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-llm_provider'] ?? 'LLM Provider'; ?></td>
            <td class="vtable">
                <select name="llm_provider_uuid" class="formfld">
                    <option value=""><?php echo $text['option-default'] ?? 'Default'; ?></option>
                    <?php if (is_array($llm_providers)) foreach ($llm_providers as $p) { ?>
                        <option value="<?php echo $p['voice_ai_provider_uuid']; ?>" 
                            <?php echo (($data['llm_provider_uuid'] ?? '') === $p['voice_ai_provider_uuid']) ? 'selected' : ''; ?>>
                            <?php echo escape($p['provider_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </td>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-embeddings_provider'] ?? 'Embeddings Provider'; ?></td>
            <td class="vtable">
                <select name="embeddings_provider_uuid" class="formfld">
                    <option value=""><?php echo $text['option-default'] ?? 'Default'; ?></option>
                    <?php if (is_array($embeddings_providers)) foreach ($embeddings_providers as $p) { ?>
                        <option value="<?php echo $p['voice_ai_provider_uuid']; ?>" 
                            <?php echo (($data['embeddings_provider_uuid'] ?? '') === $p['voice_ai_provider_uuid']) ? 'selected' : ''; ?>>
                            <?php echo escape($p['provider_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </td>
        </tr>

        <!-- Transfer Settings -->
        <tr>
            <th colspan="2"><b><?php echo $text['header-transfer'] ?? 'Transfer Settings'; ?></b></th>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-transfer_extension'] ?? 'Transfer Extension'; ?></td>
            <td class="vtable">
                <input type="text" name="transfer_extension" class="formfld" 
                    value="<?php echo escape($data['transfer_extension'] ?? '200'); ?>">
            </td>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-max_turns'] ?? 'Max Turns'; ?></td>
            <td class="vtable">
                <input type="number" name="max_turns" class="formfld" min="1" max="100"
                    value="<?php echo escape($data['max_turns'] ?? 20); ?>">
            </td>
        </tr>

        <!-- Integration -->
        <tr>
            <th colspan="2"><b><?php echo $text['header-integration'] ?? 'Integration'; ?></b></th>
        </tr>
        <tr>
            <td class="vncell"><?php echo $text['label-webhook_url'] ?? 'Webhook URL'; ?></td>
            <td class="vtable">
                <input type="url" name="webhook_url" class="formfld" 
                    value="<?php echo escape($data['omniplay_webhook_url'] ?? ''); ?>" placeholder="https://...">
            </td>
        </tr>
    </table>
</form>

<script>
function toggleModeFields() {
    var mode = document.querySelector('input[name="processing_mode"]:checked');
    if (!mode) return;
    
    var realtimeRow = document.getElementById('realtime_provider_row');
    if (mode.value === 'realtime' || mode.value === 'auto') {
        realtimeRow.style.display = '';
    } else {
        realtimeRow.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleModeFields();
});
</script>

<?php
require_once $includes_root . "/resources/footer.php";
?>
