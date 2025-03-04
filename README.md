# Disable Delete Students Plugin

## Overview

The **Disable Delete Students** plugin for Moodle is designed to manage student accounts by automatically disabling or deleting them based on specific criteria. This helps maintain a clean and efficient user database by ensuring that inactive student accounts are handled appropriately.

## Features

- **Disable Student Accounts**: Automatically disable student accounts that have not been active for a specified duration after their account creation date.
- **Delete Student Accounts**: Automatically delete student accounts that have not been active for a specified duration after their last course end date.
- **Role Exclusions**: Exclude certain roles (e.g., course creators, teachers, managers) from being disabled or deleted.
- **Configurable Settings**: Administrators can configure the timeframes for disabling and deleting accounts through the Moodle admin interface.

## Installation

1. Download the plugin and place it in the `admin/tool/` directory of your Moodle installation.
2. Navigate to the Moodle admin interface and go to **Site administration > Plugins > Install plugins**.
3. Follow the prompts to complete the installation.

## Configuration

After installation, configure the plugin settings:

1. Go to **Site administration > Plugins > Local plugins > Disable Delete Students**.
2. Set the following options:
    - **Disable after course end**: Number of days after the last course end date to disable student accounts.
    - **Disable after creation**: Number of days after account creation to disable student accounts.
    - **Delete after months**: Number of months after the last course end date to delete student accounts.

## Testing

The plugin includes PHPUnit tests to ensure functionality. To run the tests, navigate to the Moodle root directory and execute:
vendor/bin/phpunit admin/tool/disable_delete_students/tests/task/cleanup_test.php
