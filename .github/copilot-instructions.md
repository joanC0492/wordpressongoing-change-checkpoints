# WordPress Change Tracker Plugin - AI Development Guide

## Architecture Overview

This WordPress plugin implements **automatic continuous tracking** of content changes. All content modifications (posts, pages, CPTs, taxonomies) are automatically recorded as soon as the plugin is activated, without requiring manual checkpoints.

### Core Components

- **Main Plugin** (`wordpressongoing-change-checkpoints.php`): Singleton pattern initialization with automatic tracking
- **Database Layer** (`includes/class-ccp-database.php`): Single events table with simplified schema
- **Event Tracker** (`includes/class-ccp-event-tracker.php`): WordPress hooks integration for automatic change detection
- **Admin Interface** (`admin/class-ccp-admin.php`): Event display and management interface

**Note**: The checkpoint manager component has been completely removed in v2.0.0 as part of the architectural simplification.

### Database Schema

```sql
wp_ccp_events: id, object_kind(post/term/media), object_subtype, object_id, action(create/update/delete), details_json, created_at
```

Simplified single-table structure with automatic event recording. The `object_kind` field distinguishes between:

- `post` - WordPress posts, pages, and custom post types
- `term` - Taxonomies (categories, tags, custom taxonomies)  
- `media` - Media library files (images, videos, audio, documents)

### Event Details JSON Structure

The `details_json` field stores event-specific metadata:

**For Media Events:**

```json
{
  "file_name": "image.jpg",
  "file_type": "image/jpeg", 
  "file_size": 245760,
  "media_type": "image",
  "file_url": "http://site.com/wp-content/uploads/2025/11/image.jpg"
}
```

**For Post Events:**

```json
{
  "status_from": "draft",
  "status_to": "publish",
  "thumbnail_changed": true,
  "template_changed": true,
  "parent_changed": true
}
```

## Development Patterns

### Plugin Initialization

Uses lazy loading pattern - components get database instances via `ccp()->database` after main initialization. Event tracking hooks are automatically registered on plugin activation:

```php
// Event tracking is always active - no checkpoint verification needed
$this->event_tracker->init_hooks();
```

### Content Grouping

All tracked content is grouped under a single "WordPress" category, regardless of post type or taxonomy. This simplifies the interface and provides a unified view of all site changes.

### Clear All Functionality

The admin interface includes a "Clear All" button that allows administrators to remove all tracked events at once. This provides easy maintenance and cleanup capabilities.

### Event Tracking Exclusions

The plugin excludes specific content types to avoid noise:

- Autosaves/revisions (`wp_is_post_autosave()`, `wp_is_post_revision()`)
- Non-public post types and taxonomies
- Core WordPress types (see `$excluded_post_types` in `class-ccp-event-tracker.php`)
- Import operations (`WP_IMPORTING`)

### Media Tracking

The plugin automatically tracks media library events including:

- **File uploads**: Images, videos, audio files, and documents
- **Media modifications**: Changes to titles, alt text, captions, and metadata
- **File deletions**: Complete removal of media files from the library
- **Supported formats**: All standard WordPress media types (MIME type based detection)

Media events are grouped under the "WordPress" category with specific subtypes:

- `image` - All image formats (JPEG, PNG, GIF, WebP, SVG, etc.)
- `video` - Video files (MP4, AVI, MOV, etc.)
- `audio` - Audio files (MP3, WAV, OGG, etc.)
- `application` - Documents (PDF, DOC, ZIP, etc.)

#### Media Display Enhancement

Media events in the admin interface display with enhanced formatting:

- **Type Column**: Shows media type (e.g., "Image") with "Media" subtitle for visual distinction
- **Content Column**: Displays file name as primary content with file URL shown below for easy access
- **Legacy Support**: Handles both new format (`object_kind = 'media'`) and legacy format (empty `object_kind` with media `object_subtype`)

The interface automatically detects media events using either:

```php
$event->object_kind === 'media' || in_array($event->object_subtype, array('image', 'video', 'audio', 'application'))
```

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

- **Automatic tracking**: All events are recorded immediately upon content changes
- **User capabilities**: All operations require `manage_options`
- **Content exclusions**: Test with revisions, autosaves, and non-public content types
- **AJAX fallbacks**: Ensure forms work without JavaScript
- **Clear All functionality**: Test bulk deletion operations with large datasets

## Translation Workflow

Strings use descriptive context and sprintf patterns for pluralization:

```php
sprintf(_n('%d event deleted.', '%d events deleted.', $count, 'change-checkpoints'), $count)
```

Generate POT file: `wp i18n make-pot . languages/change-checkpoints.pot`

## Security Patterns

- All user input sanitized with appropriate WordPress functions
- Database interactions use prepared statements via WordPress methods  
- Nonce verification on all state-changing operations
- Capability checks at multiple levels (admin handlers, AJAX, database operations)

## Version 2.0.0 Changes

### Major Architectural Updates

1. **Checkpoint System Elimination**: Removed the entire checkpoint manager system. Events are now automatically tracked without requiring manual checkpoint creation.

2. **Database Simplification**:
   - Removed `wp_ccp_checkpoints` table
   - Simplified `wp_ccp_events` table structure
   - Added `id` and `created_at` columns to events table
   - Removed `checkpoint_id` foreign key dependency

3. **Content Grouping Simplification**: All content types (posts, pages, CPTs, taxonomies) are now grouped under "WordPress" category instead of separate type-based groupings.

4. **Clear All Functionality**: Added bulk deletion capability to remove all tracked events at once.

5. **Translation Updates**: Updated all language files to reflect new plugin name "WordPress Change Tracker" and new interface terminology.

### Migration Notes

- Database schema is automatically updated via version-based migrations
- Existing event data is preserved during the upgrade
- Old checkpoint data is safely removed during migration
- Plugin maintains backward compatibility for data display

### New Admin Interface

- **Events Overview**: Displays all tracked events in a unified table
- **WordPress Grouping**: All events appear under "WordPress" category
- **Clear All Button**: Prominent button for bulk event deletion
- **Simplified Navigation**: Removed checkpoint-related navigation elements
- **Automatic Tracking Indicator**: Interface shows that tracking is always active

## Recent Enhancements (v2.1.0)

### Enhanced Media Display

1. **Dual-line Type Display**: Media types now show with enhanced formatting:
   - Primary type ("Image", "Video", "Audio", "Application") in main text
   - "Media" subtitle for visual distinction

2. **File URL Display**: Media events show file URLs in the content column:
   - File name displayed as primary content
   - Full file URL shown below for easy access and reference
   - Only displayed for media types (image, video, audio, application)

3. **Legacy Compatibility**: Maintains backward compatibility with existing records:
   - Handles records with `object_kind = 'media'` (new format)
   - Handles records with empty `object_kind` but media `object_subtype` (legacy format)

### Implementation Details

**Media Type Detection Pattern:**

```php
// Works for both new and legacy formats
if ($event->object_kind === 'media' || in_array($event->object_subtype, array('image', 'video', 'audio', 'application'))) {
    // Handle as media
    $formatted->is_media = true;
}
```

**Display Structure:**

```html
<!-- Type Column -->
<span class="ccp-object-type">Image</span>
<small class="ccp-details" style="display: block; padding-left: 6px;">Media</small>

<!-- Content Column (for media) -->
<strong>filename.jpg</strong>
<small class="ccp-details" style="display: block; padding-left: 0;">
    http://site.com/wp-content/uploads/2025/11/filename.jpg
</small>
```
