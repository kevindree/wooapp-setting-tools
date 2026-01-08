# WooApp Setting Tools

A comprehensive WordPress plugin that provides API authentication, product category position management, and custom settings for WooCommerce mobile applications.

## Overview

WooApp Setting Tools is a powerful plugin designed to integrate WooCommerce stores with mobile applications. It provides REST API endpoints for user authentication, product category management, and custom app settings management.

## Features

### 🔐 API Authentication
- **User Login**: Secure REST API endpoint for mobile app user authentication
- **User Registration**: REST API endpoint for new user account creation
- **API Key Validation**: Built-in authentication checks for all API endpoints
- **Token-based Security**: Implements secure authentication mechanisms

### 📂 Category Position Management
- **Multi-Position Support**: Organize product categories into multiple custom positions
- **Admin Interface**: Intuitive WordPress admin interface for managing category positions
- **REST API Access**: Retrieve category position data via REST API endpoints
- **Dynamic Configuration**: Add, update, and delete category positions on the fly

### ⚙️ Core Features
- **Admin Dashboard**: Dedicated admin menu under "WooApp Settings"
- **Category Position Editor**: Visual interface for assigning categories to positions
- **REST API Endpoints**: 
  - `POST /wp-json/wooapp/v1/userlogin` - User login
  - `POST /wp-json/wooapp/v1/register` - User registration
  - `GET /wp-json/wooapp/v1/category-positions` - Get all positions
  - `GET /wp-json/wooapp/v1/category-positions/{position_key}` - Get specific position
  - `POST /wp-json/wooapp/v1/category-positions/{position_key}` - Update position

## Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 8.4 or higher
- **WooCommerce**: 10.3 or higher (tested up to 10.3)
- **MySQL/MariaDB**: Compatible with WordPress standard

## Installation

### Option 1: Manual Installation

1. Download the plugin files
2. Extract to `/wp-content/plugins/wooapp-setting-tools/`
3. Activate the plugin in WordPress Admin → Plugins
4. Navigate to **WooApp Settings** in the left admin menu

### Option 2: Via WordPress Admin

1. Go to WordPress Admin → Plugins → Add New
2. Search for "WooApp Setting Tools"
3. Click Install Now
4. Activate the plugin

## Quick Start

### After Activation

1. Go to **WooApp Settings** in the WordPress admin menu
2. Create category positions for your mobile app
3. Assign product categories to each position
4. Configure API authentication settings
5. Test API endpoints using your mobile app or API client

### Verify Installation

```bash
# Test the autoloader
php test-autoloader.php

# Check PHP syntax
php -l wooapp-setting-tools.php
```

## API Documentation

### User Authentication

#### Login
```
POST /wp-json/wooapp/v1/userlogin
```

Request:
```json
{
  "username": "user@example.com",
  "password": "password123"
}
```

#### Registration
```
POST /wp-json/wooapp/v1/register
```

Request:
```json
{
  "email": "newuser@example.com",
  "username": "newuser",
  "password": "password123"
}
```

### Category Positions

#### Get All Positions
```
GET /wp-json/wooapp/v1/category-positions
```

Response:
```json
{
  "featured": {
    "label": "Featured Categories",
    "categories": [1, 2, 3]
  },
  "new": {
    "label": "New Arrivals",
    "categories": [4, 5]
  }
}
```

#### Get Specific Position
```
GET /wp-json/wooapp/v1/category-positions/{position_key}
```

#### Update Position
```
POST /wp-json/wooapp/v1/category-positions/{position_key}
Content-Type: application/json

{
  "categories": [1, 2, 3]
}
```

## Architecture

### Project Structure

```
wooapp-setting-tools/
├── src/
│   ├── API/
│   │   ├── AppPositionEndpoints.php    # Category position API endpoints
│   │   ├── Authentication.php          # API authentication logic
│   │   ├── REST.php                    # REST API module
│   │   └── UserAuthEndpoints.php       # User authentication endpoints
│   ├── Admin/
│   │   └── Admin.php                   # Admin interface and settings
│   ├── Services/
│   │   └── CategoryPositionManager.php # Category position management
│   ├── Core/
│   │   ├── AbstractService.php         # Base service class
│   │   ├── Container.php               # Dependency injection container
│   │   └── Hooks.php                   # WordPress hooks management
│   ├── Common/
│   │   ├── Constants.php               # Plugin constants
│   │   ├── Security.php                # Security utilities
│   │   ├── VersionChecker.php          # Version compatibility checks
│   │   └── Helpers/
│   │       └── Helpers.php             # Utility functions
│   ├── Autoloader.php                  # PSR-4 autoloader
│   └── Plugin.php                      # Main plugin class
├── assets/
│   ├── css/                            # Admin and frontend styles
│   └── js/                             # Admin and frontend scripts
├── wooapp-setting-tools.php            # Plugin entry point
└── README.md                           # This file
```

### Design Patterns

- **Singleton Pattern**: Plugin class uses singleton pattern for single instance management
- **Service Container**: Dependency injection container for managing services
- **Abstract Services**: BaseService class for consistent service initialization
- **PSR-4 Autoloading**: Automatic class loading based on namespace structure

## Development

### Code Standards

- Follows WordPress coding standards
- PHP 8.4 compatible
- PSR-4 namespace conventions
- Comprehensive security checks

### Database Options

The plugin uses the following WordPress options to store data:

- `wooapp_category_positions` - Category position labels
- `wooapp_category_position_mapping` - Category to position mapping

### WooCommerce Compatibility

Declares compatibility with WooCommerce Custom Order Tables feature for better performance.

## Security

- **API Key Validation**: All API endpoints require authentication
- **Nonce Verification**: WordPress nonce protection for admin actions
- **User Capability Checks**: Only administrators can manage settings
- **Data Sanitization**: Input validation on all API endpoints
- **WPML Ready**: Text domain support for translations

## Support & Contributing

For issues, feature requests, or contributions:
- GitHub Repository: [github.com/kevindree/wooapp-setting-tools](https://github.com/kevindree/wooapp-setting-tools)

## License

GNU General Public License v3 or later

See [GPL-3.0 License](https://www.gnu.org/licenses/gpl-3.0.html) for details.

## Author

**Kevindree**
- Website: https://kevindree.geehootek.com
- Email: kevin@geehootek.com

## Changelog

### Version 1.0.0
- Initial release
- User authentication endpoints
- Category position management
- Admin settings interface
- REST API endpoints
