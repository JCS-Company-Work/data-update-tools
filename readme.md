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

## Deployment

Run the **Deploy to Live** GitHub Actions workflow manually and select the branch, tag or commit to deploy. It installs production Composer dependencies, including the autoloader, before syncing the plugin to the server.

Configure these GitHub Actions secrets for the repository:

- `DEPLOY_SSH_KEY`: private SSH key for deployment.
- `DEPLOY_SSH_PASSPHRASE`: passphrase for the private key.

Set these repository variables:

- `SSH_HOST`: live server hostname.
- `PT_SSH_USER`: SSH user.

Set the repository variable `LIVE_DEPLOY_PATH` to the absolute path of this plugin's directory, ending in `/data-update-tools` (not the parent plugins directory). The deployment uses `rsync --delete` to remove files in that directory which are no longer in the deployment; excluded files are preserved.

The server must also have `office/shared/ResourcesApi.php` and its API configuration available; they are outside this plugin and are not deployed by this workflow.

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

### Florim UK Update

Add the `[florimuk_update]` shortcode to a page. Select **Florim UK** and click **Update Tile Data** to regenerate Florim UK data from Google Sheets. The **Refresh all resources** option regenerates all resources instead.

The AJAX handler checks its nonce and requires the `edit_posts` capability. It uses `ResourcesApi::updateResource()` to call `PUT /api/florimuk`, where the Resources API reads and formats the sheet data. Refreshing all resources uses `ResourcesApi::refreshAllResources()`.

The server needs `FLORIMUK_API_TOKEN` for the Florim UK update and `RESOURCE_API_TOKEN` for refresh-all, as well as a reachable Resources API configured with Google Sheets access. Users must be logged in.
