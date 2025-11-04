# Change Checkpoints Plugin

Track changes to WordPress content within active checkpoints. Monitor Pages, Posts, Custom Post Types, and Taxonomies with a simple, focused interface.

## Features

- **Single Active Checkpoint**: Only one checkpoint can be active at a time
- **Automatic Content Tracking**: Records changes to posts, pages, custom post types, and taxonomies
- **Bilingual Support**: English (default) and Spanish translations included
- **WordPress Admin Integration**: Native WordPress admin interface styling
- **No jQuery Dependency**: Pure vanilla JavaScript for interactions

## What Gets Tracked

### Content Types
- **Pages**: Creation, updates, deletion, status changes
- **Posts**: Creation, updates, deletion, status changes  
- **Custom Post Types**: All public custom post types
- **Taxonomies**: Categories, Tags, and custom taxonomies

### Change Details
- Content creation, updates, and deletion
- Status changes (draft → published, etc.)
- Title updates
- Template changes (for pages)
- Parent/hierarchy changes
- Featured image changes
- Taxonomy term modifications (name, slug, parent changes)

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'Change Checkpoints' in the admin menu

## Usage

### Creating a Checkpoint
1. Go to **Change Checkpoints** in the WordPress admin
2. Click **Create Checkpoint** 
3. Optionally add a name and note
4. Click **Create** - the checkpoint becomes active immediately

### Viewing Changes
1. Click **View** next to any checkpoint in the list
2. See all changes organized by content type:
   - Pages
   - Posts  
   - Custom Post Types (grouped separately)
   - Taxonomies

### Managing Checkpoints
- **Close**: Stop tracking changes in the current checkpoint
- **Delete**: Remove a checkpoint and all its recorded changes
- **Bulk Actions**: Delete multiple checkpoints at once

## Interface Overview

### Overview Page
- Shows current checkpoint status (active/inactive)
- Lists all checkpoints with creation/close dates
- Displays change counts for each checkpoint
- Search by date/time functionality

### View Checkpoint Page  
- Detailed list of all changes during the checkpoint period
- Changes grouped by content type
- Shows timestamp, action, content name, author, and change details
- Example format: `14:32 · Page · update · Home · by Alice · status: publish → draft`

### Create Checkpoint Modal
- Simple form with optional name and note fields
- Auto-generates names like "Checkpoint 2024-01-15 14:30" if left empty
- Automatically closes any existing active checkpoint

## Technical Details

### Database Tables
- `wp_ccp_checkpoints`: Stores checkpoint information
- `wp_ccp_events`: Records individual change events

### Exclusions
The plugin automatically excludes:
- Autosaves and revisions
- Navigation menu items
- WordPress core post types (revisions, etc.)
- Import/bulk operations (when detectable)
- Non-public post types and taxonomies

### Permissions
- Requires `manage_options` capability (Administrator role)
- All actions include proper nonce verification

## Localization

### Supported Languages
- **English** (en_US) - Default
- **Spanish** (es_ES) - Complete translation

### Text Domain
- Text domain: `change-checkpoints`
- Translation files in `/languages/` directory

## Browser Compatibility

- Modern browsers with ES6 support
- No jQuery dependency
- Responsive design for mobile devices

## Developer Notes

### Hooks Available
- `ccp_checkpoint_created` - Fired when a checkpoint is created
- `ccp_checkpoint_closed` - Fired when a checkpoint is closed  
- `ccp_checkpoint_deleted` - Fired when a checkpoint is deleted
- `ccp_all_checkpoints_deleted` - Fired when all checkpoints are deleted

### File Structure
```
wordpressongoing-change-checkpoints/
├── wordpressongoing-change-checkpoints.php (Main plugin file)
├── includes/
│   ├── class-ccp-database.php
│   ├── class-ccp-checkpoint-manager.php
│   └── class-ccp-event-tracker.php
├── admin/
│   ├── class-ccp-admin.php
│   └── views/
│       ├── overview.php
│       └── view-checkpoint.php
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
└── languages/
    ├── change-checkpoints.pot
    └── change-checkpoints-es_ES.po
```

## Version History

### 1.0.0
- Initial release
- Core checkpoint functionality
- Content tracking for posts, pages, CPTs, and taxonomies
- Bilingual support (EN/ES)
- WordPress admin integration

## Support

For support, feature requests, or bug reports, please contact Joan Cochachi.

## License

GPL v2 or later

---

**Author**: Joan Cochachi  
**Version**: 1.0.0  
**Requires**: WordPress 5.0+  
**Tested up to**: WordPress 6.4  
**PHP**: 7.4+