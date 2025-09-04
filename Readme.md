# GoalV Football Predictions Plugin

A complete WordPress football match prediction system with advanced voting features, API integration, and responsive design.

## 📋 Overview

The GoalV Football Predictions Plugin is a comprehensive WordPress plugin that allows users to predict football match outcomes with a sophisticated dual voting system. It features automatic match synchronization, categorized voting options, and multiple display templates.

### 🌟 Key Features

- Dual Voting System: Basic voting on homepage, detailed voting on match pages
- Smart Game Week Management: Automatic football week detection and manual override
- Vote Categorization: Organized voting options by match result, score, goals, etc.
- Custom Voting Options: Admin can add custom predictions for any match
- Multiple Display Templates: Card, grid, and table layouts via shortcodes
- Real-time Updates: Live vote counting without page refresh
- Mobile Responsive: Optimized for all device sizes
- API Integration: Syncs with football-data.org for match data

## 🚀 Quick Start

### Installation

1. Upload the plugin files to `/wp-content/plugins/goalv-football-predictions/`
2. Activate the plugin through the WordPress admin
3. Database tables are created automatically on activation
4. Navigate to GoalV Settings in your WordPress admin

### Initial Setup

1. Add API Key: Get your free API key from [football-data.org](https://football-data.org) and add it in settings
2. Select Competition: Choose your preferred league (default: Premier League)
3. Test Connection: Use the "Test API Connection" button to verify setup
4. Sync Matches: Click "Sync Current Week" to fetch upcoming matches

### Display Matches

Add matches to any page or post using shortcodes:

```php
[goalv_matches]                    // Default card layout
[goalv_matches template="card"]    // Card-based list
[goalv_matches template="grid"]    // 2-column grid  
[goalv_matches template="table"]   // Table format
```

## 🛠️ Technical Specifications

### Plugin Details
- Version: 6.0.5
- WordPress Version: 5.0+
- PHP Version: 7.4+
- Text Domain: goalv
- Custom Post Type: goalv_matches

### Database Structure

The plugin creates four custom tables:

#### goalv_vote_options
Stores all voting options with categorization support
```sql
- id (Primary Key)
- match_id (Foreign Key)
- option_text (Vote option text)
- option_type (basic/detailed)
- category (Vote category)
- votes_count (Cached count)
- is_custom (Custom admin option)
- display_order (Sort order)
```

#### goalv_vote_categories  
Manages vote category definitions
```sql
- id (Primary Key)
- category_key (Unique identifier)
- category_label (Display name)
- display_order (Sort order)
- is_active (Status flag)
```

#### goalv_votes
Records individual votes
```sql
- id (Primary Key)
- match_id (Foreign Key)
- option_id (Foreign Key)
- user_id (WordPress User ID)
- user_ip (IP tracking)
- browser_id (Browser fingerprint)
- vote_location (homepage/details)
```

#### goalv_vote_summary
Performance optimization table
```sql
- match_id (Foreign Key)
- option_id (Foreign Key) 
- vote_location (Context)
- total_votes (Aggregated count)
```

## 🎯 Voting System

### Homepage Voting (Anonymous)
- 3 Basic Options: Home Win, Draw, Away Win
- Tracking: localStorage + browser fingerprint
- Display: Percentages only
- Real-time: Updates without page refresh

### Single Match Page Voting (Login Required)
- 12+ Detailed Options: Organized by category
- Categories: Match result, exact scores, goals, first scorer
- Display: Full results with progress bars
- Features: Vote changes (if enabled), complete statistics

### Vote Categories

#### Default Categories:
- match_result: Home Win, Draw, Away Win
- match_score: Exact score predictions (2-1, 1-2, 1-1)  
- goals_threshold: Over/Under 2.5 Goals
- both_teams_score: Both Teams Score Yes/No
- first_to_score: Which team scores first
- other: Custom admin-created options

## 📁 File Structure

```
goalv-football-predictions/
├── goalv-football-predictions.php     # Main plugin file
├── includes/                          # Core functionality
│   ├── class-goalv-cpt.php           # Custom post type
│   ├── class-goalv-admin.php         # Admin functionality
│   ├── class-goalv-api.php           # API integration
│   ├── class-goalv-voting.php        # Voting system
│   └── class-goalv-frontend.php      # Frontend display
├── templates/                         # Display templates
│   ├── single-goalv_matches.php      # Single match page
│   ├── matches-card.php              # Card layout
│   ├── matches-grid.php              # Grid layout
│   └── matches-table.php             # Table layout
├── assets/                            # Static assets
│   ├── css/goalv-style.css           # Plugin styles
│   └── js/                           # JavaScript files
│       ├── goalv-frontend.js         # Frontend functionality
│       └── goalv-admin.js            # Admin functionality
└── admin/                            # Admin interface
    └── admin-page.php                # Settings page
```

## ⚙️ Configuration Options

### Admin Settings

Navigate to GoalV Settings in WordPress admin:

#### API Configuration
- API Key: Your football-data.org API key
- Competition: Select league (Premier League, La Liga, etc.)
- API Timeout: Request timeout setting

#### Game Week Management
- Current Week Display: Shows detected game week
- Week Selector: Manual week selection (1-38)
- Sync Options: Current week or selected week sync
- Last Synced: Display of last sync timestamp

#### Voting Settings
- Allow Vote Changes: Enable/disable vote modifications
- Vote Tracking Method: IP, browser fingerprint, or user account
- Results Display: Show percentages, counts, or both

#### Display Options
- Default Template: Choose default shortcode template
- Team Logo Fallbacks: Configure logo display options
- Mobile Breakpoints: Responsive design settings

## 🎨 Customization

### Custom CSS Classes

The plugin provides extensive CSS classes for customization:

```css
.goalv-matches-container          / Main container /
.goalv-match-card                 / Individual match card /
.goalv-voting-group               / Vote category group /
.goalv-vote-option               / Individual vote option /
.goalv-vote-btn                  / Vote button /
.goalv-team-logos                / Team logo container /
.goalv-match-info                / Match information /
```

### Template Overrides

Copy template files to your theme directory:
```
your-theme/goalv-football-predictions/
├── single-goalv_matches.php
├── matches-card.php
├── matches-grid.php
└── matches-table.php
```

### Custom Vote Options

Admins can add custom voting options through the match edit screen:
1. Edit any match in WordPress admin
2. Scroll to "Custom Vote Options" meta box
3. Add custom predictions with categories
4. Use drag-and-drop to reorder options

## 🔧 Developer Hooks

### Actions
```php
do_action('goalv_before_vote', $match_id, $option_id, $user_id);
do_action('goalv_after_vote', $match_id, $option_id, $user_id);
do_action('goalv_match_synced', $match_id, $match_data);
```

### Filters
```php
apply_filters('goalv_vote_options', $options, $match_id);
apply_filters('goalv_match_display_data', $match_data);
apply_filters('goalv_api_request_args', $args);
```

## 🔒 Security Features

- Nonce Verification: All AJAX requests protected
- Data Sanitization: Input validation and sanitization
- SQL Injection Prevention: Prepared statements throughout
- Rate Limiting: API request throttling
- Capability Checks: User permission verification
- Browser Fingerprinting: Secure anonymous vote tracking

## 🚀 Performance Optimizations

- API Caching: 5-minute response caching
- Vote Summary Table: Optimized vote aggregation
- Database Indexing: Efficient query performance
- Asset Optimization: Separated JS files, minification ready
- Smart Loading: Only load required scripts per page

## 📱 Mobile Support

- Responsive Design: All templates mobile-optimized
- Touch-friendly: Large tap targets for voting
- Fast Loading: Optimized for mobile networks
- Progressive Enhancement: Works without JavaScript

## 🐛 Troubleshooting

### Common Issues

#### API Connection Failed
1. Verify API key is correct
2. Check internet connection
3. Ensure football-data.org is accessible
4. Try "Test API Connection" button

#### No Matches Displaying
1. Check if current week has matches
2. Try syncing different game week
3. Verify API key has correct permissions
4. Check WordPress error logs

#### Voting Not Working
1. Clear browser cache and cookies
2. Check JavaScript console for errors
3. Verify user login status for detailed voting
4. Ensure proper nonce generation

#### Styling Issues
1. Check for theme CSS conflicts
2. Verify plugin CSS is loading
3. Clear any caching plugins
4. Check responsive design on different devices

### Debug Mode

Enable WordPress debug mode for troubleshooting:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📚 API Reference

### Football-data.org Integration

The plugin uses the free tier of football-data.org API:
- Rate Limit: 10 requests per minute
- Coverage: Major European leagues
- Data: Fixtures, results, team information
- Update Frequency: Real-time match data

### Supported Competitions
- Premier League (England)
- La Liga (Spain)  
- Serie A (Italy)
- Bundesliga (Germany)
- Ligue 1 (France)
- And more...

## 🤝 Contributing

### Development Setup

1. Clone the repository
2. Set up local WordPress environment
3. Install plugin in development mode
4. Enable WP_DEBUG for development

### Code Standards
- Follow WordPress coding standards
- Use proper sanitization and validation
- Add inline documentation
- Test on multiple PHP/WordPress versions

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🔄 Changelog

### Version 6.0.5
- Enhanced vote categorization system
- Improved game week detection
- Added custom voting options
- Multiple template support
- Performance optimizations
- Mobile responsiveness improvements
- Security enhancements

## 📧 Support

For support and feature requests:
1. Check troubleshooting section
2. Review documentation
3. Submit detailed bug reports with:
   - WordPress version
   - Plugin version
   - PHP version
   - Error messages
   - Steps to reproduce

---

GoalV Football Predictions Plugin - Making football predictions interactive and engaging! ⚽#   G o a l V - w o r d p r e s s - p l u g i n  
 