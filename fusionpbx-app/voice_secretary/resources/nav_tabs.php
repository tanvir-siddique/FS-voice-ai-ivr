<?php
/**
 * Voice Secretary - Navigation Tabs
 * Include this after require_once "resources/header.php";
 * Set $current_page before including.
 */

// Default current page
if (!isset($current_page)) {
	$current_page = 'secretaries';
}

// Get text translations
if (!isset($text)) {
	$language = new text;
	$text = $language->get();
}
?>

<!-- Voice Secretary Tab Navigation -->
<div style="margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 10px;">
	<a href="secretary.php" class="btn <?php echo ($current_page == 'secretaries') ? 'btn-primary' : 'btn-default'; ?>" style="margin-right: 5px;">
		<span class="fas fa-robot fa-fw"></span> <?php echo $text['tab-secretaries'] ?? 'Secretárias'; ?>
	</a>
	<a href="providers.php" class="btn <?php echo ($current_page == 'providers') ? 'btn-primary' : 'btn-default'; ?>" style="margin-right: 5px;">
		<span class="fas fa-cogs fa-fw"></span> <?php echo $text['tab-providers'] ?? 'Provedores IA'; ?>
	</a>
	<a href="documents.php" class="btn <?php echo ($current_page == 'documents') ? 'btn-primary' : 'btn-default'; ?>" style="margin-right: 5px;">
		<span class="fas fa-file-alt fa-fw"></span> <?php echo $text['tab-documents'] ?? 'Documentos'; ?>
	</a>
	<a href="conversations.php" class="btn <?php echo ($current_page == 'conversations') ? 'btn-primary' : 'btn-default'; ?>" style="margin-right: 5px;">
		<span class="fas fa-comments fa-fw"></span> <?php echo $text['tab-conversations'] ?? 'Conversas'; ?>
	</a>
	<a href="settings.php" class="btn <?php echo ($current_page == 'settings') ? 'btn-primary' : 'btn-default'; ?>">
		<span class="fas fa-sliders-h fa-fw"></span> <?php echo $text['tab-settings'] ?? 'Configurações'; ?>
	</a>
</div>
