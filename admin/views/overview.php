<?php
/**
 * Overview page template
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

  <!-- Current Checkpoint Status -->
  <div class="ccp-current-status">
    <?php if ($active_checkpoint): ?>
      <div class="ccp-status-card ccp-status-active">
        <div class="ccp-status-header">
          <h2>
            <?php echo esc_html($active_checkpoint->title ?: sprintf(__('Checkpoint %s', 'change-checkpoints'), wp_date('Y-m-d H:i', strtotime($active_checkpoint->created_at)))); ?>
          </h2>
          <span class="ccp-badge ccp-badge-active"><?php esc_html_e('Active', 'change-checkpoints'); ?></span>
        </div>
        <div class="ccp-status-meta">
          <p class="ccp-created-at">
            <?php printf(
              __('Created: %s', 'change-checkpoints'),
              '<strong>' . wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($active_checkpoint->created_at)) . '</strong>'
            ); ?>
          </p>
          <?php if ($active_checkpoint->note): ?>
            <p class="ccp-note"><?php echo wp_kses_post($active_checkpoint->note); ?></p>
          <?php endif; ?>
        </div>
        <div class="ccp-status-actions">
          <button type="button" class="button button-secondary"
            onclick="ccpCloseCheckpoint(<?php echo intval($active_checkpoint->id); ?>)">
            <?php esc_html_e('Close Checkpoint', 'change-checkpoints'); ?>
          </button>
          <button type="button" class="button button-primary" onclick="ccpShowCreateModal()">
            <?php esc_html_e('Create New Checkpoint', 'change-checkpoints'); ?>
          </button>
        </div>
      </div>
    <?php else: ?>
      <div class="ccp-status-card ccp-status-inactive">
        <div class="ccp-empty-state">
          <div class="ccp-empty-icon">
            <span class="dashicons dashicons-backup"></span>
          </div>
          <h2><?php esc_html_e('No active checkpoint', 'change-checkpoints'); ?></h2>
          <p><?php esc_html_e('No active checkpoint. Create one to start tracking changes.', 'change-checkpoints'); ?></p>
          <button type="button" class="button button-primary" onclick="ccpShowCreateModal()">
            <?php esc_html_e('Create Checkpoint', 'change-checkpoints'); ?>
          </button>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Checkpoints List -->
  <h2><?php esc_html_e('Checkpoints History', 'change-checkpoints'); ?></h2>

  <div class="ccp-list-controls">
    <!-- Search Form -->
    <form method="get" class="ccp-search-form">
      <input type="hidden" name="page" value="change-checkpoints" />
      <p class="search-box">
        <label class="screen-reader-text"
          for="checkpoint-search-input"><?php esc_html_e('Search checkpoints:', 'change-checkpoints'); ?></label>
        <input type="search" id="checkpoint-search-input" name="s" value="<?php echo esc_attr($search); ?>"
          placeholder="<?php esc_attr_e('Search by date/time...', 'change-checkpoints'); ?>" />
        <input type="submit" id="search-submit" class="button"
          value="<?php esc_attr_e('Search', 'change-checkpoints'); ?>" />
      </p>
    </form>
  </div>

  <!-- Bulk Actions Form -->
  <form method="post" action="" id="ccp-checkpoints-form">
    <?php wp_nonce_field('ccp_bulk_action'); ?>
    <input type="hidden" name="action" value="bulk_delete" />

    <div class="tablenav top">
      <div class="alignleft actions bulkactions">
        <label for="bulk-action-selector-top"
          class="screen-reader-text"><?php esc_html_e('Select bulk action', 'change-checkpoints'); ?></label>
        <select name="action" id="bulk-action-selector-top">
          <option value="-1"><?php esc_html_e('Bulk actions', 'change-checkpoints'); ?></option>
          <option value="delete"><?php esc_html_e('Delete selected', 'change-checkpoints'); ?></option>
          <option value="delete_all"><?php esc_html_e('Delete all', 'change-checkpoints'); ?></option>
        </select>
        <input type="submit" id="doaction" class="button action"
          value="<?php esc_attr_e('Apply', 'change-checkpoints'); ?>" onclick="return ccpConfirmBulkAction()" />
      </div>

      <?php if ($total_pages > 1): ?>
        <div class="tablenav-pages">
          <span class="displaying-num">
            <?php printf(
              _n('%d item', '%d items', $total_checkpoints, 'change-checkpoints'),
              number_format_i18n($total_checkpoints)
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
      <?php endif; ?>
    </div>

    <!-- Checkpoints Table -->
    <table class="wp-list-table widefat fixed striped">
      <thead>
        <tr>
          <td id="cb" class="manage-column column-cb check-column">
            <label class="screen-reader-text"
              for="cb-select-all-1"><?php esc_html_e('Select All', 'change-checkpoints'); ?></label>
            <input id="cb-select-all-1" type="checkbox" />
          </td>
          <th scope="col" class="manage-column column-name"><?php esc_html_e('Name', 'change-checkpoints'); ?></th>
          <th scope="col" class="manage-column column-status"><?php esc_html_e('Status', 'change-checkpoints'); ?></th>
          <th scope="col" class="manage-column column-created"><?php esc_html_e('Created At', 'change-checkpoints'); ?>
          </th>
          <th scope="col" class="manage-column column-closed"><?php esc_html_e('Closed At', 'change-checkpoints'); ?>
          </th>
          <th scope="col" class="manage-column column-changes"><?php esc_html_e('Changes', 'change-checkpoints'); ?>
          </th>
          <th scope="col" class="manage-column column-actions"><?php esc_html_e('Actions', 'change-checkpoints'); ?>
          </th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($checkpoints)): ?>
          <tr class="no-items">
            <td class="colspanchange" colspan="7">
              <?php if ($search): ?>
                <?php esc_html_e('No checkpoints found matching your search.', 'change-checkpoints'); ?>
              <?php else: ?>
                <?php esc_html_e('No checkpoints found.', 'change-checkpoints'); ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($checkpoints as $checkpoint): ?>
            <tr id="checkpoint-<?php echo intval($checkpoint->id); ?>">
              <th scope="row" class="check-column">
                <input type="checkbox" name="checkpoint[]" value="<?php echo intval($checkpoint->id); ?>" />
              </th>
              <td class="column-name">
                <strong>
                  <a
                    href="<?php echo esc_url(admin_url('admin.php?page=change-checkpoints-view&checkpoint_id=' . $checkpoint->id)); ?>">
                    <?php echo esc_html($checkpoint->title ?: sprintf(__('Checkpoint %s', 'change-checkpoints'), wp_date('Y-m-d H:i', strtotime($checkpoint->created_at)))); ?>
                  </a>
                </strong>
                <?php if ($checkpoint->note): ?>
                  <br><small
                    class="ccp-note-preview"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($checkpoint->note), 10)); ?></small>
                <?php endif; ?>
              </td>
              <td class="column-status">
                <span class="ccp-badge ccp-badge-<?php echo esc_attr($checkpoint->status); ?>">
                  <?php echo $checkpoint->status === 'open' ? esc_html__('Open', 'change-checkpoints') : esc_html__('Closed', 'change-checkpoints'); ?>
                </span>
              </td>
              <td class="column-created">
                <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($checkpoint->created_at))); ?>
              </td>
              <td class="column-closed">
                <?php if ($checkpoint->closed_at): ?>
                  <?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($checkpoint->closed_at))); ?>
                <?php else: ?>
                  <span class="ccp-text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="column-changes">
                <span class="ccp-changes-count"><?php echo intval($checkpoint->events_count); ?></span>
              </td>
              <td class="column-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=change-checkpoints-view&checkpoint_id=' . $checkpoint->id)); ?>"
                  class="button button-small">
                  <?php esc_html_e('View', 'change-checkpoints'); ?>
                </a>
                <?php if ($checkpoint->status === 'open'): ?>
                  <button type="button" class="button button-small"
                    onclick="ccpCloseCheckpoint(<?php echo intval($checkpoint->id); ?>)">
                    <?php esc_html_e('Close', 'change-checkpoints'); ?>
                  </button>
                <?php endif; ?>
                <button type="button" class="button button-small button-link-delete"
                  onclick="ccpDeleteCheckpoint(<?php echo intval($checkpoint->id); ?>)">
                  <?php esc_html_e('Delete', 'change-checkpoints'); ?>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td class="manage-column column-cb check-column">
            <label class="screen-reader-text"
              for="cb-select-all-2"><?php esc_html_e('Select All', 'change-checkpoints'); ?></label>
            <input id="cb-select-all-2" type="checkbox" />
          </td>
          <th scope="col" class="manage-column column-name"><?php esc_html_e('Name', 'change-checkpoints'); ?></th>
          <th scope="col" class="manage-column column-status"><?php esc_html_e('Status', 'change-checkpoints'); ?></th>
          <th scope="col" class="manage-column column-created"><?php esc_html_e('Created At', 'change-checkpoints'); ?>
          </th>
          <th scope="col" class="manage-column column-closed"><?php esc_html_e('Closed At', 'change-checkpoints'); ?>
          </th>
          <th scope="col" class="manage-column column-changes"><?php esc_html_e('Changes', 'change-checkpoints'); ?>
          </th>
          <th scope="col" class="manage-column column-actions"><?php esc_html_e('Actions', 'change-checkpoints'); ?>
          </th>
        </tr>
      </tfoot>
    </table>

    <div class="tablenav bottom">
      <div class="alignleft actions bulkactions">
        <label for="bulk-action-selector-bottom"
          class="screen-reader-text"><?php esc_html_e('Select bulk action', 'change-checkpoints'); ?></label>
        <select name="action2" id="bulk-action-selector-bottom">
          <option value="-1"><?php esc_html_e('Bulk actions', 'change-checkpoints'); ?></option>
          <option value="delete"><?php esc_html_e('Delete selected', 'change-checkpoints'); ?></option>
          <option value="delete_all"><?php esc_html_e('Delete all', 'change-checkpoints'); ?></option>
        </select>
        <input type="submit" id="doaction2" class="button action"
          value="<?php esc_attr_e('Apply', 'change-checkpoints'); ?>" onclick="return ccpConfirmBulkAction()" />
      </div>

      <?php if ($total_pages > 1): ?>
        <div class="tablenav-pages">
          <span class="displaying-num">
            <?php printf(
              _n('%d item', '%d items', $total_checkpoints, 'change-checkpoints'),
              number_format_i18n($total_checkpoints)
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
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Create Checkpoint Modal -->
<div id="ccp-create-modal" class="ccp-modal" style="display: none;">
  <div class="ccp-modal-content">
    <div class="ccp-modal-header">
      <h2><?php esc_html_e('Create New Checkpoint', 'change-checkpoints'); ?></h2>
      <button type="button" class="ccp-modal-close" onclick="ccpHideCreateModal()">&times;</button>
    </div>
    <form id="ccp-create-form" class="ccp-modal-body">
      <table class="form-table">
        <tr>
          <th scope="row">
            <label for="checkpoint_title"><?php esc_html_e('Name (Optional)', 'change-checkpoints'); ?></label>
          </th>
          <td>
            <input type="text" id="checkpoint_title" name="checkpoint_title" class="regular-text"
              placeholder="<?php esc_attr_e('Checkpoint {YYYY-MM-DD HH:mm}', 'change-checkpoints'); ?>" />
            <p class="description">
              <?php esc_html_e('If left empty, the name will default to "Checkpoint {YYYY-MM-DD HH:mm}".', 'change-checkpoints'); ?>
            </p>
          </td>
        </tr>
        <tr>
          <th scope="row">
            <label for="checkpoint_note"><?php esc_html_e('Note (Optional)', 'change-checkpoints'); ?></label>
          </th>
          <td>
            <textarea id="checkpoint_note" name="checkpoint_note" rows="3" class="large-text"
              placeholder="<?php esc_attr_e('Adding new image gallery', 'change-checkpoints'); ?>"></textarea>
          </td>
        </tr>
      </table>
      <div class="ccp-modal-footer">
        <button type="button" class="button" onclick="ccpHideCreateModal()">
          <?php esc_html_e('Cancel', 'change-checkpoints'); ?>
        </button>
        <button type="submit" class="button button-primary">
          <?php esc_html_e('Create', 'change-checkpoints'); ?>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Overlay -->
<div id="ccp-modal-overlay" class="ccp-modal-overlay" style="display: none;" onclick="ccpHideCreateModal()"></div>