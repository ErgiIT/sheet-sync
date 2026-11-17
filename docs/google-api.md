# Google API credentials

Sheet Sync only **reads** spreadsheets (`spreadsheets.readonly` scope). There are two ways to give it access:

| | Service account (recommended) | API key |
|---|---|---|
| Works with private sheets | Yes, once shared with the service account | No |
| Works with "anyone with the link" sheets | Yes | Yes |
| Setting | `GOOGLE_SERVICE_ACCOUNT_JSON` | `GOOGLE_API_KEY` |

When both are set, the service account is used.

## 1. Create a Google Cloud project

1. Open <https://console.cloud.google.com/> and sign in.
2. Use the project picker at the top and choose **New project**. Name it, for example `sheet-sync`, then click **Create**.
3. Make sure the new project is selected in the picker.

## 2. Enable the Google Sheets API

1. Go to **APIs & Services → Library**.
2. Search for **Google Sheets API**, open it, and click **Enable**.

You don't need the Drive API. The app reads tabs and cells only through the Sheets API.

## 3a. Service account (private sheets)

### Create the account and key

1. Go to **IAM & Admin → Service Accounts** and click **Create service account**.
2. Give it a name, for example `sheet-sync-reader`, and click **Create and continue**.
3. Skip the optional *Grant this service account access to project* and *Grant users access* steps, then click **Done**. The account needs no project roles, because access is granted per spreadsheet.
4. Open the new service account, go to the **Keys** tab, and choose **Add key → Create new key → JSON → Create**.
   A `.json` file downloads. It is the only copy, so store it like a password.

> If key creation is blocked by the organisation policy `iam.disableServiceAccountKeyCreation`,
> ask a Google Cloud admin to allow it for this project, or use a personal Google account / project.

### Install the key

Put the file where the app expects it, which by default is `storage/app/private/google/service-account.json`:

```bash
mkdir -p storage/app/private/google
cp ~/Downloads/sheet-sync-*.json storage/app/private/google/service-account.json
chmod 640 storage/app/private/google/service-account.json
```

To keep it somewhere else, set an absolute path, or a path relative to the project root, in `.env`:

```dotenv
GOOGLE_SERVICE_ACCOUNT_JSON=/etc/sheet-sync/service-account.json
```

On a server set up with the Ubuntu script, pass the key and the script installs it with the right owner:

```bash
sudo ./scripts/setup-ubuntu.sh --google-key ~/service-account.json
```

`storage/app/private` is git-ignored, so the key never ends up in the repository.

### Share each spreadsheet with the service account

The service account behaves like a separate Google user. It sees only the files shared with it.

1. Copy the `client_email` from the JSON file. It looks like
   `sheet-sync-reader@your-project.iam.gserviceaccount.com`.
2. In each Google Sheet, click **Share**, paste that email, choose **Viewer**, untick *Notify people*, and click **Share**.

If a sheet isn't shared, the sync run is marked failed with a 403 error that names the sheet.

## 3b. API key (public sheets only)

1. Go to **APIs & Services → Credentials → Create credentials → API key**.
2. Click **Edit API key**. Under **API restrictions**, choose **Restrict key** and select **Google Sheets API**.
   If the server has a fixed IP, also add it under **Application restrictions → IP addresses**.
3. Put the key in `.env`:

   ```dotenv
   GOOGLE_API_KEY=AIza...
   ```

4. Every sheet must be shared as **Anyone with the link → Viewer**.

## Check that it works

```bash
php artisan config:clear
php artisan sheets:sync --spreadsheet=<google-spreadsheet-id>
```

In the UI, the **Detect tabs** button on a spreadsheet page and **Load headers from sheet** in the mapping editor call Google straight away. They are the quickest way to confirm that the credentials and the sharing work.

| Error | Meaning |
|---|---|
| `No Google credentials configured` | Neither a readable key file nor an API key was found. Check the path, file permissions (the web user `www-data` must read it) and run `php artisan config:clear`. |
| `Google Sheets API error (403) … Share the spreadsheet …` | The sheet is not shared with the service account, or, with an API key, it is not public. |
| `Google Sheets API error (404) … Check the spreadsheet id` | The id or URL is wrong, or the file was deleted. |
| `Google Sheets API error (400) … Unable to parse range` | The tab name in the config does not exist (tab names are case-sensitive). |
| `Google Sheets API has not been used in project …` | Step 2 was skipped, or the key belongs to another project. |

Quotas: the Sheets API allows 300 read requests per minute per project. A sync makes one request per configured tab, so normal use stays far below that.
