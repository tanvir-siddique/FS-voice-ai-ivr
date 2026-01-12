<?php
/**
 * Voice Secretary - Providers List Page
 * 
 * Lists all configured AI providers.
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

// Include classes
require_once "resources/classes/voice_ai_provider.php";

// Validate multi-tenant
require_once "resources/classes/domain_validator.php";
domain_validator::init();

// Get providers
$provider_obj = new voice_ai_provider();
$providers = $provider_obj->list();

// Group by type
$grouped = [
    'stt' => [],
    'tts' => [],
    'llm' => [],
    'embeddings' => [],
];

foreach ($providers as $p) {
    $type = $p['provider_type'];
    if (isset($grouped[$type])) {
        $grouped[$type][] = $p;
    }
}

// Include header
$document['title'] = $text['title-providers'];
require_once "resources/header.php";
?>

<div class="action_bar" id="action_bar">
    <div class="heading">
        <b><?php echo $text['title-providers']; ?></b>
    </div>
    <div class="actions">
        <?php if (permission_exists('voice_secretary_add')) { ?>
            <button type="button" onclick="window.location='providers_edit.php'" class="btn btn-default btn-sm">
                <span class="fas fa-plus-square fa-fw"></span>
                <?php echo $text['button-add']; ?>
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
            <th><?php echo $text['label-provider_name']; ?></th>
            <th><?php echo $text['label-priority']; ?></th>
            <th><?php echo $text['label-default']; ?></th>
            <th><?php echo $text['label-status']; ?></th>
            <th></th>
        </tr>
        <?php if (!empty($type_providers)) { ?>
            <?php foreach ($type_providers as $p) { 
                $display_name = voice_ai_provider::PROVIDERS[$type][$p['provider_name']] ?? $p['provider_name'];
            ?>
                <tr class="list-row">
                    <td>
                        <?php if (permission_exists('voice_secretary_edit')) { ?>
                            <a href="providers_edit.php?id=<?php echo urlencode($p['provider_uuid']); ?>">
                                <?php echo escape($display_name); ?>
                            </a>
                        <?php } else { ?>
                            <?php echo escape($display_name); ?>
                        <?php } ?>
                    </td>
                    <td><?php echo intval($p['priority']); ?></td>
                    <td>
                        <?php if ($p['is_default']) { ?>
                            <span class="badge badge-primary"><?php echo $text['label-yes']; ?></span>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if ($p['is_active']) { ?>
                            <span class="badge badge-success"><?php echo $text['label-active']; ?></span>
                        <?php } else { ?>
                            <span class="badge badge-secondary"><?php echo $text['label-inactive']; ?></span>
                        <?php } ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-default btn-xs" 
                                onclick="testProvider('<?php echo $p['provider_uuid']; ?>')">
                            <span class="fas fa-plug fa-fw"></span>
                            <?php echo $text['button-test']; ?>
                        </button>
                    </td>
                </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="5" class="no_data_found">
                    <?php echo $text['message-no_providers']; ?>
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
                alert('<?php echo $text['message-provider_ok']; ?>');
            } else {
                alert('<?php echo $text['message-provider_failed']; ?>: ' + data.message);
            }
        })
        .catch(error => {
            alert('<?php echo $text['message-test_error']; ?>');
        });
}
</script>

<?php
// Include footer
require_once "resources/footer.php";
?>
