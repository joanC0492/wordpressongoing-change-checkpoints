<?php
/**
 * Overview page template for change tracking
 *
 * @package Change_Checkpoints
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}
?>

<div class="wrap ccp-overview">
  <h1 class="wp-heading-inline"><?php echo esc_html(get_admin_page_title()); ?></h1>

  <!-- Clear All Button -->
  <div class="ccp-toolbar">
    <button type="button" class="button button-secondary" onclick="ccpClearAll()">
      <?php esc_html_e('Clear All', 'change-checkpoints'); ?>
    </button>
  </div>

  <!-- Changes Section -->
  <h2><?php esc_html_e('WordPress', 'change-checkpoints'); ?></h2>

  <div class="ccp-list-controls">
    <!-- Search Form -->
    <form method="get" class="ccp-search-form">
      <input type="hidden" name="page" value="change-checkpoints" />
      <p class="search-box">
        <label class="screen-reader-text"
          for="event-search-input"><?php esc_html_e('Search changes:', 'change-checkpoints'); ?></label>
        <input type="search" id="event-search-input" name="s" value="<?php echo esc_attr($search); ?>"
          placeholder="<?php esc_attr_e('Search by content name or date...', 'change-checkpoints'); ?>" />
        <input type="submit" id="search-submit" class="button"
          value="<?php esc_attr_e('Search', 'change-checkpoints'); ?>" />
      </p>
    </form>
  </div>

  <!-- Events List -->
  <?php if (empty($events)): ?>
    <div class="ccp-empty-state">
      <div class="ccp-empty-icon">
        <span class="dashicons dashicons-backup"></span>
      </div>
      <?php if ($search): ?>
        <h3><?php esc_html_e('No changes found', 'change-checkpoints'); ?></h3>
        <p><?php esc_html_e('No changes found matching your search.', 'change-checkpoints'); ?></p>
      <?php else: ?>
        <h3><?php esc_html_e('No changes recorded yet', 'change-checkpoints'); ?></h3>
        <p>
          <?php esc_html_e('Start making changes to your WordPress content to see them tracked here.', 'change-checkpoints'); ?>
        </p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <!-- Pagination Top -->
    <?php if ($total_pages > 1): ?>
      <div class="tablenav top">
        <div class="tablenav-pages">
          <span class="displaying-num">
            <?php printf(
              _n('%d change', '%d changes', $total_events, 'change-checkpoints'),
              number_format_i18n($total_events)
            ); ?>
          </span>
          <?php
          echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total' => $total_pages,
            'current' => $current_page
          ));
          ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- Events Table -->
    <table class="wp-list-table widefat fixed striped">
      <thead>
        <tr>
          <th scope="col" class="manage-column column-time"><?php esc_html_e('Time', 'change-checkpoints'); ?></th>
          <th scope="col" class="manage-column column-type"><?php esc_html_e('Type', 'change-checkpoints'); ?></th>
          <th scope="col" class="manage-column column-action"><?php esc_html_e('Action', 'change-checkpoints'); ?></th>
          <th scope="col" class="manage-column column-content"><?php esc_html_e('Content', 'change-checkpoints'); ?></th>
          <th scope="col" class="manage-column column-author"><?php esc_html_e('Author', 'change-checkpoints'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($events as $event): ?>
          <tr>
            <td class="column-time">
              <span class="ccp-time"
                title="<?php echo esc_attr(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($event->timestamp))); ?>">
                <?php echo esc_html(wp_date('H:i', strtotime($event->timestamp))); ?>
              </span>
            </td>
            <td class="column-type">
              <span class="ccp-object-type"><?php echo esc_html($event->object_type); ?></span>
              <?php if (!empty($event->is_media) && $event->is_media): ?>
                <small class="ccp-details" style="display: block; padding-left: 6px;">
                  <?php esc_html_e('Media', 'change-checkpoints'); ?>
                </small>
              <?php endif; ?>
            </td>
            <td class="column-action">
              <span class="ccp-action ccp-action-<?php echo esc_attr($event->action); ?>">
                <?php echo esc_html($event->action_text); ?>
              </span>
            </td>
            <td class="column-content">
              <strong><?php echo esc_html($event->object_name); ?></strong>
              <?php if (!empty($event->is_media) && $event->is_media && !empty($event->details['file_url'])): ?>
                <small class="ccp-details" style="display: block; padding-left: 0;">
                  <?php echo esc_html($event->details['file_url']); ?>
                </small>
              <?php endif; ?>
              <?php if (!empty($event->details)): ?>
                <br><small class="ccp-details">
                  <?php
                  $detail_parts = array();
                  foreach ($event->details as $key => $value) {
                    // Skip file_url as it's already displayed above
                    if ($key === 'file_url') {
                      continue;
                    }
                    if ($key === 'status_from' && isset($event->details['status_to'])) {
                      $detail_parts[] = sprintf(__('status: %s → %s', 'change-checkpoints'), $value, $event->details['status_to']);
                    } elseif ($key === 'thumbnail_changed') {
                      $detail_parts[] = __('thumbnail changed', 'change-checkpoints');
                    } elseif ($key === 'template_changed') {
                      $detail_parts[] = __('template changed', 'change-checkpoints');
                    } elseif ($key === 'parent_changed') {
                      $detail_parts[] = __('parent changed', 'change-checkpoints');
                    }
                  }
                  if (!empty($detail_parts)) {
                    echo esc_html(implode(', ', $detail_parts));
                  }
                  ?>
                </small>
              <?php endif; ?>
            </td>
            <td class="column-author">
              <?php echo esc_html($event->author_name); ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Pagination Bottom -->
    <?php if ($total_pages > 1): ?>
      <div class="tablenav bottom">
        <div class="tablenav-pages">
          <span class="displaying-num">
            <?php printf(
              _n('%d change', '%d changes', $total_events, 'change-checkpoints'),
              number_format_i18n($total_events)
            ); ?>
          </span>
          <?php
          echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total' => $total_pages,
            'current' => $current_page
          ));
          ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>