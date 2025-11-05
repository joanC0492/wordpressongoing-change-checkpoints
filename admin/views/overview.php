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
              <?php if (!empty($event->is_menu_item) && $event->is_menu_item && !empty($event->details['item_type'])): ?>
                <small class="ccp-details" style="display: block; padding-left: 0;">
                  <?php 
                  // Show the type of menu item instead of repeating the title
                  $item_type = $event->details['item_type'];
                  $object_type = !empty($event->details['object_type']) ? $event->details['object_type'] : '';
                  
                  switch ($item_type) {
                    case 'custom':
                      echo esc_html__('Custom Link', 'change-checkpoints');
                      break;
                    case 'post_type':
                      // Show specific post type name
                      if ($object_type === 'page') {
                        echo esc_html__('Page', 'change-checkpoints');
                      } elseif ($object_type === 'post') {
                        echo esc_html__('Post', 'change-checkpoints');
                      } else {
                        // Custom post type - try to get label
                        $post_type_obj = get_post_type_object($object_type);
                        echo $post_type_obj ? esc_html($post_type_obj->labels->singular_name) : esc_html(ucfirst($object_type));
                      }
                      break;
                    case 'taxonomy':
                      // Show specific taxonomy name
                      if ($object_type === 'category') {
                        echo esc_html__('Category', 'change-checkpoints');
                      } elseif ($object_type === 'post_tag') {
                        echo esc_html__('Tag', 'change-checkpoints');
                      } else {
                        // Custom taxonomy - try to get label
                        $taxonomy_obj = get_taxonomy($object_type);
                        echo $taxonomy_obj ? esc_html($taxonomy_obj->labels->singular_name) : esc_html(ucfirst($object_type));
                      }
                      break;
                    default:
                      echo esc_html(ucfirst($item_type));
                  }
                  ?>
                </small>
              <?php endif; ?>
              <?php if (!empty($event->details) && empty($event->is_theme) && empty($event->is_plugin) && empty($event->is_menu)): ?>
                <br><small class="ccp-details">
                  <?php
                  $detail_parts = array();
                  foreach ($event->details as $key => $value) {
                    // Skip file_url and menu details as they're already displayed above
                    if (in_array($key, array('file_url', 'menu_id', 'item_id', 'item_title', 'item_count'))) {
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