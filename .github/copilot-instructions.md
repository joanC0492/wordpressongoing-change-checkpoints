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
wp_ccp_events: id, object_kind(varchar), object_subtype, object_id, action(varchar), details_json, created_at
```

Simplified single-table structure with automatic event recording. The `object_kind` field distinguishes between:

- `post` - WordPress posts, pages, and custom post types
- `term` - Taxonomies (categories, tags, custom taxonomies)  
- `media` - Media library files (images, videos, audio, documents)
- `theme` - WordPress themes (activation and deletion)
- `menu` - Navigation menus and menu items
- `plugin` - WordPress plugins (activation, deactivation, deletion)
- `user` - WordPress users (creation, updates, deletion, role changes)
- `setting` - WordPress Settings (from WP admin settings pages)

**Important**: Uses `VARCHAR(32)` instead of `ENUM` for better flexibility and easier migrations.

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

**For Theme Events:**

```json
{
  // Minimal data - no confusing details displayed in UI
}
```

**For Menu Events:**

```json
{
  "menu_id": 123,
  "menu_name": "Main Navigation",
  "menu_item_type": "page", // For menu items: page, post, custom, category, tag
  "menu_item_object": "page", // The source object type
  "menu_item_object_id": 456, // ID of the source object
  "method": "delete_term" // Tracking method used
}
```

**For Plugin Events:**

```json
{
  "plugin_name": "Akismet Anti-Spam",
  "plugin_version": "5.3.1",
  "plugin_file": "akismet/akismet.php",
  "plugin_author": "Automattic"
}
```

**For User Events:**

```json
{
  "user_login": "johndoe",
  "user_email": "john@example.com",
  "role_from": "subscriber",
  "role_to": "editor",
  "role_display_name": "Editor"
}
```

**For Settings Events:**

```json
{
  "option_name": "blogname",
  "setting_label": "Site Title",
  "old_value": "My WordPress Site",
  "new_value": "New Site Name",
  "settings_page": "general"
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

### Navigation Menu Tracking

The plugin automatically tracks WordPress navigation menu changes with comprehensive coverage:

- **Menu creation**: Complete menu setup via `wp_create_nav_menu` hook
- **Menu updates**: Menu name/location changes via `wp_update_nav_menu` hook  
- **Menu deletion**: Menu removal via `delete_term` hook (more reliable than `wp_delete_nav_menu`)
- **Menu item operations**: Item additions, updates, and deletions with detailed type tracking
- **Accurate naming**: Menu deletions show actual menu names (e.g., "Main Navigation") instead of generic "Menu"

#### Menu Event Types

1. **Menu Management**:
   - `object_kind = 'menu'`, `object_subtype = 'nav_menu'`
   - Actions: `create`, `update`, `delete`
   - Shows menu name and basic menu information

2. **Menu Item Management**:
   - `object_kind = 'menu'`, `object_subtype = 'nav_menu_item'`
   - Actions: `create`, `update`, `delete`
   - Enhanced type display showing source type (Page, Post, Custom Link, Category, etc.)

#### Menu Item Type Detection

Menu items display enhanced information based on their source:

```php
// Menu item types shown in interface
$item_types = array(
    'post_type' => 'Page/Post', // Shows actual post type
    'taxonomy' => 'Category/Tag', // Shows actual taxonomy  
    'custom' => 'Custom Link',
    'post_type_archive' => 'Archive'
);
```

#### Implementation Notes

- **Hook Optimization**: Uses `delete_term` instead of `wp_delete_nav_menu` for better data access during deletion
- **Duplicate Prevention**: Single hook per operation to avoid duplicate event logging
- **Type Enhancement**: Menu items show meaningful types instead of redundant menu item names
- **Error Handling**: Graceful fallbacks when menu data is not available

### Theme Tracking

The plugin automatically tracks WordPress theme changes:

- **Theme activation**: When switching from one theme to another
- **Theme deletion**: When permanently removing themes from the system
- **Clean interface**: No confusing details displayed - only theme name and action
- **Simplified tracking**: Focuses on the essential information (what theme, what action)

Theme events show with minimal information to avoid interface clutter:

- **Type Column**: "Theme"  
- **Action Column**: "activate" or "delete"
- **Content Column**: Theme name only (no additional details)

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

## Database Migration Best Practices

### ENUM vs VARCHAR Considerations

**Avoid ENUM for dynamic values:**

- ENUMs are restrictive and hard to modify
- `dbDelta()` cannot update existing ENUM values
- Silent failures when inserting invalid ENUM values
- Use VARCHAR for better flexibility and future-proofing

**Successful Migration Pattern:**

```php
// Check version and force ALTER TABLE for ENUM to VARCHAR conversion
if (version_compare($installed_version, '2.2.0', '<')) {
    $wpdb->query("ALTER TABLE $events_table MODIFY COLUMN object_kind varchar(32) NOT NULL");
    $wpdb->query("ALTER TABLE $events_table MODIFY COLUMN action varchar(32) NOT NULL");
}
```

### Troubleshooting Database Issues

**Force Database Update Method:**

```php
public function force_database_update() {
    global $wpdb;
    $events_table = $wpdb->prefix . 'ccp_events';
    
    // Convert ENUM to VARCHAR for better flexibility
    $wpdb->query("ALTER TABLE $events_table MODIFY COLUMN object_kind varchar(32) NOT NULL");
    $wpdb->query("ALTER TABLE $events_table MODIFY COLUMN action varchar(32) NOT NULL");
    
    update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    return true;
}
```

**Emergency Migration Script Pattern:**

Create temporary PHP script for manual database updates when automatic migrations fail.

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

## Recent Enhancements (v2.2.0)

### Database Schema Fix (Critical)

**Problem Fixed**: The database used `ENUM` fields for `object_kind` and `action`, which caused major issues:

- ENUM only accepted predefined values like `('post','term')`
- When inserting `'media'` or `'theme'`, MySQL silently stored empty strings
- `dbDelta()` cannot modify existing ENUMs, making migrations nearly impossible
- Very restrictive and hard to maintain

**Solution**: Converted to `VARCHAR` for maximum flexibility:

- Database version bumped to `2.2.0`
- `object_kind` changed from ENUM to `varchar(32) NOT NULL`
- `action` changed from ENUM to `varchar(32) NOT NULL`
- Updated migration logic to use `ALTER TABLE` with VARCHAR conversion
- All existing and future values now work correctly

### Theme Tracking Implementation

1. **WordPress Theme Events**: Complete tracking of theme lifecycle:
   - Theme activation/switching via `switch_theme` hook
   - Theme deletion via `delete_theme` hook
   - Minimal data storage to avoid interface clutter

2. **Clean Interface Design**: Theme events display with simplified information:
   - Type shows as "Theme"
   - Actions show as "activate" or "delete"
   - Content shows only theme name (no confusing details)
   - No additional metadata displayed in details section

3. **Event Detection Logic**: Added `is_theme` flag for proper event classification:

   ```php
   if ($event->object_kind === 'theme') {
       $formatted->is_theme = true;
   }
   ```

4. **Interface Exclusions**: Theme events are excluded from detail display:

   ```php
   if (!empty($event->details) && empty($event->is_theme))
   ```

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

**Database Architecture**:

```sql
CREATE TABLE wp_ccp_events (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    object_kind varchar(32) NOT NULL,
    object_subtype varchar(64) NOT NULL,
    object_id bigint(20) DEFAULT NULL,
    object_name varchar(255) DEFAULT NULL,
    action varchar(32) NOT NULL,
    details_json longtext DEFAULT NULL,
    author_id bigint(20) DEFAULT NULL,
    timestamp datetime NOT NULL,
    PRIMARY KEY (id),
    KEY object_kind_subtype (object_kind, object_subtype),
    KEY timestamp (timestamp),
    KEY author_id (author_id)
);
```

**Media Type Detection Pattern:**

```php
// Works for both new and legacy formats
if ($event->object_kind === 'media' || in_array($event->object_subtype, array('image', 'video', 'audio', 'application'))) {
    // Handle as media
    $formatted->is_media = true;
}
```

**Theme Detection Pattern:**

```php
// Clean theme detection
if ($event->object_kind === 'theme') {
    $formatted->is_theme = true;
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

<!-- Content Column (for themes) -->
<strong>Twenty Twenty-Four</strong>
<!-- No additional details for clean interface -->

<!-- Type Column (for menus) -->
<span class="ccp-object-type">Menu</span>
<!-- Menu items show specific types: Page, Post, Custom Link, etc. -->

<!-- Content Column (for menu items) -->
<strong>Menu Item Title</strong>
<small class="ccp-details">Main Navigation</small>
```

## Recent Enhancements (v2.3.0)

### Complete Navigation Menu Tracking System

**Major Addition**: Comprehensive tracking of WordPress navigation menu operations:

1. **Full Menu Lifecycle Tracking**:
   - Menu creation via `wp_create_nav_menu` hook
   - Menu updates via `wp_update_nav_menu` hook  
   - Menu deletion via `delete_term` hook (more reliable than `wp_delete_nav_menu`)
   - Menu item additions, updates, and deletions

2. **Enhanced Menu Deletion Accuracy**:
   - **Problem Solved**: Menu deletions previously showed generic "Menu" name
   - **Solution Implemented**: Using `delete_term` hook provides access to actual menu name before deletion
   - **Result**: Deletions now show specific menu names (e.g., "New Header Es", "Main Navigation")

3. **Menu Item Type Enhancement**:
   - Menu items now display meaningful types instead of redundant names
   - Shows source types: "Page", "Post", "Custom Link", "Category", "Tag", etc.
   - Enhanced user understanding of what each menu item represents

4. **Hook Strategy Optimization**:
   - Disabled `wp_delete_nav_menu` hook due to limited data access at deletion time
   - Primary reliance on `delete_term` hook for accurate menu name capture
   - Duplicate prevention through strategic hook selection

### Implementation Architecture

**Navigation Menu Hooks System:**

```php
// Core menu operations
add_action('wp_create_nav_menu', array($this, 'track_nav_menu_create'), 10, 2);
add_action('wp_update_nav_menu', array($this, 'track_nav_menu_update'), 10, 1);
add_action('wp_update_nav_menu_item', array($this, 'track_nav_menu_item_update'), 10, 3);

// Menu item deletion (posts)
add_action('before_delete_post', array($this, 'track_nav_menu_item_delete'), 5, 2);

// Menu deletion (terms) - primary method
add_action('delete_term', array($this, 'track_nav_menu_term_delete'), 10, 4);
```

**Menu Event Database Structure:**

```php
// Menu events
object_kind = 'menu'
object_subtype = 'nav_menu' | 'nav_menu_item'
action = 'create' | 'update' | 'delete'

// Enhanced details JSON
{
  "menu_id": 123,
  "menu_name": "Actual Menu Name",
  "menu_item_type": "page",
  "menu_item_object": "page", 
  "menu_item_object_id": 456,
  "method": "delete_term"
}
```

### Interface Enhancements

1. **Menu Type Display**:
   - Menus show as "Menu" type in main column
   - Menu items show specific source types (Page, Post, Custom Link, etc.)
   - Clear visual distinction between menu management and item management

2. **Menu Content Display**:
   - Menu operations show menu name prominently
   - Menu item operations show item title with menu name as subtitle
   - Enhanced readability and context for all menu-related events

3. **Event Classification Logic**:

   ```php
   // Menu detection for interface formatting
   if ($event->object_kind === 'menu') {
       if ($event->object_subtype === 'nav_menu_item') {
           $formatted->is_menu_item = true;
           // Show enhanced menu item types
       } else {
           $formatted->is_menu = true;
           // Show standard menu operations
       }
   }
   ```

### Testing and Validation

**Verified Workflows**:

✅ Menu creation with custom names  
✅ Menu updates (name/location changes)  
✅ Menu item additions (pages, posts, custom links)  
✅ Menu item modifications and reordering  
✅ Menu item deletions with proper tracking  
✅ Complete menu deletions with accurate naming  
✅ Interface display with enhanced type information  

**Critical Fix Validated**:
- Menu deletion naming issue resolved
- Previously: "Menu" (generic)
- Now: "New Header Es", "Main Navigation", etc. (actual names)

### Migration and Compatibility

**Database Compatibility**: All menu events integrate seamlessly with existing `wp_ccp_events` table structure using the established `object_kind` and `object_subtype` pattern.

**Backward Compatibility**: Existing event display logic enhanced without breaking changes to non-menu event types.

**Performance Impact**: Minimal - uses WordPress native hooks with efficient data access patterns.

## Recent Enhancements (v2.4.0)

### WordPress Plugin Tracking System

**Major Addition**: Comprehensive tracking of WordPress plugin lifecycle operations:

1. **Full Plugin Lifecycle Tracking**:
   - Plugin activation via `activated_plugin` hook
   - Plugin deactivation via `deactivated_plugin` hook
   - Plugin deletion via `deleted_plugin` hook

2. **Enhanced Plugin Information**:
   - Plugin name, version, author information
   - Plugin file path for identification
   - Clean interface display with plugin metadata

3. **Event Structure**:
   ```php
   object_kind = 'plugin'
   object_subtype = 'plugin'
   action = 'activate' | 'deactivate' | 'delete'
   ```

4. **Interface Integration**:
   - Plugins show as "Plugin" type in main column
   - Plugin name displayed prominently in content
   - Version and author information available in details

### WordPress User Management Tracking

**Major Addition**: Complete user lifecycle and role management tracking:

1. **User Lifecycle Events**:
   - User registration via `user_register` hook
   - User profile updates via `profile_update` hook
   - User deletion via `delete_user` hook

2. **Role Change Detection**:
   - Automatic detection of role changes during user updates
   - Display of previous and new roles
   - Human-readable role names in interface

3. **Enhanced User Information**:
   ```php
   object_kind = 'user'
   object_subtype = 'user'
   action = 'create' | 'update' | 'delete'
   ```

4. **Interface Features**:
   - Users show as "User" type
   - Role information displayed as subtitle
   - Clean separation of user management from other events

### WordPress Settings Tracking System

**Major Addition**: Comprehensive tracking of WordPress admin settings changes:

1. **Settings Pages Coverage**:
   - **General Settings** (`options-general.php`): Site title, tagline, URLs, email, timezone
   - **Writing Settings** (`options-writing.php`): Default categories, post formats, mail server
   - **Reading Settings** (`options-reading.php`): Homepage settings, posts per page, RSS
   - **Discussion Settings** (`options-discussion.php`): Comments, moderation, notifications
   - **Media Settings** (`options-media.php`): Image sizes, upload organization
   - **Permalink Settings** (`options-permalink.php`): URL structure, category/tag bases

2. **Intelligent Setting Detection**:
   - Only tracks relevant admin settings (ignores internal WordPress options)
   - Filters out unchanged values to prevent noise
   - Categorizes settings by admin page for better organization

3. **Enhanced Value Handling**:
   - Password fields are hidden for security (`(hidden)`)
   - Boolean values displayed as "Yes/No"
   - Long strings truncated with ellipsis
   - Empty values shown as "(empty)"

4. **Event Structure**:
   ```php
   object_kind = 'setting'
   object_subtype = 'general|writing|reading|discussion|media|permalink'
   action = 'update'
   details = {
     "option_name": "blogname",
     "setting_label": "Site Title", 
     "old_value": "Old Value",
     "new_value": "New Value",
     "settings_page": "general"
   }
   ```

5. **Interface Enhancement**:
   - Settings display as "Settings (Page Name)" type
   - Clear "From → To" value display in details
   - Organized by settings page for easy identification

### Database Schema Updates

**Enhanced Object Kind Support**: Database whitelist updated to include all new object types:

```php
// Supported object_kind values
array('post', 'term', 'media', 'theme', 'plugin', 'menu', 'user', 'setting')
```

**Interface Flag System**: All event types now use centralized flag initialization:

```php
// Centralized flag initialization prevents redundant assignments
$formatted->is_media = false;
$formatted->is_theme = false; 
$formatted->is_plugin = false;
$formatted->is_user = false;
$formatted->is_menu = false;
$formatted->is_menu_item = false;
$formatted->is_setting = false;
```

### Testing and Validation

**Plugin Tracking Verified**:

✅ Plugin activation with metadata capture  
✅ Plugin deactivation tracking  
✅ Plugin deletion with proper cleanup  
✅ Plugin information display (name, version, author)  

**User Tracking Verified**:

✅ User registration with email capture  
✅ User profile updates and role changes  
✅ User deletion tracking  
✅ Role display names in interface  

**Settings Tracking Verified**:

✅ All six settings pages functional  
✅ Value change detection and formatting  
✅ From/To display in interface  
✅ Security handling for sensitive values  
✅ Page categorization working correctly  

### Implementation Architecture

**Hook Strategy**: Uses WordPress native hooks for maximum reliability:

```php
// Plugin tracking
add_action('activated_plugin', array($this, 'track_plugin_activate'), 10, 2);
add_action('deactivated_plugin', array($this, 'track_plugin_deactivate'), 10, 2);
add_action('deleted_plugin', array($this, 'track_plugin_delete'), 10, 2);

// User tracking  
add_action('user_register', array($this, 'track_user_create'), 10, 2);
add_action('profile_update', array($this, 'track_user_update'), 10, 3);
add_action('delete_user', array($this, 'track_user_delete'), 10, 3);

// Settings tracking
add_action('update_option', array($this, 'track_setting_update'), 10, 3);
```

**Performance Considerations**: All new tracking systems:

- Use efficient WordPress core functions
- Include proper exclusion logic to prevent noise
- Maintain minimal database overhead
- Integrate seamlessly with existing event structure

### Migration and Compatibility

**Database Compatibility**: All new event types integrate seamlessly with existing `wp_ccp_events` table structure using the established `object_kind` and `object_subtype` pattern.

**Backward Compatibility**: Existing event display logic enhanced without breaking changes to existing event types.

**Interface Consistency**: All new event types follow established display patterns and flag systems for consistent user experience.
