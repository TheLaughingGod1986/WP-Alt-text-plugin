# Class Renaming Quick Reference

This document provides a quick reference for all class renames during the framework migration.

## Framework Layer

### Core Classes

```php
// OLD
namespace BeepBeepAI\AltTextGenerator;
class Plugin { }

// NEW
namespace Optti\Framework;
class Plugin { }
```

```php
// OLD
namespace BeepBeepAI\AltTextGenerator;
class API_Client_V2 { }

// NEW
namespace Optti\Framework;
class API { }
```

```php
// OLD
namespace BeepBeepAI\AltTextGenerator;
class Debug_Log { }

// NEW
namespace Optti\Framework;
class Logger { }
```

```php
// OLD
namespace BeepBeepAI\AltTextGenerator;
class Usage_Tracker { }

// NEW (partial migration)
namespace Optti\Framework;
class DB { }
```

### New Framework Classes

```php
// NEW
namespace Optti\Framework;
class License { }

// NEW
namespace Optti\Framework;
class Cache { }

// NEW
namespace Optti\Framework\Traits;
trait Singleton { }

// NEW
namespace Optti\Framework\Traits;
trait API_Response { }

// NEW
namespace Optti\Framework\Traits;
trait Settings { }

// NEW
namespace Optti\Framework\Interfaces;
interface Module { }

// NEW
namespace Optti\Framework\Interfaces;
interface Service { }

// NEW
namespace Optti\Framework\Interfaces;
interface Cache { }
```

## Admin Layer

```php
// OLD
namespace BeepBeepAI\AltTextGenerator;
class Admin { }

// NEW
namespace Optti\Admin;
class Admin_Menu { }
```

```php
// OLD
namespace BeepBeepAI\AltTextGenerator;
class Admin_Hooks { }

// NEW
namespace Optti\Admin;
class Admin_Notices { }
```

```php
// NEW
namespace Optti\Admin;
class Admin_Assets { }
```

## Modules

```php
// OLD (extracted from Core)
// Logic from BeepBeepAI\AltTextGenerator\Core

// NEW
namespace Optti\Modules;
class Alt_Generator implements \Optti\Framework\Interfaces\Module { }
```

```php
// OLD (extracted from Core)
// Logic from BeepBeepAI\AltTextGenerator\Core

// NEW
namespace Optti\Modules;
class Image_Scanner implements \Optti\Framework\Interfaces\Module { }
```

```php
// OLD (extracted from Core)
// Logic from BeepBeepAI\AltTextGenerator\Core

// NEW
namespace Optti\Modules;
class Bulk_Processor implements \Optti\Framework\Interfaces\Module { }
```

```php
// OLD
namespace BeepBeepAI\AltTextGenerator;
class Usage_Tracker { }

// NEW
namespace Optti\Modules;
class Metrics implements \Optti\Framework\Interfaces\Module { }
```

## Legacy Classes to Remove

These classes should be deleted (functionality moved elsewhere):

- `BeepBeepAI\AltTextGenerator\Loader` → Replaced by `Optti\Framework\Plugin`
- `BeepBeepAI\AltTextGenerator\Activator` → Moved to `Optti\Framework\Plugin`
- `BeepBeepAI\AltTextGenerator\Deactivator` → Moved to `Optti\Framework\Plugin`
- `BeepBeepAI\AltTextGenerator\Token_Quota_Service` → Merged into `Optti\Framework\License`
- `BeepBeepAI\AltTextGenerator\Site_Fingerprint` → Merged into `Optti\Framework\License`
- `BeepBeepAI\AltTextGenerator\Credit_Usage_Logger` → Merged into `Optti\Modules\Metrics`
- `BeepBeepAI\AltTextGenerator\Migrate_Usage` → One-time migration, remove after
- `BeepBeepAI\AltTextGenerator\OptiAI_Migration` → One-time migration, remove after

## Constants Renaming

```php
// OLD
BEEPBEEP_AI_VERSION
BEEPBEEP_AI_PLUGIN_FILE
BEEPBEEP_AI_PLUGIN_DIR
BEEPBEEP_AI_PLUGIN_URL
BEEPBEEP_AI_PLUGIN_BASENAME

// NEW
OPTTI_VERSION
OPTTI_PLUGIN_FILE
OPTTI_PLUGIN_DIR
OPTTI_PLUGIN_URL
OPTTI_PLUGIN_BASENAME

// REMOVE (legacy aliases)
BBAI_VERSION
BBAI_PLUGIN_FILE
BBAI_PLUGIN_DIR
BBAI_PLUGIN_URL
BBAI_PLUGIN_BASENAME
```

## Option Keys Renaming

```php
// OLD
beepbeepai_settings
beepbeepai_jwt_token
beepbeepai_user_data
beepbeepai_site_id
beepbeepai_license_key
beepbeepai_license_data
bbai_settings
bbai_*

// NEW
optti_settings
optti_jwt_token
optti_user_data
optti_site_id
optti_license_key
optti_license_data
```

## Function Renaming

```php
// OLD
beepbeepai_activate()
beepbeepai_deactivate()
beepbeepai_run()

// NEW
optti_activate()
optti_deactivate()
optti_run()
```

## Usage Examples

### Old Way
```php
use BeepBeepAI\AltTextGenerator\API_Client_V2;
use BeepBeepAI\AltTextGenerator\Core;

$api = new API_Client_V2();
$core = new Core();
```

### New Way
```php
use Optti\Framework\API;
use Optti\Framework\Plugin;

$api = API::instance();
$plugin = Plugin::instance();
$plugin->register_module(new \Optti\Modules\Alt_Generator());
```

### Module Registration
```php
// In main plugin file or initialization
Plugin::instance()->register_module(new \Optti\Modules\Alt_Generator());
Plugin::instance()->register_module(new \Optti\Modules\Image_Scanner());
Plugin::instance()->register_module(new \Optti\Modules\Bulk_Processor());
Plugin::instance()->register_module(new \Optti\Modules\Metrics());
```

