# Change Checkpoints Plugin - AI Development Guide

## Architecture Overview

This WordPress plugin implements a **single-active-checkpoint** pattern for tracking content changes. Only one checkpoint can be active at a time, and all content modifications (posts, pages, CPTs, taxonomies) are automatically recorded when a checkpoint is active.

### Core Components

- **Main Plugin** (`wordpressongoing-change-checkpoints.php`): Singleton pattern initialization
- **Database Layer** (`includes/class-ccp-database.php`): Custom tables with foreign key constraints
- **Checkpoint Manager** (`includes/class-ccp-checkpoint-manager.php`): Business logic for checkpoint lifecycle
- **Event Tracker** (`includes/class-ccp-event-tracker.php`): WordPress hooks integration for change detection
- **Admin Interface** (`admin/class-ccp-admin.php`): WordPress admin integration with AJAX

### Database Schema

```sql
wp_ccp_checkpoints: id, title, note, status(open/closed), created_at, closed_at
wp_ccp_events: checkpoint_id, object_kind(post/term), object_subtype, object_id, action(create/update/delete), details_json
```

Events are CASCADE deleted when checkpoints are removed.

## Development Patterns

### Plugin Initialization

Uses lazy loading pattern - components get database instances via `ccp()->database` after main initialization. Event tracking hooks are only registered when an active checkpoint exists:

```php
if ($this->checkpoint_manager->has_active_checkpoint()) {
    $this->event_tracker->init_hooks();
}
```

### Event Tracking Exclusions

The plugin excludes specific content types to avoid noise:

- Autosaves/revisions (`wp_is_post_autosave()`, `wp_is_post_revision()`)
- Non-public post types and taxonomies
- Core WordPress types (see `$excluded_post_types` in `class-ccp-event-tracker.php`)
- Import operations (`WP_IMPORTING`)

### WordPress Admin Integration

- **No jQuery dependency** - uses vanilla JavaScript with fetch API
- **AJAX pattern**: Dual handlers (form submission + AJAX) for graceful degradation
- **Nonce verification**: Separate nonces for different actions
- **Capability checks**: All actions require `manage_options`

### Localization Architecture

- Text domain: `change-checkpoints`
- Bilingual support (EN/ES) with complete translations
- JavaScript strings localized via `wp_localize_script()`

## Key Workflows

### Adding New Trackable Events

1. Add hooks in `CCP_Event_Tracker->init_hooks()`
2. Create handler method following pattern: `track_[event_type]_[action]()`
3. Use `track_event()` private method with standardized parameters
4. Add exclusion logic if needed

### Adding New Admin Actions

1. Add AJAX handler in `CCP_Admin` class following pattern: `ajax_[action_name]()`
2. Add form handler in `handle_admin_actions()` for non-JS fallback
3. Register both in constructor
4. Add JavaScript function in `admin.js` with consistent error handling

### Database Queries

Use the database layer methods (`CCP_Database`) rather than direct WordPress queries. Methods follow WordPress coding standards and include proper escaping.

## Testing Considerations

- **Active checkpoint state**: Many features only work when checkpoint is active
- **User capabilities**: All operations require `manage_options`
- **Content exclusions**: Test with revisions, autosaves, and non-public content types
- **AJAX fallbacks**: Ensure forms work without JavaScript

## Translation Workflow

Strings use descriptive context and sprintf patterns for pluralization:

```php
sprintf(_n('%d checkpoint deleted.', '%d checkpoints deleted.', $count, 'change-checkpoints'), $count)
```

Generate POT file: `wp i18n make-pot . languages/change-checkpoints.pot`

## Security Patterns

- All user input sanitized with appropriate WordPress functions
- Database interactions use prepared statements via WordPress methods  
- Nonce verification on all state-changing operations
- Capability checks at multiple levels (admin handlers, AJAX, database operations)