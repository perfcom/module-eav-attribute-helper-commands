# Perfcom EavAttributeHelperCommands

A Magento 2 module that provides CLI commands for managing EAV attributes.


## Installation

```bash
composer require perfcom/magento2-eav-attribute-helper-commands
```

## Usage

### Command 1: Check Missing Classes

Check for attributes with missing class references:

```bash
php bin/magento perfcom:eav:check-missing-classes
```

### Command X:Your command here?

Make a PR or open an issue if you have a command that you would like to see!


## Important Notes

⚠️ **WARNING**: Always backup your database before executing any SQL queries!

- The command only gives SQL queries, it does not modify the database
- Review the generated SQL queries carefully before executing them
- Test on a development/staging environment first
- Setting class references to NULL is generally safer than deleting entire attributes
- Deleting attributes may cause data loss if the attribute is still in use
