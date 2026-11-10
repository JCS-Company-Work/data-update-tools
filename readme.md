# PT Hub Data Update Tools

WordPress plugin containing data update tools for PT Hub. Each tool lives in its own folder under `src/Tools/` and is registered from `Plugin::boot()`.

## Structure

```
data-update-tools.php      Plugin entry point (constants, Composer autoload, boot)
src/Plugin.php             Registers each tool
src/Tools/JsonUpdater/     JSON updater tool
assets/                    Front-end scripts
```

Classes are autoloaded via Composer (PSR-4, `DataUpdateTools\` => `src/`). Run `composer install` after cloning.

## Tools

### JSON Updater

Triggers an update of the combined tile data through the Resources API.

**Usage:** add the `[json_updater]` shortcode to a page. It renders a dropdown and an "Update Tile Data" button.

| Option | Description |
| --- | --- |
| `allbrands` | All brands (excluding pending) |
| `allpending` | All brands (including pending) |

**How it works**

1. The shortcode enqueues `assets/json-updater.js` and passes it the AJAX URL, action name and a nonce.
2. Clicking the button POSTs `action=json_updater_trigger`, `nonce` and `toolType` to `admin-ajax.php`.
3. `JsonUpdater::handle_request()` verifies the nonce, requires the `edit_posts` capability and validates `toolType`.
4. It then calls `ResourcesApi::updateResource()` (`office/shared/ResourcesApi.php`) with `resources/combined-data/{toolType}`.
5. The result is returned as a JSON success/error response and shown beside the button.

**Requirements**

- `COMBINED_DATA_API_TOKEN` set in the environment or the site `.env` file (read by `ResourcesApi`).
- The Resources API reachable at the base URL defined in `ResourcesApi`.
- User must be logged in.

**Files**

- `src/Tools/JsonUpdater/JsonUpdater.php` – shortcode, asset enqueue, AJAX handler
- `assets/json-updater.js` – button click handling and outcome message
