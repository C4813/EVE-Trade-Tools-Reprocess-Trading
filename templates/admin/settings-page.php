<?php
if (!defined('ABSPATH')) exit;

$ph_active      = class_exists('ETT_ExternalDB') && class_exists('ETT_Crypto');
$db_configured  = $ph_active && ETT_ExternalDB::is_configured();
$sso_configured = !empty(get_option('ett_sso_client_id')) && !empty(get_option('ett_sso_client_secret'));
?>
<div class="ett-settings-grid">

    <!-- EVE SSO -->
    <div class="ett-card">
        <h2>EVE SSO</h2>

        <?php if (!$ph_active): ?>
            <div class="ett-statusline">
                <span class="ett-dot bad"></span>
                <span class="ett-bad">ETT Price Helper is not active.</span>
            </div>
            <p class="description">Install and activate ETT Price Helper to configure EVE SSO.</p>

        <?php elseif ($sso_configured): ?>
            <div class="ett-statusline">
                <span class="ett-dot ok"></span>
                <span class="ett-ok">Client ID and Secret configured</span>
            </div>
            <p class="description" style="margin-top:10px;">
                Ensure your EVE developer app includes these scopes for ETT Reprocess Trading:
            </p>
            <ul style="margin:6px 0 0 20px; list-style:disc;">
                <?php foreach (explode(' ', ETT_RT::SCOPE) as $scope): ?>
                <li><code><?php echo esc_html($scope); ?></code></li>
                <?php endforeach; ?>
            </ul>

        <?php else: ?>
            <div class="ett-statusline">
                <span class="ett-dot bad"></span>
                <span class="ett-bad">Not configured &mdash; set up EVE SSO in the Price Helper tab.</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- External Database -->
    <div class="ett-card">
        <h2>External Price Database</h2>

        <?php if (!$ph_active): ?>
            <div class="ett-statusline">
                <span class="ett-dot bad"></span>
                <span class="ett-bad">ETT Price Helper is not active.</span>
            </div>
            <p class="description">Install and activate ETT Price Helper to configure the external database.</p>

        <?php elseif ($db_configured): ?>
            <div class="ett-statusline">
                <span class="ett-dot" id="ett-rt-db-dot"></span>
                <span id="ett-rt-db-status-text" class="ett-muted">Testing&hellip;</span>
            </div>

        <?php else: ?>
            <div class="ett-statusline">
                <span class="ett-dot bad"></span>
                <span class="ett-bad">Not configured &mdash; set up the database in the Price Helper tab.</span>
            </div>
        <?php endif; ?>
    </div>

</div>
