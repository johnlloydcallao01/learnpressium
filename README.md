# Learnpressium
haha
A comprehensive WordPress plugin to extend LearnPress functionality with advanced trainee management, reporting, and additional features.

## Description

Learnpressium is designed to enhance your LearnPress-powered learning management system by providing advanced trainee management capabilities. This plugin offers a modular structure that allows for easy extension and customization.

## Features

### Trainees Module
- **Advanced Trainee Management**: View and manage all trainees (customers) in a comprehensive dashboard
- **Detailed Trainee Profiles**: Edit and update trainee information including personal details, contact information, and emergency contacts
- **Course Enrollment Tracking**: Monitor which courses trainees are enrolled in and their active enrollments
- **Excel Export**: Export trainee data to Excel format with detailed formatting and course information
- **Responsive Interface**: Clean, WordPress-native admin interface that works on all devices

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- LearnPress plugin (active)

## Installation

1. Upload the `learnpressium` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure LearnPress plugin is installed and activated
4. Navigate to the 'Trainees' menu in your WordPress admin to start using the plugin

## Usage

### Accessing Trainees
1. Go to your WordPress admin dashboard
2. Click on "Trainees" in the admin menu
3. View the comprehensive list of all trainees with their enrollment status

### Managing Individual Trainees
1. From the trainees list, hover over a trainee's name
2. Click "View" to access their detailed profile
3. Edit any information and click "Save Changes"

### Exporting Data
1. From the trainees list page
2. Click the "Download" button
3. An Excel file will be generated and downloaded with all trainee data

## Plugin Structure

```
learnpressium/
├── learnpressium.php (main plugin file)
├── includes/
│   ├── class-learnpressium.php (main plugin class)
│   ├── class-learnpressium-activator.php (activation hooks)
│   ├── class-learnpressium-deactivator.php (deactivation hooks)
│   ├── class-learnpressium-loader.php (hooks loader)
│   ├── class-learnpressium-admin.php (admin functionality)
│   └── modules/
│       └── trainees/
│           ├── class-trainees-module.php (main trainees functionality)
│           ├── class-trainees-admin.php (admin interface)
│           ├── class-trainees-export.php (export functionality)
│           └── class-trainees-profile.php (individual profile management)
├── admin/
│   ├── css/
│   │   └── learnpressium-admin.css
│   └── js/
│       └── learnpressium-admin.js
└── README.md
```

## Extending the Plugin

The plugin is designed with a modular architecture to make it easy to add new features:

1. Create a new module directory under `includes/modules/`
2. Follow the same pattern as the trainees module
3. Register your module in the main plugin class
4. Add appropriate hooks and filters

## Support

For support and feature requests, please contact the plugin developer.

## Changelog

### 1.0.0
- Initial release
- Trainees management module
- Excel export functionality
- Individual trainee profile editing
- Course enrollment tracking

## License

This plugin is licensed under the GPL v2 or later.
