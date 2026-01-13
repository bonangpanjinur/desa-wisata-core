<?php
/**
 * File Name:   admin-ui-components.php
 * Description: Helper functions to render standardized UI components for the admin dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render a standard card component.
 */
function dw_admin_render_card( $title, $body_content, $link_text = '', $link_url = '' ) {
    ?>
    <div class="dw-card">
        <div class="dw-card-header">
            <h3 class="card-heading"><?php echo esc_html( $title ); ?></h3>
            <?php if ( $link_text && $link_url ) : ?>
                <a href="<?php echo esc_url( $link_url ); ?>" class="dw-btn-link"><?php echo esc_html( $link_text ); ?></a>
            <?php endif; ?>
        </div>
        <div class="dw-card-body">
            <?php echo $body_content; // Content is expected to be pre-rendered HTML ?>
        </div>
    </div>
    <?php
}

/**
 * Render a statistics card.
 */
function dw_admin_render_stat_card( $icon_class, $bg_class, $value, $label ) {
    ?>
    <div class="dw-stat-card">
        <div class="dw-stat-icon-wrapper <?php echo esc_attr( $bg_class ); ?>">
            <span class="dashicons <?php echo esc_attr( $icon_class ); ?>"></span>
        </div>
        <div>
            <p class="dw-stat-value"><?php echo esc_html( $value ); ?></p>
            <p class="dw-stat-label"><?php echo esc_html( $label ); ?></p>
        </div>
    </div>
    <?php
}

/**
 * Render a modern data table.
 */
function dw_admin_render_table( $headers, $rows, $classes = [] ) {
    $table_class = 'dw-modern-table ' . implode( ' ', $classes );
    ?>
    <div class="dw-table-wrapper">
        <table class="<?php echo esc_attr( $table_class ); ?>">
            <thead>
                <tr>
                    <?php foreach ( $headers as $header ) : ?>
                        <th><?php echo esc_html( $header ); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr>
                        <td colspan="<?php echo count( $headers ); ?>" style="text-align: center;">No data found.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <?php foreach ( $row as $cell ) : ?>
                                <td><?php echo $cell; // Cell content can be HTML ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Render a form group.
 */
function dw_admin_render_form_group( $label, $input_html, $help_text = '' ) {
    ?>
    <div class="dw-form-group">
        <label class="dw-label"><?php echo esc_html( $label ); ?></label>
        <?php echo $input_html; ?>
        <?php if ( $help_text ) : ?>
            <p class="dw-help-text"><?php echo esc_html( $help_text ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render an alert/notification.
 */
function dw_admin_render_alert( $message, $type = 'info' ) {
    $type_class = 'status-' . $type;
    ?>
    <div class="dw-badge <?php echo esc_attr( $type_class ); ?>" style="display: block; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
        <?php echo esc_html( $message ); ?>
    </div>
    <?php
}
