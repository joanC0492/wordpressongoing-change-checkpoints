<?php
/**
 * View checkpoint page template
 *
 * @package Change_Checkpoints
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}
?>

<div class="wrap ccp-view-checkpoint">
  <div class="ccp-header">
    <h1 class="wp-heading-inline">
      <?php echo esc_html($checkpoint->title ?: sprintf(__('Checkpoint %s', 'change-checkpoints'), wp_date('Y-m-d H:i', strtotime($checkpoint->created_at)))); ?>
    </h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=change-checkpoints')); ?>" class="page-title-action">
      <?php esc_html_e('Back to All Checkpoints', 'change-checkpoints'); ?>
    </a>
  </div>

  <!-- Checkpoint Info -->
  <div class="ccp-checkpoint-info">
    <div class="ccp-info-card">
      <div class="ccp-info-header">
        <span class="ccp-badge ccp-badge-<?php echo esc_attr($checkpoint->status); ?>">
          <?php echo $checkpoint->status === 'open' ? esc_html__('Open', 'change-checkpoints') : esc_html__('Closed', 'change-checkpoints'); ?>
        </span>
        <div class="ccp-timeframe">
          <?php
          $created_time = wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($checkpoint->created_at));
          if ($checkpoint->closed_at) {
            $closed_time = wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($checkpoint->closed_at));
            printf(
              __('Created: %s → Closed: %s', 'change-checkpoints'),
              '<strong>' . esc_html($created_time) . '</strong>',
              '<strong>' . esc_html($closed_time) . '</strong>'
            );
          } else {
            printf(
              __('Created: %s → Now', 'change-checkpoints'),
              '<strong>' . esc_html($created_time) . '</strong>'
            );
          }
          ?>
        </div>
      </div>

      <?php if ($checkpoint->note): ?>
        <div class="ccp-note">
          <h3><?php esc_html_e('Note', 'change-checkpoints'); ?></h3>
          <p><?php echo wp_kses_post($checkpoint->note); ?></p>
        </div>
      <?php endif; ?>

      <div class="ccp-actions">
        <?php if ($is_active): ?>
          <button type="button" class="button button-secondary"
            onclick="ccpCloseCheckpoint(<?php echo intval($checkpoint->id); ?>, '<?php echo esc_url(admin_url('admin.php?page=change-checkpoints-view&checkpoint_id=' . $checkpoint->id)); ?>')">
            <?php esc_html_e('Close Checkpoint', 'change-checkpoints'); ?>
          </button>
        <?php endif; ?>
        <button type="button" class="button button-link-delete"
          onclick="ccpDeleteCheckpoint(<?php echo intval($checkpoint->id); ?>, '<?php echo esc_url(admin_url('admin.php?page=change-checkpoints')); ?>')">
          <?php esc_html_e('Delete', 'change-checkpoints'); ?>
        </button>
      </div>
    </div>
  </div>

  <!-- Changes List -->
  <div class="ccp-changes">
    <h2><?php esc_html_e('Changes', 'change-checkpoints'); ?></h2>

    <?php if (empty($events)): ?>
      <div class="ccp-empty-state">
        <div class="ccp-empty-icon">
          <span class="dashicons dashicons-info"></span>
        </div>
        <h3><?php esc_html_e('No changes recorded', 'change-checkpoints'); ?></h3>
        <p><?php esc_html_e('No changes were recorded during this checkpoint period.', 'change-checkpoints'); ?></p>
      </div>
    <?php else: ?>
      <div class="ccp-events-list">
        <?php foreach ($events as $group_key => $group): ?>
          <div class="ccp-event-group">
            <h3 class="ccp-group-title"><?php echo esc_html($group['label']); ?></h3>
            <div class="ccp-group-events">
              <?php foreach ($group['events'] as $event): ?>
                <div class="ccp-event-item">
                  <div class="ccp-event-time">
                    <?php echo esc_html($event['time']); ?>
                  </div>
                  <div class="ccp-event-content">
                    <div class="ccp-event-main">
                      <span class="ccp-event-type"><?php echo esc_html($event['object_type']); ?></span>
                      <span class="ccp-event-separator">·</span>
                      <span class="ccp-event-action"><?php echo esc_html($event['action']); ?></span>
                      <span class="ccp-event-separator">·</span>
                      <span class="ccp-event-object"><?php echo esc_html($event['object_name']); ?></span>
                      <span class="ccp-event-separator">·</span>
                      <span class="ccp-event-author">
                        <?php printf(__('by %s', 'change-checkpoints'), esc_html($event['author'])); ?>
                      </span>
                    </div>
                    <?php if (!empty($event['details'])): ?>
                      <div class="ccp-event-details">
                        <?php echo esc_html($event['details']); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>