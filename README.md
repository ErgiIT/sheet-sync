# Sheet Sync

Sheet Sync pulls Google Sheets into MySQL every day. You configure which files and tabs to read, and how their columns map to database tables. You can do this in the web UI or in a JSON file. The app creates the tables and adds new columns itself, and keeps a log of every run.

Built with Laravel 12, MySQL 8, Blade + Alpine.js + Tailwind 4, and the Google Sheets API v4.

- [What it does](#what-it-does)
- [Quick start (Ubuntu server)](#quick-start-ubuntu-server)
- [Manual setup (local development)](#manual-setup-local-development)
- [Google API credentials](docs/google-api.md)
- [Configuration](#configuration)
- [Running syncs](#running-syncs)
- [How a sync works](#how-a-sync-works)
- [Project structure](#project-structure)
- [Tests](#tests)
- [Limitations and design decisions](#limitations-and-design-decisions)

## What it does

| Requirement | How |
|---|---|
| Files with the same structure, but extra columns or columns in another order | Columns are matched by **header name**, not by position. Case, spaces, `_` and `-` are ignored, so `Product Name` = `product_name` = `product-name`. Columns that are not mapped are ignored. |
| Save to the database daily | `sheets:sync --scheduled` runs from the Laravel scheduler at `SHEETS_SYNC_TIME` (default 02:00). |
| Config of files, tabs and columns | Stored in DB tables (`spreadsheets`, `spreadsheet_tabs`, `tab_mappings`, `column_mappings`). You edit it in the UI, or import/export it as a JSON file. |
| Column mapping, e.g. `sales [date=>date, product_name=>name, price=>product_price, amount=>amount]` | Each tab mapping is *target table + list of `sheet header → table column (type)`*. |
| One file → several tables with different structures | A tab can have any number of mappings, each with its own table and columns. Several files can also write into the **same** table. |
| New column in the config → `ALTER TABLE` and fill it | Saving a mapping, or the next sync, adds missing columns to the table. That sync fills them for every row. |
| UI | Login, spreadsheets, tabs, mapping editor (with "load headers from sheet"), manual sync, sync history, config import/export. |
| Ubuntu setup | [`scripts/setup-ubuntu.sh`](scripts/setup-ubuntu.sh) |

## Quick start (Ubuntu server)

On a fresh Ubuntu 22.04 or 24.04 machine:

```bash
git clone <this-repo> /var/www/sheet-sync
cd /var/www/sheet-sync
sudo ./scripts/setup-ubuntu.sh \
    --domain sheets.example.com \
    --admin-email you@example.com \
    --google-key ~/service-account.json   # optional now, see docs/google-api.md
```

The script installs and configures:

- PHP 8.3 + extensions (via `ppa:ondrej/php` if needed), Composer (checksum verified), Node 22, MySQL 8, nginx, supervisor
- the MySQL database and user, with a generated password
- `.env` (production settings), `APP_KEY`, migrations, the admin user
- an nginx site pointing at `public/`
- a supervisor queue worker (runs "Sync now" from the UI)
- `/etc/cron.d/sheet-sync`, which runs `schedule:run` every minute

At the end the script prints the URL and the admin login. It also saves the generated passwords to `/root/sheet-sync-credentials.txt`, which only root can read. You can run the script again safely: it keeps existing passwords and the `APP_KEY`.

All options (also accepted as environment variables):

```
--app-dir DIR          --domain NAME           --timezone TZ        --sync-time HH:MM
--db-name NAME         --db-user NAME          --db-password PASS
--admin-email EMAIL    --admin-password PASS
--google-key FILE      --google-api-key KEY
```

HTTPS is not part of the script. Once DNS points at the server, run `sudo apt install certbot python3-certbot-nginx && sudo certbot --nginx -d sheets.example.com`.

## Manual setup (local development)

Requirements: PHP 8.2+ with `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`; Composer; Node 20+; MySQL 8.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create a database and fill in `.env`:

```sql
CREATE DATABASE sheet_sync CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sheet_sync'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON sheet_sync.* TO 'sheet_sync'@'localhost';
```

```dotenv
DB_DATABASE=sheet_sync
DB_USERNAME=sheet_sync
DB_PASSWORD=secret

ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=choose-one

GOOGLE_SERVICE_ACCOUNT_JSON=storage/app/private/google/service-account.json
```

Then:

```bash
php artisan migrate
php artisan db:seed          # creates/updates the admin user from ADMIN_EMAIL / ADMIN_PASSWORD
composer dev                 # web server, queue worker, logs and Vite together
```

Open <http://localhost:8000> and log in.

`composer dev` serves the CSS and JS through Vite. To run without it, build the assets once and start the server and a queue worker yourself:

```bash
npm run build
php artisan serve
php artisan queue:work --tries=1 --timeout=1800   # in a second terminal; needed for "Sync now"
```

To get the Google credentials, follow **[docs/google-api.md](docs/google-api.md)**. You create a project, enable the Sheets API, create a service account and download its JSON key. Then you share each sheet with the service account's email.

### Environment variables

| Variable | Default | |
|---|---|---|
| `GOOGLE_SERVICE_ACCOUNT_JSON` | `storage/app/private/google/service-account.json` | Service account key; absolute, or relative to the project root. |
| `GOOGLE_API_KEY` | — | Alternative for public sheets only. |
| `SHEETS_SYNC_TIME` | `02:00` | Daily sync time, in `APP_TIMEZONE`. |
| `APP_TIMEZONE` | `UTC` | |
| `SHEETS_DATE_FORMATS` | `Y-m-d,d/m/Y,d.m.Y,m/d/Y,d-m-Y` | Tried in order for dates typed as plain text in the sheet (real date cells are read as serial numbers and need no format). Put `m/d/Y` before `d/m/Y` if your text dates are US-style. |
| `SHEETS_DATETIME_FORMATS` | `Y-m-d H:i:s,…` | Same, for date-time columns. |
| `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` | — | UI login created by `db:seed`. |
| `DB_QUEUE_RETRY_AFTER` | `1900` | Must stay above the job timeout (1800 s). |

The rest of the settings (chunk size, lock time, tables that may never be written to) are in [`config/sheets.php`](config/sheets.php).

## Configuration

The configuration has four levels:

```
Spreadsheet  (Google file: name + id/URL, active)
└── Tab      (sheet name, header row, active)
    └── Mapping   (target table, sync mode, key columns, active)
        └── Column (sheet header → table column, type)
```

### In the UI

1. **Add spreadsheet**: paste the Google Sheets URL. The id is taken from it.
2. **Add a tab**: **Detect tabs** lists the tab names in the file. Set **Header row** if there are title rows above the headers.
3. **Map to a table**: enter a table name. **Load headers from sheet** fills in the columns and suggests snake_case column names; pick a type for each. Saving creates the table, or adds the missing columns, right away. That way a bad mapping fails at save time, not during the nightly sync.
4. **Sync now** queues a sync. The result appears under **Sync history**.

### As a JSON file

**Configuration → Download JSON** exports everything. **Import** adds and updates entries and never deletes anything. The same works from the console:

```bash
php artisan sheets:config:import config/sheet-sync.example.json
php artisan sheets:config:export storage/app/sheet-sync.json   # or no path to print it
```

The format is shown in [`config/sheet-sync.example.json`](config/sheet-sync.example.json). That example has two stores with different sheet layouts feeding one `sales` table, and one tab feeding two tables:

```jsonc
{
  "spreadsheets": [
    {
      "name": "Sales - EU store",
      "spreadsheet_id": "https://docs.google.com/spreadsheets/d/1Bxi.../edit",  // URL or id
      "is_active": true,
      "tabs": [
        {
          "name": "Sales",
          "header_row": 1,
          "mappings": [
            {
              "target_table": "sales",
              "sync_mode": "replace",
              "columns": [
                { "source_column": "date",         "target_column": "date",          "type": "date" },
                { "source_column": "product_name", "target_column": "name",          "type": "string" },
                { "source_column": "price",        "target_column": "product_price", "type": "decimal" },
                { "source_column": "amount",       "target_column": "amount",        "type": "integer" }
              ]
            },
            {
              "target_table": "products",
              "sync_mode": "upsert",
              "key_columns": ["name"],
              "columns": [
                { "source_column": "product_name", "target_column": "name",       "type": "string" },
                { "source_column": "price",        "target_column": "last_price", "type": "decimal" }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

Spreadsheets are matched by Google id, tabs by name, and mappings by target table. Importing the same file twice changes nothing.

### Column types

| Type | MySQL column | Accepts |
|---|---|---|
| `string` | `VARCHAR(255)` | anything; longer text is cut to 255 |
| `text` | `TEXT` | anything (cannot be an upsert key) |
| `integer` | `BIGINT` | `12`, `1 200`, `1,200`, `12.0` |
| `decimal` | `DECIMAL(18,4)` | `1234.5`, `1.234,50`, `1,234.50`, `€ 12,5` (currency symbols and spaces are stripped) |
| `boolean` | `TINYINT(1)` | checkboxes, `true/false`, `yes/no`, `y/n`, `1/0`, `on/off`, `x`/`✓` (true), `-` (false) |
| `date` | `DATE` | date cells and text dates in one of `SHEETS_DATE_FORMATS`, or a date serial number |
| `datetime` | `DATETIME` | same, with `SHEETS_DATETIME_FORMATS` |

If a value can't be read as its type, it is stored as `NULL`, and the run log counts how many per column (for example `3 value(s) in "amount" could not be read as integer`). One bad cell never fails the whole sync.

### Sync modes

- **replace** (default): every sync deletes the rows this tab wrote earlier and inserts the current rows. The table always mirrors the sheet, and running the sync twice gives the same result. Rows from *other* spreadsheets or tabs in the same table are not touched.
- **upsert**: rows are matched on the key columns. Existing ones are updated, new ones inserted, and rows that disappeared from the sheet are **kept**. Use it for tables that collect history or are shared with other writers. The app creates a unique index on the key columns. Rows with an empty key are skipped and counted in the log.

### Columns added to every target table

| Column | |
|---|---|
| `id` | auto-increment primary key |
| `source_spreadsheet_id` | `spreadsheets.id` of the file the row came from |
| `source_tab` | tab name |
| `source_row` | row number in the sheet, so you can find the row again |
| `synced_at` | when the row was last written |

These names are reserved and can't be used as target columns.

## Running syncs

| How | |
|---|---|
| Daily | The scheduler runs `sheets:sync --scheduled` at `SHEETS_SYNC_TIME`. It needs the cron entry `* * * * * cd /path && php artisan schedule:run` (the Ubuntu script adds it). Check with `php artisan schedule:list`. |
| UI | **Sync now** on a spreadsheet, or **Sync all now** on the dashboard. These queue a job, so a worker must be running (`php artisan queue:work`, `composer dev`, or the supervisor worker). |
| Console | `php artisan sheets:sync` syncs all active spreadsheets inline and prints a summary table. |

```bash
php artisan sheets:sync                                   # all active, inline
php artisan sheets:sync --spreadsheet=3 --spreadsheet=1Bxi...   # by local id or Google id
php artisan sheets:sync --queue                           # one queued job per spreadsheet
```

`sheets:sync` exits with a non-zero code if any run failed or was partial, so it can be used in monitoring.

Each run is stored in `sync_runs` with:

- the trigger: schedule, console or manual
- the status
- rows read and written
- the duration
- a list of messages, for example columns added, headers not found in the sheet, unreadable values and skipped rows

The statuses:

| Status | Meaning |
|---|---|
| `success` | everything was written |
| `partial` | some tabs or mappings failed; the others were written |
| `failed` | nothing could be written (for example, the sheet is not shared) |
| `skipped` | another sync of the same spreadsheet was still running |

## How a sync works

```
sheets:sync / SyncSpreadsheetJob
  └─ SpreadsheetSyncService::sync(spreadsheet)          cache lock per spreadsheet, creates a sync_runs row
       └─ for each active tab
            ├─ SheetReader::rows()                      one Sheets API call per tab (unformatted values)
            ├─ split the header row from the data rows  (header_row, blank rows dropped, sheet row numbers kept)
            └─ for each active mapping: TabMappingWriter::write()
                 ├─ TargetTableManager::ensure()        CREATE TABLE / ALTER TABLE ADD COLUMN, unique index for upsert keys
                 ├─ HeaderMatcher::match()              sheet header → column index, by normalised name
                 ├─ ValueCaster::cast()                 per column type, collects unreadable values
                 └─ DB::transaction                     replace: delete this tab's rows + chunked insert
                                                        upsert:  upsert on the key columns
```

A few details that matter:

- **Schema changes run before the data transaction.** On MySQL, DDL commits any open transaction. A failed write therefore rolls back cleanly and never leaves half a snapshot.
- **Errors are isolated.** If one tab or mapping fails, it doesn't stop the others. The run is then `partial`, and the error is in its messages.
- **If the sheet can't be read, nothing is deleted.** The previous data stays until a sync succeeds.
- **No overlapping syncs.** Each spreadsheet has its own lock, and the scheduler uses `withoutOverlapping()`.
- **Table and column names are validated** against `^[a-z][a-z0-9_]{0,63}$` before they reach any SQL. The app's own tables (`users`, `sessions`, `jobs`, the config tables …) can never be used as a target.

## Project structure

```
app/
├── Console/Commands/        sheets:sync, sheets:config:import, sheets:config:export
├── Contracts/SheetReader    interface for reading sheets (faked in tests)
├── Enums/                   ColumnType, SyncMode, SyncStatus, SyncTrigger, SourceColumn
├── Http/                    controllers + form requests for the UI
├── Jobs/SyncSpreadsheetJob  queued sync (UI "Sync now", sheets:sync --queue)
├── Models/                  Spreadsheet, SpreadsheetTab, TabMapping, ColumnMapping, SyncRun
├── Services/
│   ├── Configuration/       MappingService (save + ensure schema), ConfigurationService (JSON import/export), MappingRules
│   ├── Google/              GoogleClientFactory, GoogleSheetReader
│   ├── Schema/              TargetTableManager (CREATE / ALTER / indexes)
│   └── Sync/                SpreadsheetSyncService, TabMappingWriter, HeaderMatcher, ValueCaster
└── Support/                 SqlIdentifier, GoogleSpreadsheetId
config/sheets.php            app settings
config/sheet-sync.example.json
docs/google-api.md           how to create the Google credentials
scripts/setup-ubuntu.sh      server setup
```

## Tests

```bash
composer test     # Pest, in-memory SQLite, Google is faked
composer lint     # Laravel Pint
```

To run the same tests on MySQL, use an **empty, separate** database. Each test rebuilds it from the migrations, which drops every table in it:

```bash
mysql -uroot -e "CREATE DATABASE sheet_sync_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
DB_CONNECTION=mysql DB_DATABASE=sheet_sync_test DB_USERNAME=root DB_PASSWORD= composer test
```

Values set on the command line override the SQLite settings in `phpunit.xml`. Never point `DB_DATABASE` at your real database.

The tests cover:

- reordered and extra columns
- header rows lower in the sheet
- one tab → several tables, and several files → one table
- replace idempotency
- adding a column to an existing table
- missing headers and unreadable values
- read failures that keep existing data
- partial runs, upsert keys, value casting (dates, decimals with different separators, booleans)
- validation, the UI pages, JSON import/export, and the console command

## Limitations and design decisions

- **Columns are never dropped or retyped.** Removing a column from a mapping leaves it in the table, and so does changing its type. An automated `DROP COLUMN` on a nightly job is too easy to get wrong. Change existing columns with a migration or by hand.
- **Removing** a spreadsheet, tab or mapping keeps the table and the data already synced.
- **Renaming a tab** in the UI moves its synced rows to the new name (`source_tab`), so replace mode keeps clearing them. Rename the tab in Google Sheets first, then here, or the next sync can't find it.
- **Upsert** skips rows whose key is empty. MySQL treats `NULL`s as distinct in a unique index, so those rows would be duplicated on every run.
- A table may have several upsert mappings with **different key sets**. Each key set gets its own unique index, and every one of them must hold for every row.
- Sheets are read in full, one request per tab. This is fine up to tens of thousands of rows per tab. Larger sheets would need to be read in ranges.
- Numbers are read unformatted, so thousand separators and currency formats in the sheet don't matter. Date cells are read as serial numbers, so they are stored correctly whatever the sheet's locale (`3/4/2026` in a US sheet is 4 March). Map date cells as `date` or `datetime`: mapped to `string`, they store the serial number (e.g. `46085`). Only dates typed as plain text go through `SHEETS_DATE_FORMATS`, and ambiguous ones are resolved by order (`d/m/Y` before `m/d/Y` by default).
- There is a single admin login. User management was out of scope.
