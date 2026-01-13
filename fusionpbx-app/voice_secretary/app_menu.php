<?php
/**
 * Voice Secretary App Menu
 */

$y = 0;
$apps[$x]['menu'][$y]['title']['en-us'] = "Voice Secretary";
$apps[$x]['menu'][$y]['title']['pt-br'] = "Secretária Virtual";
$apps[$x]['menu'][$y]['uuid'] = "b2c3d4e5-f6a7-8901-bcde-f12345678901";
$apps[$x]['menu'][$y]['parent_uuid'] = "fd29e39c-c936-f5fc-8e2b-611681b266b5"; // Apps menu
$apps[$x]['menu'][$y]['category'] = "internal";
$apps[$x]['menu'][$y]['path'] = "/app/voice_secretary/secretary.php";
$apps[$x]['menu'][$y]['groups'][] = "superadmin";
$apps[$x]['menu'][$y]['groups'][] = "admin";
$apps[$x]['menu'][$y]['groups'][] = "user";

?>
