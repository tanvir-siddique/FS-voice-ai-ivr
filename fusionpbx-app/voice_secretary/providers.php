<?php
/**
 * Voice Secretary - Providers List Page
 * 
 * Lists all configured AI providers.
 * ⚠️ MULTI-TENANT: Uses domain_uuid from session.
 *
 * @package voice_secretary
 */

// FusionPBX includes
$includes_root = dirname(__DIR__, 2);
require_once $includes_root . "/resources/require.php";
require_once $includes_root . "/resources/check_auth.php";

// Check permission
if (permission_exists('voice_secretary_view')) {
    // Access allowed
} else {
    echo "access denied";
    exit;
}

// Get domain_uuid from session
$domain_uuid = $_SESSION['domain_uuid'] ?? null;
if (!$domain_uuid) {
    echo "Error: domain_uuid not found in session.";
    exit;
}

// Include class
require_once __DIR__ . "/resources/classes/voice_ai_provider.php";

// Get providers
$provider_obj = new voice_ai_provider();
$providers = $provider_obj->get_list($domain_uuid);

// Group by type
$grouped = [
    'stt' => [],
    'tts' => [],
    'llm' => [],
    'embeddings' => [],
    'realtime' => [],
];

if (is_array($providers)) {
    foreach ($providers as $p) {
        $type = $p['provider_type'];
        if (isset($grouped[$type])) {
            $grouped[$type][] = $p;
        }
    }
}

// Include header
$document['title'] = $text['title-providers'] ?? 'AI Providers';
require_once $includes_root . "/resources/header.php";
?>

<div class="action_bar" id="action_bar">
    <div class="heading">
        <b><?php echo $text['title-providers'] ?? 'AI Providers'; ?></b>
    </div>
    <div class="actions">
        <?php if (permission_exists('voice_secretary_add')) { ?>
            <button type="button" onclick="window.location='providers_edit.php'" class="btn btn-default btn-sm">
                <span class="fas fa-plus fa-fw"></span>
                <?php echo $text['button-add'] ?? 'Add'; ?>
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
            <th><?php echo $text['label-priority'] ?? 'Priority'; ?></th>
            <th><?php echo $text['label-default'] ?? 'Default'; ?></th>
            <th><?php echo $text['label-status'] ?? 'Status'; ?></th>
            <th></th>
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
                    <td><?php echo intval($p['priority'] ?? 0); ?></td>
                    <td>
                        <?php if ($p['is_default'] ?? false) { ?>
                            <span class="badge bg-primary"><?php echo $text['label-yes'] ?? 'Yes'; ?></span>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if ($p['is_enabled'] ?? true) { ?>
                            <span class="badge bg-success"><?php echo $text['label-active'] ?? 'Active'; ?></span>
                        <?php } else { ?>
                            <span class="badge bg-secondary"><?php echo $text['label-inactive'] ?? 'Inactive'; ?></span>
                        <?php } ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-default btn-xs" 
                                onclick="testProvider('<?php echo $p['voice_ai_provider_uuid']; ?>')">
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
require_once $includes_root . "/resources/footer.php";
?>
