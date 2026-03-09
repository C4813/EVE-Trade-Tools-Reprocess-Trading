<?php
if (!defined('ABSPATH')) exit;
/** @var int $user_id */
/** @var array $characters */
/** @var int $count */
?>

<div class="ett-characters">
    <h3>Authenticated Characters (<?php echo esc_html((string) $count); ?>)</h3>

    <?php
    // Pre-pass: check for any expired tokens before rendering the character list.
    $has_expired = false;
    if (!empty($characters)) {
        foreach ($characters as $char_id => $data) {
            if (!ETT_RT_OAuth::get_valid_access_token($user_id, (string) $char_id)) {
                $has_expired = true;
                break;
            }
        }
    }
    ?>

    <?php if ($has_expired) : ?>
        <p class="ett-token-warning">&#9888; Token expired &mdash; check characters below and reconnect.</p>
    <?php endif; ?>

    <div class="ett-connect-wrap">
        <?php echo ETT_RT_OAuth::connect_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>

    <?php if (empty($characters)) : ?>
        <p>No authenticated characters yet. Use &quot;Connect with EVE Online&quot; to add one.</p>
    <?php else : ?>
        <?php foreach ($characters as $char_id => $data) : ?>
            <?php
            $char_id = (string) $char_id;
            $disconnect_url = admin_url('admin-post.php?action=ett_rt_disconnect_char&char_id=' . rawurlencode($char_id));

            $token = ETT_RT_OAuth::get_valid_access_token($user_id, $char_id);

            $body_html = $token
                ? ETT_RT_Render::render_profile($char_id)
                : '<p>Token expired. Please reconnect.</p>';

            $name = (is_array($data) && !empty($data['name'])) ? (string) $data['name'] : ('Character ' . $char_id);
            ?>

            <div class="ett-character">
                <div class="ett-character-header">
                    <strong><?php echo esc_html($name); ?></strong>
                    <a href="<?php echo esc_url($disconnect_url); ?>" class="ett-disconnect">Disconnect</a>
                </div>

                <div class="ett-character-body">
                    <?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
